<?php
// path: ./services/AiLogger.php
// AI Studio operational logging: JSON-lines logs with rotation + secret redaction.
// Extracted from AiStudioController — no behavior change.

class AiLogger {

    public const LOG_FILE = BASE_PATH . '/logs/ai-studio.log';
    public const DEBUG_LOG_FILE = BASE_PATH . '/logs/ai-studio-debug.log';

    public static function logAi(string $event, array $ctx = []): void {
        $sid = $_SESSION['ai_session_id'] ?? ($_POST['session_id'] ?? null);
        $ctx = self::redactForLog($ctx);
        $line = json_encode([
            'ts' => date('Y-m-d H:i:s'),
            'event' => $event,
            'user' => $_SESSION['username'] ?? 'admin',
            'user_id' => $_SESSION['user_id'] ?? null,
            'session_id' => $sid ? substr((string)$sid, 0, 12) : null,
            'model' => $ctx['model'] ?? null,
        ] + $ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) return;
        // Rotate if >10MB with lock to prevent race (07#9)
        if (is_file(self::LOG_FILE) && @filesize(self::LOG_FILE) > 10 * 1024 * 1024) {
            $lock = @fopen(self::LOG_FILE, 'a');
            if ($lock && @flock($lock, LOCK_EX | LOCK_NB)) {
                // Re-check size after acquiring lock
                clearstatcache(true, self::LOG_FILE);
                if (@filesize(self::LOG_FILE) > 10 * 1024 * 1024) {
                    @flock($lock, LOCK_UN);
                    @fclose($lock);
                    @rename(self::LOG_FILE, self::LOG_FILE . '.' . date('Y-m-d_His'));
                } else {
                    @flock($lock, LOCK_UN);
                    @fclose($lock);
                }
            } elseif ($lock) {
                @fclose($lock);
            }
        }
        @error_log($line . "\n", 3, self::LOG_FILE);
    }

    public static function logDebug(string $event, array $payload = []): void {
        $sid = $_SESSION['ai_session_id'] ?? ($_POST['session_id'] ?? null);
        $line = json_encode([
            'ts' => date('Y-m-d H:i:s.v'),
            'event' => $event,
            'session_id' => $sid ? substr((string)$sid, 0, 16) : null,
        ] + $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line === false) $line = json_encode(['ts'=>date('Y-m-d H:i:s'), 'event'=>$event, 'error'=>'json_encode failed']);
        if (is_file(self::DEBUG_LOG_FILE) && @filesize(self::DEBUG_LOG_FILE) > 20 * 1024 * 1024) {
            $lock = @fopen(self::DEBUG_LOG_FILE, 'a');
            if ($lock && @flock($lock, LOCK_EX | LOCK_NB)) {
                clearstatcache(true, self::DEBUG_LOG_FILE);
                if (@filesize(self::DEBUG_LOG_FILE) > 20 * 1024 * 1024) {
                    @flock($lock, LOCK_UN);
                    @fclose($lock);
                    @rename(self::DEBUG_LOG_FILE, self::DEBUG_LOG_FILE . '.' . date('Y-m-d_His'));
                } else {
                    @flock($lock, LOCK_UN);
                    @fclose($lock);
                }
            } elseif ($lock) {
                @fclose($lock);
            }
        }
        @error_log($line . "\n", 3, self::DEBUG_LOG_FILE);
    }

    public static function redactForLog(array $ctx): array {
        $out = [];
        foreach ($ctx as $k => $v) {
            if (is_string($v)) {
                $v = preg_replace('/(Authorization:\s*)[^\n]+/i', '$1[redacted]', $v) ?? $v;
                $v = preg_replace('/((?:api[_-]?key|secret|password|token|OPENCODE_API_KEY|OPENROUTER_API_KEY|GSC_CLIENT_SECRET|GSC_ENCRYPTION_KEY|BOT_API_SECRET)\s*[:=]\s*)([^\s\n"\'`,;]+)/i', '$1[redacted]', $v) ?? $v;
                $v = preg_replace('/(sk-[a-zA-Z0-9_\-]{10,})/', '[redacted-sk]', $v) ?? $v;
                $v = preg_replace('/(Bearer\s+[a-zA-Z0-9_\-\.]+)/i', 'Bearer [redacted]', $v) ?? $v;
            } elseif (is_array($v)) {
                $v = self::redactForLog($v);
            }
            $out[$k] = $v;
        }
        return $out;
    }
}
