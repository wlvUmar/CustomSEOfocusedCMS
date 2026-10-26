<?php
// path: ./models/SeoApiTokenStore.php
// Bearer tokens for the read-only SEO API. Plaintext tokens are never stored:
// only sha256(token) lives in seo_api_tokens (migrations/013_seo_api_tokens.php).

require_once BASE_PATH . '/core/Database.php';

class SeoApiTokenStore {
    // 'write' = may edit SEO metadata via POST /api/seo/v1/page/{slug}/fields. Never part of a default token.
    public const ALLOWED_SCOPES = ['pages', 'stats', 'gsc', 'write'];
    public const DEFAULT_DAYS = 30;
    public const MAX_DAYS = 365;

    /** Returns the active token row (id, label, scopes) or null. Revoked/expired/unknown → null. */
    public static function findActiveByToken(string $token): ?array {
        if ($token === '' || strlen($token) > 200) return null;
        $hash = hash('sha256', $token);
        $row = Database::getInstance()->fetchOne(
            "SELECT id, label, token_hash, scopes FROM seo_api_tokens
             WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > NOW()",
            [$hash]
        );
        if (!$row || !hash_equals((string)$row['token_hash'], $hash)) return null;
        return ['id' => (int)$row['id'], 'label' => $row['label'], 'scopes' => (string)$row['scopes']];
    }

    /**
     * Issue a new token. Returns plaintext token ONCE plus metadata.
     * @param string[] $scopes subset of ALLOWED_SCOPES
     */
    public static function issue(string $label, array $scopes, int $days = self::DEFAULT_DAYS): array {
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 100) throw new InvalidArgumentException('label required (max 100 chars)');
        $scopes = array_values(array_unique(array_map('trim', $scopes)));
        if (!$scopes) throw new InvalidArgumentException('at least one scope required');
        foreach ($scopes as $s) {
            if (!in_array($s, self::ALLOWED_SCOPES, true)) throw new InvalidArgumentException("unknown scope: $s");
        }
        $days = max(1, min(self::MAX_DAYS, $days));

        $token = 'seo_' . bin2hex(random_bytes(32));
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO seo_api_tokens (label, token_hash, scopes, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL {$days} DAY))",
            [$label, hash('sha256', $token), implode(',', $scopes)]
        );
        $id = (int)$db->lastInsertId();
        $row = $db->fetchOne("SELECT expires_at FROM seo_api_tokens WHERE id = ?", [$id]);
        return ['id' => $id, 'label' => $label, 'scopes' => implode(',', $scopes),
                'expires_at' => $row['expires_at'] ?? null, 'token' => $token];
    }

    public static function revoke(int $id): bool {
        $db = Database::getInstance();
        $stmt = $db->query("UPDATE seo_api_tokens SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL", [$id]);
        return $stmt->rowCount() > 0;
    }

    /** Never returns token_hash. */
    public static function listAll(): array {
        return Database::getInstance()->fetchAll(
            "SELECT id, label, scopes, created_at, expires_at, last_used_at, revoked_at
             FROM seo_api_tokens ORDER BY id DESC"
        );
    }

    public static function touch(int $id): void {
        try {
            Database::getInstance()->query("UPDATE seo_api_tokens SET last_used_at = NOW() WHERE id = ?", [$id]);
        } catch (Throwable $e) {
            error_log('[SeoApi] touch failed: ' . $e->getMessage());
        }
    }

    /**
     * Fixed-window rate limit per token (requests per minute), stored in analytics_throttle
     * (migrations/003). Fails open on DB error so analytics reads are not blocked by a throttle outage.
     */
    public static function rateLimitOk(int $tokenId, int $maxPerMinute = 120): bool {
        $bucket = intdiv(time(), 60);
        $key = 'seo_api_' . $tokenId . '_' . $bucket;
        $expires = date('Y-m-d H:i:s', ($bucket + 2) * 60);
        try {
            $db = Database::getInstance();
            $db->query(
                "INSERT INTO analytics_throttle (throttle_key, hits, expires_at) VALUES (?, 1, ?)
                 ON DUPLICATE KEY UPDATE hits = hits + 1",
                [$key, $expires]
            );
            if (random_int(1, 200) === 1) {
                $db->query("DELETE FROM analytics_throttle WHERE throttle_key LIKE 'seo_api_%' AND expires_at < NOW()");
            }
            $row = $db->fetchOne("SELECT hits FROM analytics_throttle WHERE throttle_key = ?", [$key]);
            return $row ? ((int)$row['hits'] <= $maxPerMinute) : true;
        } catch (Throwable $e) {
            error_log('[SeoApi] rate limit error: ' . $e->getMessage());
            return true;
        }
    }
}
