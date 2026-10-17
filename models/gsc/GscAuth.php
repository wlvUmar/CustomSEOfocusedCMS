<?php
// path: ./models/gsc/GscAuth.php
// GSC OAuth2: auth URL, code exchange, access-token refresh with file lock.
// Loaded via models/GscClient.php (thin facade keeps caller API stable).

require_once BASE_PATH . '/models/gsc/GscTokenStore.php';

class GscAuth {

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const OAUTH_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const OAUTH_SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    public static function isConfigured(): bool {
        return (bool)((defined('GSC_CLIENT_ID') ? GSC_CLIENT_ID : '') ?: getenv('GSC_CLIENT_ID'));
    }

    public static function getClientId(): string {
        return (string)((defined('GSC_CLIENT_ID') ? GSC_CLIENT_ID : '') ?: getenv('GSC_CLIENT_ID'));
    }

    public static function getClientSecret(): string {
        return (string)((defined('GSC_CLIENT_SECRET') ? GSC_CLIENT_SECRET : '') ?: getenv('GSC_CLIENT_SECRET'));
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

    private static function postForm(array $payload): ?array {
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err !== '' || $code < 200 || $code >= 300) {
            error_log("GscClient token POST failed HTTP $code: " . mb_substr((string)$resp, 0, 500) . " err=$err");
            return null;
        }
        $data = json_decode((string)$resp, true);
        if (!is_array($data) || empty($data['access_token'])) {
            error_log("GscClient token POST bad json: " . mb_substr((string)$resp, 0, 500));
            return null;
        }
        return $data;
    }

    public static function exchangeCode(string $code): ?array {
        $data = self::postForm([
            'code' => $code,
            'client_id' => self::getClientId(),
            'client_secret' => self::getClientSecret(),
            'redirect_uri' => self::getRedirectUri(),
            'grant_type' => 'authorization_code',
        ]);
        return $data; // contains access_token, refresh_token (first time), expires_in, scope
    }

    public static function refreshAccessToken(string $refreshToken): ?string {
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
                        $tok = GscTokenStore::decrypt((string)$row['access_token']);
                        if ($tok !== '') return $tok;
                    } catch (Throwable $e) {
                        error_log("GscClient refresh re-check decrypt failed: " . $e->getMessage());
                    }
                }
            }

            $data = self::postForm([
                'client_id' => self::getClientId(),
                'client_secret' => self::getClientSecret(),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);
            if ($data === null) return null;
            $access = (string)$data['access_token'];
            $expiresIn = (int)($data['expires_in'] ?? 3600);
            $expiresAt = date('Y-m-d H:i:s', time() + max(60, $expiresIn - 30));
            $enc = GscTokenStore::encrypt($access);
            $db->query("UPDATE gsc_tokens SET access_token = ?, expires_at = ? WHERE id = 1", [$enc, $expiresAt]);
            return $access;
        } finally {
            if ($fp) { @flock($fp, LOCK_UN); @fclose($fp); }
        }
    }
}
