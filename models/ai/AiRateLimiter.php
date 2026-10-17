<?php
// path: ./services/AiRateLimiter.php
// AI Studio run gate: dual session + file flock bucket, 20 runs / 600s
// per admin (user_id + IP). Pure gate — returns verdict, controller
// owns the HTTP 429 + Retry-After response.

class AiRateLimiter {

    public const LIMIT = 20;
    public const WINDOW_SECONDS = 600;
    public const MESSAGE = 'AI Studio rate limit: max 20 runs per 10 minutes. Please wait.';

    public static function limit(): int {
        return defined('AI_STUDIO_RATE_LIMIT') ? (int)AI_STUDIO_RATE_LIMIT : self::LIMIT;
    }

    public static function window(): int {
        return defined('AI_STUDIO_RATE_WINDOW') ? (int)AI_STUDIO_RATE_WINDOW : self::WINDOW_SECONDS;
    }

    public static function key(int $uid, string $ip): string {
        return 'ai_studio_' . $uid . '_' . sha1($ip);
    }

    public static function check(int $uid, string $ip): array {
        $limit = self::limit();
        $window = self::window();
        $now = time();
        $rlKey = self::key($uid, $ip);
        $sessionKey = "ratelimit_ai_studio_{$rlKey}";
        $rlData = $_SESSION[$sessionKey] ?? ['count' => 0, 'timestamp' => $now];
        if ($now - (int)($rlData['timestamp'] ?? $now) > $window) $rlData = ['count' => 0, 'timestamp' => $now];
        $rlDir = BASE_PATH . '/storage/ratelimit_ai_studio';
        if (!is_dir($rlDir)) @mkdir($rlDir, 0750, true);
        $rlFile = $rlDir . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $rlKey) . '.json';
        $fileData = ['count' => 0, 'timestamp' => $now];
        $fp = @fopen($rlFile, 'c+');
        if ($fp) {
            @flock($fp, LOCK_EX);
            $raw = stream_get_contents($fp);
            if ($raw !== false && $raw !== '') {
                $j = json_decode($raw, true);
                if (is_array($j) && isset($j['count'], $j['timestamp'])) $fileData = $j;
            }
            if ($now - (int)$fileData['timestamp'] > $window) $fileData = ['count' => 0, 'timestamp' => $now];
            $effective = max((int)$rlData['count'], (int)$fileData['count']) + 1;
            if ($effective > $limit) {
                $retryAfter = $window - ($now - (int)$fileData['timestamp']);
                if ($retryAfter < 1) $retryAfter = 60;
                @flock($fp, LOCK_UN); @fclose($fp);
                return ['allowed' => false, 'retryAfter' => $retryAfter];
            }
            $fileData['count'] = $effective;
            $fileData['timestamp'] = $rlData['timestamp'] = $fileData['timestamp'];
            ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($fileData));
            @flock($fp, LOCK_UN); @fclose($fp);
            $rlData['count'] = $effective;
        } else {
            $rlData['count']++;
            if ($rlData['count'] > $limit) {
                $retryAfter = $window - ($now - (int)$rlData['timestamp']);
                return ['allowed' => false, 'retryAfter' => max(1, $retryAfter)];
            }
        }
        $_SESSION[$sessionKey] = $rlData;
        return ['allowed' => true, 'retryAfter' => 0];
    }
}
