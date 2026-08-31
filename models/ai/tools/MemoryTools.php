<?php
// path: ./models/ai/tools/MemoryTools.php
// Memory + debug tools for AI Studio cross-session context and troubleshooting.

class MemoryTools {

    private const MAX_KEYS = 20;
    private const MAX_VALUE_CHARS = 4000;
    private const LOG_MAX_LINES = 20;
    private const LOG_MAX_CHARS_PER_LINE = 500;

    public static function definitions(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'store_context',
                    'description' => 'Persist a small key-value note for this session (cross-session DB). Use to remember decisions, page ids, slugs, or user preferences across turns and reloads. Keys: ^[a-z_][a-z0-9_]{1,40}$, values ≤4000 chars, ≤20 keys total. Never auto-injected — you must call get_context/list_context to recall.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string', 'description' => 'Key name, e.g. "target_page" or "user_goal".'],
                            'value' => ['type' => 'string', 'description' => 'Value to store (max 4000 chars).'],
                        ],
                        'required' => ['key', 'value'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_context',
                    'description' => 'Fetch one persisted context value by key. Returns null if not found with hint to call list_context.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string', 'description' => 'Key name.'],
                        ],
                        'required' => ['key'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_context',
                    'description' => 'List all persisted context keys with an 80-char preview of each value (not full values). Use to discover what is remembered.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_tool_logs',
                    'description' => 'Read the last N lines of the AI Studio operational log (logs/ai-studio.log) and php error log. Useful to debug tool failures, truncations, or cost. Read-only, capped at 20 lines × 500 chars.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => ['type' => 'integer', 'description' => 'Max lines to return (default 20, max 20).'],
                            'filter_tool' => ['type' => 'string', 'description' => 'Optional tool name substring to filter by.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        switch ($name) {
            case 'store_context': return self::storeContext($args);
            case 'get_context': return self::getContext($args);
            case 'list_context': return self::listContext($args);
            case 'get_tool_logs': return self::getToolLogs($args);
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    private static function ensureSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!isset($_SESSION['ai_context']) || !is_array($_SESSION['ai_context'])) {
            $_SESSION['ai_context'] = [];
        }
    }

    private static function dbContextLoad(): array {
        // Try DB latest session for this user if ai_sessions exists
        if (!isset($_SESSION['user_id'])) return [];
        try {
            $db = Database::getInstance();
            $row = $db->fetchOne("SELECT context FROM ai_sessions WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1", [(int)$_SESSION['user_id']]);
            if ($row && isset($row['context']) && $row['context'] !== null) {
                $decoded = is_string($row['context']) ? json_decode($row['context'], true) : $row['context'];
                if (is_array($decoded)) return $decoded;
            }
        } catch (Throwable $e) {
            // table may not exist yet
        }
        return [];
    }

    private static function dbContextPersist(array $context): void {
        if (!isset($_SESSION['user_id'])) return;
        // Update latest session or session_id if available
        try {
            $db = Database::getInstance();
            $sessionId = $_SESSION['ai_session_id'] ?? null;
            if ($sessionId) {
                $exists = $db->fetchOne("SELECT id FROM ai_sessions WHERE id = ? AND user_id = ?", [$sessionId, (int)$_SESSION['user_id']]);
                if ($exists) {
                    $db->query("UPDATE ai_sessions SET context = ?, updated_at = NOW() WHERE id = ?", [json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $sessionId]);
                    return;
                }
            }
            // Fallback: update latest row for user_id
            $row = $db->fetchOne("SELECT id FROM ai_sessions WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1", [(int)$_SESSION['user_id']]);
            if ($row) {
                $db->query("UPDATE ai_sessions SET context = ? WHERE id = ?", [json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $row['id']]);
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    private static function storeContext(array $args): array {
        self::ensureSession();
        $key = trim((string)($args['key'] ?? ''));
        $value = (string)($args['value'] ?? '');
        if ($key === '' || $value === '') throw new InvalidArgumentException('key and value are required');
        if (!preg_match('/^[a-z_][a-z0-9_]{1,40}$/', $key)) {
            throw new InvalidArgumentException('Invalid key "' . $key . '" — must match ^[a-z_][a-z0-9_]{1,40}$, e.g. "target_page"');
        }
        if (mb_strlen($value) > self::MAX_VALUE_CHARS) {
            throw new InvalidArgumentException('Value too long (' . mb_strlen($value) . ' chars, max ' . self::MAX_VALUE_CHARS . ') — trim it.');
        }
        // Load from DB if session empty
        if (empty($_SESSION['ai_context'])) {
            $dbCtx = self::dbContextLoad();
            if (!empty($dbCtx)) $_SESSION['ai_context'] = $dbCtx;
        }
        if (count($_SESSION['ai_context']) >= self::MAX_KEYS && !array_key_exists($key, $_SESSION['ai_context'])) {
            throw new InvalidArgumentException('Context limit reached (' . self::MAX_KEYS . ' keys) — delete or overwrite an existing key. Call list_context to see keys.');
        }
        $_SESSION['ai_context'][$key] = $value;
        self::dbContextPersist($_SESSION['ai_context']);
        return ['ok'=>true,'key'=>$key,'chars'=>mb_strlen($value),'total_keys'=>count($_SESSION['ai_context']),'note'=>'Stored. It persists across reloads via DB.'];
    }

    private static function getContext(array $args): array {
        self::ensureSession();
        $key = trim((string)($args['key'] ?? ''));
        if ($key === '') throw new InvalidArgumentException('key is required');
        // Prefer session, fallback to DB
        if (isset($_SESSION['ai_context'][$key])) {
            return ['key'=>$key,'value'=>$_SESSION['ai_context'][$key],'found'=>true];
        }
        $dbCtx = self::dbContextLoad();
        if (isset($dbCtx[$key])) {
            $_SESSION['ai_context'] = $dbCtx;
            return ['key'=>$key,'value'=>$dbCtx[$key],'found'=>true,'source'=>'db'];
        }
        return ['key'=>$key,'value'=>null,'found'=>false,'hint'=>'Not found — call list_context to see available keys.'];
    }

    private static function listContext(array $args): array {
        self::ensureSession();
        $ctx = $_SESSION['ai_context'] ?? [];
        if (empty($ctx)) {
            $dbCtx = self::dbContextLoad();
            if (!empty($dbCtx)) {
                $_SESSION['ai_context'] = $dbCtx;
                $ctx = $dbCtx;
            }
        }
        $out = [];
        foreach ($ctx as $k => $v) {
            $out[] = ['key'=>$k,'preview'=>mb_substr((string)$v,0,80),'chars'=>mb_strlen((string)$v)];
        }
        return ['keys'=>$out,'count'=>count($out),'total_keys'=>count($ctx),'max_keys'=>self::MAX_KEYS];
    }

    private static function getToolLogs(array $args): array {
        $limit = isset($args['limit']) ? max(1, min(self::LOG_MAX_LINES, (int)$args['limit'])) : 20;
        $filter = trim((string)($args['filter_tool'] ?? ''));
        $files = [
            BASE_PATH . '/logs/ai-studio.log',
            BASE_PATH . '/logs/php_errors.log',
        ];
        $lines = [];
        foreach ($files as $path) {
            if (!is_file($path)) continue;
            // Efficient tail: read last 32KB instead of full 10MB file to avoid memory blow-up
            $content = self::tailFile($path, 32 * 1024);
            if ($content === '' ) continue;
            // Redact secrets: Authorization + api keys / secrets / tokens / passwords + GSC/OpenRouter keys
            $content = preg_replace('/Authorization:\s*[^\n]+/i', 'Authorization: [redacted]', $content);
            $content = preg_replace('/((?:api[_-]?key|secret|password|token|OPENROUTER_API_KEY|GSC_CLIENT_SECRET|GSC_ENCRYPTION_KEY|BOT_API_SECRET)\s*[:=]\s*)([^\s\n"\'`,;]+)/i', '$1[redacted]', $content);
            $content = preg_replace('/(sk-[a-zA-Z0-9_\-]{10,})/', '[redacted-sk]', $content);
            $content = preg_replace('/(Bearer\s+[a-zA-Z0-9_\-\.]+)/i', 'Bearer [redacted]', $content);
            $all = explode("\n", $content);
            $all = array_filter($all, fn($l) => trim($l) !== '');
            // Take last N
            $slice = array_slice($all, -$limit);
            foreach ($slice as $line) {
                if ($filter !== '' && stripos($line, $filter) === false) continue;
                // Try parse JSON line from ai-studio.log
                $parsed = json_decode($line, true);
                if (is_array($parsed)) {
                    $msg = $line;
                    if (mb_strlen($msg) > self::LOG_MAX_CHARS_PER_LINE) $msg = mb_substr($msg,0,self::LOG_MAX_CHARS_PER_LINE).'…';
                    $lines[] = ['file'=>basename($path),'raw'=>$msg,'event'=>$parsed['event']??'','tool'=>$parsed['tool']?? $parsed['name']??'','ts'=>$parsed['ts']??''];
                } else {
                    $msg = mb_strlen($line) > self::LOG_MAX_CHARS_PER_LINE ? mb_substr($line,0,self::LOG_MAX_CHARS_PER_LINE).'…' : $line;
                    $lines[] = ['file'=>basename($path),'raw'=>$msg];
                }
                if (count($lines) >= $limit) break 2;
            }
        }
        // Cap total chars
        $totalChars = 0;
        $capped = [];
        foreach ($lines as $l) {
            $capped[] = $l;
            $totalChars += mb_strlen($l['raw']);
            if ($totalChars > 10000) break;
        }
        return ['logs'=>$capped,'count'=>count($capped),'limit'=>$limit,'filter'=>$filter ?: null];
    }

    private static function tailFile(string $path, int $maxBytes): string {
        $size = @filesize($path);
        if ($size === false || $size <= $maxBytes) {
            $c = @file_get_contents($path);
            return $c === false ? '' : $c;
        }
        $fh = @fopen($path, 'rb');
        if (!$fh) return '';
        @fseek($fh, -$maxBytes, SEEK_END);
        $data = @stream_get_contents($fh);
        @fclose($fh);
        if ($data === false) return '';
        // Trim to first full line to avoid half-line
        $pos = strpos($data, "\n");
        if ($pos !== false) $data = substr($data, $pos + 1);
        return $data;
    }
}
