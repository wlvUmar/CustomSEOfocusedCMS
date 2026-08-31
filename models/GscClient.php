<?php
// path: ./models/GscClient.php
// Google Search Console API client for AI Studio (MCP-like).
// - OAuth2 refresh-token flow (no google/* SDK — pure curl, zero deps).
// - Search Analytics query with caching and graceful auth_required fallback.
// Env: GSC_CLIENT_ID, GSC_CLIENT_SECRET, GSC_SITE_URL (e.g. https://kuplyu-tashkent.uz/ or sc-domain:kuplyu-tashkent.uz)
// Token store: `gsc_tokens` table (single row id=1, encrypted at rest if GSC_ENCRYPTION_KEY set).

class GscClient {

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const OAUTH_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const OAUTH_SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';
    private const API_BASE = 'https://www.googleapis.com/webmasters/v3';
    private const CACHE_TTL = 3600; // seconds

    public static function isConfigured(): bool {
        return (bool)(getenv('GSC_CLIENT_ID') ?: (defined('GSC_CLIENT_ID') ? GSC_CLIENT_ID : ''));
    }

    public static function getClientId(): string {
        return (string)(getenv('GSC_CLIENT_ID') ?: (defined('GSC_CLIENT_ID') ? GSC_CLIENT_ID : ''));
    }

    public static function getClientSecret(): string {
        return (string)(getenv('GSC_CLIENT_SECRET') ?: (defined('GSC_CLIENT_SECRET') ? GSC_CLIENT_SECRET : ''));
    }

    public static function getSiteUrl(): string {
        $v = trim((string)(getenv('GSC_SITE_URL') ?: (defined('GSC_SITE_URL') ? GSC_SITE_URL : '')));
        if ($v !== '') return $v;
        // Check DB stored site_url (from OAuth) before falling back to hardcoded domain (02-architecture #8 BC)
        try {
            self::ensureTable();
            $row = Database::getInstance()->fetchOne("SELECT site_url FROM gsc_tokens WHERE id = 1");
            if ($row && !empty($row['site_url'])) {
                $dbUrl = trim((string)$row['site_url']);
                if ($dbUrl !== '') return $dbUrl;
            }
        } catch (Throwable $e) {}
        $base = trim((string)(defined('BASE_URL') ? BASE_URL : ''));
        if ($base === '') return 'sc-domain:kuplyu-tashkent.uz';
        return rtrim($base, '/') . '/';
    }

    public static function getSiteUrlFor(?string $override): string {
        $o = trim((string)$override);
        if ($o !== '') return $o;
        return self::getSiteUrl();
    }

    public static function getRedirectUri(): string {
        $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');
        return $base . '/admin/ai-studio/gsc-callback';
    }

