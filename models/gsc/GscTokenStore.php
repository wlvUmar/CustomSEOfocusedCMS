<?php
// path: ./models/gsc/GscTokenStore.php
// GSC token persistence: gsc_tokens single row (id=1), AES-256-GCM + HKDF.
// Loaded via models/GscClient.php (thin facade keeps caller API stable).

require_once BASE_PATH . '/core/Database.php';

class GscTokenStore {

    public static function ensureTable(): void {
        return;
    }

    private static function encKey(): string {
        static $warnedReuse = false;
        $k = (string)((defined('GSC_ENCRYPTION_KEY') ? GSC_ENCRYPTION_KEY : '') ?: getenv('GSC_ENCRYPTION_KEY'));
        if ($k === '') {
            $k = (string)((defined('BOT_API_SECRET') ? BOT_API_SECRET : '') ?: getenv('BOT_API_SECRET'));
            if ($k !== '' && !$warnedReuse) {
                if (function_exists('logDebug')) { logDebug("GscClient: GSC_ENCRYPTION_KEY not set — falling back to BOT_API_SECRET (key reuse). Set dedicated GSC_ENCRYPTION_KEY in .env."); }
                $warnedReuse = true;
            }
        }
        return $k;
    }

    public static function deriveKey(string $key): string {
        // Use HKDF-SHA256 instead of raw SHA256 (project-11#5)
        if (function_exists('hash_hkdf')) {
            return hash_hkdf('sha256', $key, 32, 'gsc-encrypt', '');
        }
        // Fallback to SHA256 if hkdf not available
        return hash('sha256', $key, true);
    }

    public static function encrypt(string $plain): string {
        $key = self::encKey();
        if ($key === '' || !function_exists('openssl_encrypt')) {
            if (function_exists('logDebug')) { logDebug("GscClient: GSC_ENCRYPTION_KEY not set — storing token plaintext is insecure. Set GSC_ENCRYPTION_KEY in .env."); }
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

    public static function decrypt(string $stored): string {
        static $warnedPlain = false;
        $isEncrypted = str_starts_with($stored, 'gcm$');
        $key = self::encKey();
        // BC: allow plaintext rows but warn once per request and migrate on next save (remaining-bugs #8)
        if (!$isEncrypted) {
            if (!$warnedPlain) {
                if (function_exists('logDebug')) { logDebug("GscClient: token stored as plaintext — will re-encrypt on next save. Re-authenticate recommended."); }
                $warnedPlain = true;
            }
            if ($key === '' || !function_exists('openssl_decrypt')) {
                return $stored;
            }
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
            if (function_exists('logDebug')) { logDebug("GscClient: decrypted with legacy key — will re-encrypt with HKDF on next save."); }
        }
        return $pt;
    }

    public static function getSiteUrl(): string {
        $v = trim((string)((defined('GSC_SITE_URL') ? GSC_SITE_URL : '') ?: getenv('GSC_SITE_URL')));
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
            if (function_exists('logDebug')) { logDebug("GscClient getRefreshToken decrypt failed: " . $e->getMessage()); }
            return null;
        }
    }

    public static function isConnected(): bool {
        return self::getRefreshToken() !== null;
    }

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
                    if (function_exists('logDebug')) { logDebug("GscClient getAccessToken decrypt failed: " . $e->getMessage()); }
                }
            }
        }
        // Refresh.
        $refresh = self::getRefreshToken();
        if ($refresh === null || $refresh === '') return null;
        return GscAuth::refreshAccessToken($refresh);
    }

    public static function getStatus(): array {
        $configured = GscAuth::isConfigured();
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
            'redirect_uri' => GscAuth::getRedirectUri(),
        ];
        // Expose available properties for multi-site debug (02-architecture #8) — uses live listSites if connected
        if ($connected) {
            try {
                $sites = GscSearchApi::listSites();
                if (is_array($sites)) $status['available_sites'] = array_map(fn($s) => $s['siteUrl'] ?? $s['site_url'] ?? '', $sites);
            } catch (Throwable $e) {}
        }
        return $status;
    }

    public static function disconnect(): void {
        self::ensureTable();
        Database::getInstance()->query("DELETE FROM gsc_tokens WHERE id = 1");
        GscCache::clearCache();
    }
}