    public static function getAuthUrl(string $state): string {
        $params = http_build_query([
            'client_id' => self::getClientId(),
            'redirect_uri' => self::getRedirectUri(),
            'response_type' => 'code',
            'scope' => self::OAUTH_SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
        return self::OAUTH_AUTH_URL . '?' . $params;
    }

    // ------------------------------------------------------------------
    // Token storage (gsc_tokens table, single row id=1).
    // If GSC_ENCRYPTION_KEY is set, refresh_token is AES-256-GCM encrypted.
    // ------------------------------------------------------------------

    private static function ensureTable(): void {
        $db = Database::getInstance();
        $db->query("CREATE TABLE IF NOT EXISTS `gsc_tokens` (
          `id` int(11) NOT NULL DEFAULT 1,
          `refresh_token` text NOT NULL,
          `access_token` text DEFAULT NULL,
          `expires_at` datetime DEFAULT NULL,
          `site_url` varchar(500) DEFAULT NULL,
          `email` varchar(255) DEFAULT NULL,
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private static function encKey(): string {
        $k = (string)(getenv('GSC_ENCRYPTION_KEY') ?: (defined('GSC_ENCRYPTION_KEY') ? GSC_ENCRYPTION_KEY : ''));
        if ($k === '') {
            // Fallback to BOT_API_SECRET for BC, but warn — key reuse is discouraged (project-11#5, config#10)
            $k = (string)(getenv('BOT_API_SECRET') ?: '');
            if ($k !== '') {
                error_log("GscClient: GSC_ENCRYPTION_KEY not set — falling back to BOT_API_SECRET (key reuse). Set dedicated GSC_ENCRYPTION_KEY in .env.");
            }
        }
        return $k;
    }

    private static function deriveKey(string $key): string {
        // Use HKDF-SHA256 instead of raw SHA256 (project-11#5)
        if (function_exists('hash_hkdf')) {
            return hash_hkdf('sha256', $key, 32, 'gsc-encrypt', '');
        }
        // Fallback to SHA256 if hkdf not available
        return hash('sha256', $key, true);
    }

    private static function encrypt(string $plain): string {
        $key = self::encKey();
        if ($key === '' || !function_exists('openssl_encrypt')) {
            error_log("GscClient: GSC_ENCRYPTION_KEY not set — storing token plaintext is insecure. Set GSC_ENCRYPTION_KEY in .env.");
            if ($key === '') {
                throw new RuntimeException('GSC_ENCRYPTION_KEY not configured — refusing to store plaintext token. Set GSC_ENCRYPTION_KEY in .env.');
            }
            return $plain;
        }
        if (strlen($key) < 16) {
            throw new RuntimeException('GSC_ENCRYPTION_KEY too short — need at least 16 chars.');
        }
        $k = self::deriveKey($key);
        $iv = random_bytes(12);
        $ct = openssl_encrypt($plain, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) return $plain;
        return 'gcm$' . base64_encode($iv . $tag . $ct);
    }

    private static function decrypt(string $stored): string {
        $isEncrypted = str_starts_with($stored, 'gcm$');
        $key = self::encKey();
        // BC: allow plaintext rows but warn and migrate on next save (avoids immediate breakage for existing installs)
        if (!$isEncrypted) {
            error_log("GscClient: token stored as plaintext — will re-encrypt on next save. Re-authenticate recommended.");
            if ($key === '' || !function_exists('openssl_decrypt')) {
                // No key to validate — return as-is for BC, but caller should treat as connected
                return $stored;
            }
            // If key exists, plaintext is still usable until re-encrypted, but flag for migration
            return $stored;
        }
        if ($key === '' || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('GSC_ENCRYPTION_KEY not configured — cannot decrypt token.');
        }
        // Try HKDF first, fallback to legacy sha256 for BC (tokens encrypted before batch 05)
        $raw = base64_decode(substr($stored, 4), true);
        if ($raw === false || strlen($raw) < 28) {
            throw new RuntimeException('GSC token corrupt — re-authenticate.');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct = substr($raw, 28);
        $k = self::deriveKey($key);
        $pt = openssl_decrypt($ct, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
        if ($pt === false) {
            // Legacy fallback: sha256-derived key
            $legacyK = hash('sha256', $key, true);
            $pt = openssl_decrypt($ct, 'aes-256-gcm', $legacyK, OPENSSL_RAW_DATA, $iv, $tag);
            if ($pt === false) {
                throw new RuntimeException('GSC token decrypt failed — key mismatch or corrupt. Re-authenticate.');
            }
            error_log("GscClient: decrypted with legacy key — will re-encrypt with HKDF on next save.");
        }
        return $pt;
    }

    public static function saveRefreshToken(string $refreshToken, ?string $siteUrl = null, ?string $email = null): void {
        self::ensureTable();
        $db = Database::getInstance();
        $enc = self::encrypt($refreshToken);
        // Upsert single row id=1.
        $db->query("INSERT INTO gsc_tokens (id, refresh_token, site_url, email) VALUES (1, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE refresh_token = VALUES(refresh_token), site_url = COALESCE(VALUES(site_url), site_url), email = COALESCE(VALUES(email), email)",
            [$enc, $siteUrl, $email]);
        // Invalidate cached access token.
        $db->query("UPDATE gsc_tokens SET access_token = NULL, expires_at = NULL WHERE id = 1");
    }

    public static function getRefreshToken(): ?string {
        self::ensureTable();
        $row = Database::getInstance()->fetchOne("SELECT refresh_token FROM gsc_tokens WHERE id = 1");
        if (!$row || empty($row['refresh_token'])) return null;
        try {
            return self::decrypt((string)$row['refresh_token']);
        } catch (Throwable $e) {
            error_log("GscClient getRefreshToken decrypt failed: " . $e->getMessage());
            return null;
        }
    }

    public static function isConnected(): bool {
        return self::getRefreshToken() !== null;
    }

    public static function getStatus(): array {
        $configured = self::isConfigured();
        $connected = self::isConnected();
        $row = null;
        if ($connected) {
            $row = Database::getInstance()->fetchOne("SELECT site_url, email, updated_at, expires_at FROM gsc_tokens WHERE id = 1");
        }
        $status = [
            'configured' => $configured,
            'connected' => $connected,
            'site_url' => $row['site_url'] ?? self::getSiteUrl(),
            'email' => $row['email'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
            'redirect_uri' => self::getRedirectUri(),
        ];
        // Expose available properties for multi-site debug (02-architecture #8) — uses live listSites if connected
        if ($connected) {
            try {
                $sites = self::listSites();
                if (is_array($sites)) $status['available_sites'] = array_map(fn($s) => $s['siteUrl'] ?? $s['site_url'] ?? '', $sites);
            } catch (Throwable $e) {}
        }
        return $status;
    }

    public static function disconnect(): void {
        self::ensureTable();
        Database::getInstance()->query("DELETE FROM gsc_tokens WHERE id = 1");
        self::clearCache();
    }

    // ------------------------------------------------------------------
    // Access token (cached, auto-refresh).
    // ------------------------------------------------------------------

    public static function getAccessToken(): ?string {
        self::ensureTable();
        $db = Database::getInstance();
        $row = $db->fetchOne("SELECT access_token, expires_at FROM gsc_tokens WHERE id = 1");
        if ($row && !empty($row['access_token']) && !empty($row['expires_at'])) {
            $exp = strtotime((string)$row['expires_at']);
            if ($exp !== false && $exp > time() + 60) {
                try {
                    $tok = self::decrypt((string)$row['access_token']);
                    if ($tok !== '') return $tok;
                } catch (Throwable $e) {
                    error_log("GscClient getAccessToken decrypt failed: " . $e->getMessage());
                }
            }
        }
        // Refresh.
        $refresh = self::getRefreshToken();
        if ($refresh === null || $refresh === '') return null;
        $tok = self::refreshAccessToken($refresh);
        return $tok;
    }

    private static function refreshAccessToken(string $refreshToken): ?string {
        // File lock to prevent concurrent refresh races (C5).
        $lockFile = BASE_PATH . '/storage/gsc_token.lock';
        $lockDir = dirname($lockFile);
        if (!is_dir($lockDir)) @mkdir($lockDir, 0750, true);
        $fp = @fopen($lockFile, 'c');
        $hasLock = false;
        if ($fp) {
            $hasLock = @flock($fp, LOCK_EX | LOCK_NB);
            if (!$hasLock) {
                // Wait up to 10s for holder to release (avoid forever block, project-11#7)
                for ($wait = 0; $wait < 10; $wait++) {
                    sleep(1);
                    if (@flock($fp, LOCK_EX | LOCK_NB)) { $hasLock = true; break; }
                }
                if (!$hasLock) {
                    error_log("GscClient: token lock wait timeout after 10s, proceeding without lock");
                }
            }
        }
        try {
            // Re-check if another process already refreshed while we waited.
            $db = Database::getInstance();
            $row = $db->fetchOne("SELECT access_token, expires_at FROM gsc_tokens WHERE id = 1");
            if ($row && !empty($row['access_token']) && !empty($row['expires_at'])) {
                $exp = strtotime((string)$row['expires_at']);
                if ($exp !== false && $exp > time() + 60) {
                    try {
                        $tok = self::decrypt((string)$row['access_token']);
                        if ($tok !== '') return $tok;
                    } catch (Throwable $e) {
                        error_log("GscClient refresh re-check decrypt failed: " . $e->getMessage());
                    }
                }
            }

            $ch = curl_init(self::TOKEN_URL);
            $payload = http_build_query([
                'client_id' => self::getClientId(),
                'client_secret' => self::getClientSecret(),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_TIMEOUT => 15,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($err !== '' || $code < 200 || $code >= 300) {
                error_log("GscClient refresh failed HTTP $code: " . mb_substr((string)$resp, 0, 500) . " err=$err");
                return null;
            }
            $data = json_decode((string)$resp, true);
            if (!is_array($data) || empty($data['access_token'])) {
                error_log("GscClient refresh bad json: " . mb_substr((string)$resp, 0, 500));
                return null;
            }
            $access = (string)$data['access_token'];
            $expiresIn = (int)($data['expires_in'] ?? 3600);
            $expiresAt = date('Y-m-d H:i:s', time() + max(60, $expiresIn - 30));
            $enc = self::encrypt($access);
            $db->query("UPDATE gsc_tokens SET access_token = ?, expires_at = ? WHERE id = 1", [$enc, $expiresAt]);
            return $access;
        } finally {
            if ($fp) { @flock($fp, LOCK_UN); @fclose($fp); }
        }
    }

    public static function exchangeCode(string $code): ?array {
        $ch = curl_init(self::TOKEN_URL);
        $payload = http_build_query([
            'code' => $code,
            'client_id' => self::getClientId(),
            'client_secret' => self::getClientSecret(),
            'redirect_uri' => self::getRedirectUri(),
            'grant_type' => 'authorization_code',
        ]);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err !== '' || $code2 < 200 || $code2 >= 300) {
            error_log("GscClient exchange failed HTTP $code2: " . mb_substr((string)$resp, 0, 500) . " err=$err");
            return null;
        }
        $data = json_decode((string)$resp, true);
        if (!is_array($data) || empty($data['access_token'])) return null;
        return $data; // contains access_token, refresh_token (first time), expires_in, scope
    }

    // ------------------------------------------------------------------
    // Search Analytics query (with file cache).
    // ------------------------------------------------------------------

    private static function cacheKey(string $siteUrl, array $payload): string {
        return sha1($siteUrl . '|' . json_encode($payload));
    }

    private static function cacheGet(string $key): ?array {
        $dir = BASE_PATH . '/storage/gsc_cache';
        $file = $dir . '/' . $key . '.json';
        if (!is_file($file)) return null;
        $mtime = @filemtime($file);
        if ($mtime === false || (time() - $mtime) > self::CACHE_TTL) return null;
        $fh = @fopen($file, 'r');
        if (!$fh) return null;
        @flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh);
        @flock($fh, LOCK_UN);
        @fclose($fh);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private static function cacheSet(string $key, array $data): void {
        $dir = BASE_PATH . '/storage/gsc_cache';
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        $file = $dir . '/' . $key . '.json';
        // Bounded cache: cap at 200 files, GC oldest if over
        $files = glob($dir . '/*.json') ?: [];
        if (count($files) > 200) {
            usort($files, fn($a,$b) => filemtime($a) <=> filemtime($b));
            foreach (array_slice($files, 0, count($files) - 180) as $old) {
                if (is_link($old)) continue; // skip symlink (TOCTOU)
                @unlink($old);
            }
        }
        $fh = @fopen($file, 'c');
        if ($fh) {
            @flock($fh, LOCK_EX);
            ftruncate($fh, 0);
            fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            @flock($fh, LOCK_UN);
            @fclose($fh);
        } else {
            @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
    }

    public static function clearCache(): void {
        $dir = BASE_PATH . '/storage/gsc_cache';
        if (!is_dir($dir)) return;
        foreach (glob($dir . '/*.json') ?: [] as $f) {
            if (is_link($f)) continue;
            @unlink($f);
        }
    }

    /**
     * Query Search Analytics.
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param string[] $dimensions e.g. ['query'], ['page'], ['query','page']
     * @param array $dimensionFilterGroups GSC filter groups
     * @param int $rowLimit max 25000 (we default 1000-5000)
     * @return array|null rows or null on auth failure
     */
    public static function searchAnalytics(string $startDate, string $endDate, array $dimensions = [], array $dimensionFilterGroups = [], int $rowLimit = 1000, string $aggregationType = 'auto', ?string $siteUrlOverride = null): ?array {
        $siteUrl = self::getSiteUrlFor($siteUrlOverride);
        $payload = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => $dimensions,
            'dimensionFilterGroups' => $dimensionFilterGroups,
            'aggregationType' => $aggregationType,
            'rowLimit' => min(25000, max(1, $rowLimit)),
        ];
        // Remove empty filters for cleaner cache key.
        if (empty($dimensionFilterGroups)) unset($payload['dimensionFilterGroups']);

        $token = self::getAccessToken();
        if ($token === null) return null; // auth_required

        $cacheKey = self::cacheKey($siteUrl, $payload);
        $cached = self::cacheGet($cacheKey);
        if ($cached !== null) return $cached;

        $url = self::API_BASE . '/sites/' . rawurlencode($siteUrl) . '/searchAnalytics/query';
        $ch = curl_init($url);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($code === 401 || $code === 403) {
            error_log("GscClient searchAnalytics auth error HTTP $code: " . mb_substr((string)$resp, 0, 800));
            // Invalidate cache on auth failure to avoid serving stale data (H10).
            self::clearCache();
            // Also clear stored access_token so next call triggers refresh.
            try { Database::getInstance()->query("UPDATE gsc_tokens SET access_token = NULL, expires_at = NULL WHERE id = 1"); } catch (Throwable $e) {}
            return null; // treat as auth_required; token may be revoked
        }
        if ($err !== '' || $code < 200 || $code >= 300) {
            $msg = $err !== '' ? $err : mb_substr((string)$resp, 0, 800);
            throw new RuntimeException("GSC API error HTTP $code: $msg");
        }
        $data = json_decode((string)$resp, true);
        if (!is_array($data)) throw new RuntimeException("GSC API bad JSON");
        $rows = $data['rows'] ?? [];
        if (!is_array($rows)) $rows = [];

        self::cacheSet($cacheKey, $rows);
        return $rows;
    }

    public static function listSites(): ?array {
        $token = self::getAccessToken();
        if ($token === null) return null;
        $ch = curl_init(self::API_BASE . '/sites');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300) return null;
        $data = json_decode((string)$resp, true);
        return $data['siteEntry'] ?? [];
    }

    public static function slugFromPage(string $pageUrl): string {
        $page = trim($pageUrl);
        if (preg_match('#https?://[^/]+/(.*)#i', $page, $m)) $page = '/' . $m[1];
        $page = trim($page, "/ \t");
        $page = preg_replace('#^(ru|uz)/#i', '', $page);
        if ($page === '' || $page === '/') return 'main';
        if (str_contains($page, '/')) {
            $parts = explode('/', $page);
            $page = end($parts);
        }
        $slug = mb_strtolower($page, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9_\-]/', '-', $slug);
        $slug = trim((string)$slug, '-');
        return $slug === '' ? 'main' : $slug;
    }
}
