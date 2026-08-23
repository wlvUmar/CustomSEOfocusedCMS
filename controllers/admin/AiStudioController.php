<?php
// path: ./controllers/admin/AiStudioController.php
// AI Studio: an agent loop over the CMS admin via tools.
//   GET  /admin/ai-studio      → index()  — the chat window
//   POST /admin/ai-studio/run  → run()    — one agent turn (SSE stream)

require_once BASE_PATH . '/models/OpenRouter.php';
require_once BASE_PATH . '/models/ai/AiToolRegistry.php';

class AiStudioController extends Controller {

    /** Hard cap on model↔tool round trips per HTTP request. */
    private const MAX_TOOL_TURNS = 8;
    /** History depth kept for context (client sends the transcript each turn). */
    private const MAX_HISTORY_TURNS = 12;
    /** JSON-lines operational log for this feature (separate from php_errors.log). */
    private const LOG_FILE = BASE_PATH . '/logs/ai-studio.log';

    public function index() {
        $this->requireAuth();
        require_once BASE_PATH . '/models/GscClient.php';
        $gsc = GscClient::getStatus();
        $this->view('admin/ai-studio/index', [
            'pageName' => 'ai-studio',
            'models' => OpenRouter::MODELS,
            'gscStatus' => $gsc,
        ]);
    }

    public function run() {
        $this->requireAuth();

        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->json(['success' => false, 'message' => 'CSRF token validation failed'], 400);
        }

        $model = (string)($_POST['model'] ?? '');
        $message = trim((string)($_POST['message'] ?? ''));
        $history = $this->sanitizeHistory($_POST['history'] ?? '[]');
        $approved = $this->sanitizeApproved($_POST['approved'] ?? '[]');
        $pending = $this->sanitizePending($_POST['pending'] ?? '[]');

        $startedAt = microtime(true);
        $turnsUsed = 0;

        $this->startStream();

        if ($message === '') {
            $this->sse('error', ['message' => 'Message cannot be empty']);
            $this->sse('done', ['status' => 'error']);
            return;
        }

        $this->logAi('run_start', [
            'model' => $model,
            'message_len' => mb_strlen($message),
            'history_turns' => count($history),
            'approved_count' => count($approved),
            'pending_count' => count($pending),
        ]);

        $this->sse('activity', ['text' => 'Starting…']);

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->buildSystemPrompt()]],
            $history,
            $pending,
            [['role' => 'user', 'content' => $message]]
        );

        $finalText = '';
        $usageTotal = ['prompt' => 0, 'completion' => 0, 'total' => 0, 'cost' => 0.0];

        try {
            for ($turn = 1; $turn <= self::MAX_TOOL_TURNS; $turn++) {
                if (connection_aborted()) {
                    $this->logAi('run_end', [
                        'status' => 'aborted',
                        'turns' => $turnsUsed,
                        'duration_ms' => $this->elapsedMs($startedAt),
                    ]);
                    // Try to tell the client we stopped — if the TCP is already dead the bytes are just dropped.
                    try { $this->sse('done', ['status' => 'aborted', 'text' => $finalText]); } catch (Throwable $e) {}
                    return; // client pressed Stop — don't spend more tokens
                }

                $turnsUsed++;
                $this->sse('turn', ['number' => $turn, 'max' => self::MAX_TOOL_TURNS]);
                $this->sse('activity', ['text' => 'Thinking… turn ' . $turn . '/' . self::MAX_TOOL_TURNS]);

                $modelStart = microtime(true);
                $response = OpenRouter::chatWithTools($messages, $model, AiToolRegistry::definitions(), 0.5, 8192);
                $modelMs = (int)round((microtime(true) - $modelStart) * 1000);

                $usage = $response['usage'] ?? null;
                if (is_array($usage)) {
                    $uPrompt = (int)($usage['prompt_tokens'] ?? 0);
                    $uCompletion = (int)($usage['completion_tokens'] ?? 0);
                    $uTotal = (int)($usage['total_tokens'] ?? ($uPrompt + $uCompletion));
                    $uCost = (float)($usage['cost'] ?? 0);
                    $usageTotal['prompt'] += $uPrompt;
                    $usageTotal['completion'] += $uCompletion;
                    $usageTotal['total'] += $uTotal;
                    $usageTotal['cost'] += $uCost;
                    $this->sse('usage', [
                        'turn' => $turn,
                        'prompt' => $usageTotal['prompt'],
                        'completion' => $usageTotal['completion'],
                        'total' => $usageTotal['total'],
                        'cost' => $usageTotal['cost'],
                    ]);
                }

                $this->logAi('model_turn', [
                    'turn' => $turn,
                    'model' => $model,
                    'prompt_tokens' => (int)($usage['prompt_tokens'] ?? 0),
                    'completion_tokens' => (int)($usage['completion_tokens'] ?? 0),
                    'total_tokens' => (int)($usage['total_tokens'] ?? 0),
                    'cost' => (float)($usage['cost'] ?? 0),
                    'finish_reason' => $response['finish_reason'] ?? null,
                    'tool_calls' => count($response['tool_calls'] ?? []),
                    'duration_ms' => $modelMs,
                ]);

                if ($response['content'] !== '') {
                    $finalText = $response['content'];
                    $this->sse('narrate', ['text' => $response['content']]);
                }

                $toolCalls = $response['tool_calls'];
                if (empty($toolCalls)) {
                    break; // model answered without calling tools — turn complete
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => $response['content'],
                    'tool_calls' => $toolCalls,
                ];

                $haltForApproval = false;

                foreach ($toolCalls as $tc) {
                    $name = $tc['function']['name'] ?? '';
                    $rawArgs = $tc['function']['arguments'] ?? '{}';
                    $args = json_decode((string)$rawArgs, true);
                    if (!is_array($args)) {
                        $args = [];
                    }

                    $this->sse('activity', ['text' => 'Running ' . $name . '…']);

                    $toolStart = microtime(true);
                    $out = AiToolRegistry::execute($name, $args, $approved);
                    $toolMs = (int)round((microtime(true) - $toolStart) * 1000);
                    $toolMsgId = $tc['id'] ?? $out['call_id'] ?? $name;

                    $this->logAi('tool_call', [
                        'name' => $name,
                        'call_id' => $out['call_id'] ?? $toolMsgId,
                        'type' => $out['type'],
                        'args' => mb_substr((string)json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 500),
                        'duration_ms' => $toolMs,
                    ]);

                    if ($out['type'] === 'approval') {
                        $haltForApproval = true;
                        // Carry the exact interrupted call into the follow-up run
                        // (Approve/Deny), so the model re-issues the same
                        // arguments — and thus the same deterministic call_id —
                        // instead of re-deriving them from the plan text.
                        $pendingPair = [
                            [
                                'role' => 'assistant',
                                'content' => $response['content'],
                                'tool_calls' => [[
                                    'id' => $toolMsgId,
                                    'type' => 'function',
                                    'function' => [
                                        'name' => $name,
                                        'arguments' => (string)$rawArgs,
                                    ],
                                ]],
                            ],
                            [
                                'role' => 'tool',
                                'tool_call_id' => $toolMsgId,
                                'content' => json_encode([
                                    'status' => 'approval_required',
                                    'call_id' => $out['call_id'],
                                    'plan' => $out['plan'],
                                    'note' => 'Re-issue the exact same tool call (same name and arguments) to execute this change.',
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            ],
                        ];
                        $this->sse('approval_required', [
                            'call_id' => $out['call_id'],
                            'tool' => $name,
                            'plan' => $out['plan'],
                            'reason' => $out['reason'],
                            'pending' => $pendingPair,
                        ]);
                        $this->logAi('approval_requested', [
                            'call_id' => $out['call_id'],
                            'tool' => $name,
                            'plan' => mb_substr((string)($out['plan'] ?? ''), 0, 300),
                        ]);
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolMsgId,
                            'content' => json_encode([
                                'status' => 'approval_required',
                                'call_id' => $out['call_id'],
                                'plan' => $out['plan'],
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ];
                        break; // stop the batch here — nothing runs after a guard
                    }

                    if ($out['type'] === 'error') {
                        $this->sse('tool_result', [
                            'tool' => $name,
                            'ok' => false,
                            'message' => $out['message'],
                            'summary' => 'Error: ' . $out['message'],
                        ]);
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolMsgId,
                            'content' => json_encode(['error' => $out['message']], JSON_UNESCAPED_UNICODE),
                        ];
                        continue;
                    }

                    $this->sse('tool_result', [
                        'tool' => $name,
                        'ok' => true,
                        'summary' => $this->summarizeResult($name, $out['result']),
                    ]);

                    if ($name === 'render_preview' && isset($out['result']['html'])) {
                        $this->sse('activity', ['text' => 'Rendering preview…']);
                        $this->sse('preview', ['html' => $out['result']['html']]);
                    }

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolMsgId,
                        'content' => json_encode($out['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ];
                }

                if ($haltForApproval) {
                    $this->logAi('run_end', [
                        'status' => 'awaiting_approval',
                        'turns' => $turnsUsed,
                        'duration_ms' => $this->elapsedMs($startedAt),
                    ]);
                    $this->sse('done', ['status' => 'awaiting_approval', 'text' => $finalText]);
                    return;
                }
            }

            $this->sse('done', ['status' => 'complete', 'text' => $finalText]);
            $this->logAi('run_end', [
                'status' => 'complete',
                'turns' => $turnsUsed,
                'prompt_tokens' => $usageTotal['prompt'],
                'completion_tokens' => $usageTotal['completion'],
                'total_tokens' => $usageTotal['total'],
                'cost' => $usageTotal['cost'],
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);
        } catch (Exception $e) {
            $this->logAi('run_error', [
                'message' => $e->getMessage(),
                'at' => $e->getFile() . ':' . $e->getLine(),
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);
            $this->sse('error', ['message' => $e->getMessage()]);
            $this->sse('done', ['status' => 'error']);
        }
    }

    // ------------------------------------------------------------------
    // SSE plumbing
    // ------------------------------------------------------------------

    /**
     * Switch the response into an SSE stream. The front controller starts an
     * output buffer at the top of index.php, so drain it here (headers are
     * set first so they ship with the first flush). If the host still buffers
     * (nginx gzip, mod_deflate), the client-side parser simply receives all
     * events at once — degraded but not broken.
     */
    private function startStream() {
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');
        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', '0');
        }
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        echo "retry: 2000\n\n";
        $this->flushAll();
    }

    private function sse(string $event, array $data = []) {
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        $this->flushAll();
    }

    private function flushAll() {
        while (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    // ------------------------------------------------------------------
    // Operational logging (logs/ai-studio.log, one JSON line per event)
    // ------------------------------------------------------------------

    private function elapsedMs(float $since): int {
        return (int)round((microtime(true) - $since) * 1000);
    }

    private function logAi(string $event, array $ctx = []): void {
        $line = json_encode([
            'ts' => date('Y-m-d H:i:s'),
            'event' => $event,
            'user' => $_SESSION['username'] ?? 'admin',
            'user_id' => $_SESSION['user_id'] ?? null,
        ] + $ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line !== false) {
            @error_log($line . "\n", 3, self::LOG_FILE);
        }
    }

    // ------------------------------------------------------------------
    // GSC CSV upload
    // ------------------------------------------------------------------

    /**
     * POST /admin/ai-studio/upload-gsc — upload a GSC Performance CSV.
     * Accepts the raw export (Pages, Queries, or Pages+Queries pivot).
     * Flexible header detection: synonyms for page/query/clicks/impressions/ctr/position/date.
     * Page URLs like https://site/uz/foo are reduced to slug "foo".
     */
    public function uploadGsc() {
        $this->requireAuth();
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->json(['success' => false, 'message' => 'CSRF token validation failed'], 400);
        }
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'message' => 'No file uploaded or upload error'], 400);
        }
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $this->json(['success' => false, 'message' => 'Only .csv files are allowed'], 400);
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            $this->json(['success' => false, 'message' => 'File too large (max 10 MB)'], 400);
        }

        $db = Database::getInstance();
        // Ensure table exists even before migration was applied.
        $db->query("CREATE TABLE IF NOT EXISTS `gsc_data` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `page_slug` varchar(255) NOT NULL,
          `query` varchar(500) NOT NULL,
          `clicks` int(11) UNSIGNED NOT NULL DEFAULT 0,
          `impressions` int(11) UNSIGNED NOT NULL DEFAULT 0,
          `ctr` decimal(5,2) NOT NULL DEFAULT 0.00,
          `position` decimal(10,2) NOT NULL DEFAULT 0.00,
          `date` date DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `uniq_page_query_date` (`page_slug`,`query`(191),`date`),
          KEY `idx_page_slug` (`page_slug`),
          KEY `idx_query` (`query`(191)),
          KEY `idx_date` (`date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $tmp = $file['tmp_name'];
        // Detect encoding: GSC exports can be UTF-8 with BOM or Windows-1251 (RU).
        $raw = @file_get_contents($tmp);
        if ($raw !== false && substr($raw, 0, 3) === "\xEF\xBB\xBF") $raw = substr($raw, 3);
        if ($raw !== false && $raw !== '') {
            // Heuristic: if valid UTF-8, keep; otherwise assume windows-1251 (common for RU exports).
            if (!mb_check_encoding($raw, 'UTF-8')) {
                $converted = @iconv('windows-1251', 'UTF-8//IGNORE', $raw);
                if ($converted !== false && $converted !== '') {
                    file_put_contents($tmp, $converted);
                    $raw = $converted;
                }
            } else {
                // Still strip BOM if present in sample.
                if ($raw !== @file_get_contents($tmp)) { /* already stripped */ }
            }
        }

        $handle = fopen($tmp, 'r');
        if (!$handle) {
            $this->json(['success' => false, 'message' => 'Cannot read uploaded file'], 400);
        }

        // Sniff delimiter: GSC exports use comma, but some locales export semicolon.
        $firstLine = fgets($handle);
        rewind($handle);
        $delim = ',';
        if ($firstLine !== false) {
            $commas = substr_count($firstLine, ',');
            $semis = substr_count($firstLine, ';');
            $tabs = substr_count($firstLine, "\t");
            if ($semis > $commas && $semis > $tabs) $delim = ';';
            elseif ($tabs > $commas && $tabs > $semis) $delim = "\t";
        }

        $header = fgetcsv($handle, 0, $delim);
        if ($header === false || count(array_filter($header, fn($v) => trim((string)$v) !== '')) === 0) {
            fclose($handle);
            $this->json(['success' => false, 'message' => 'Empty or unreadable CSV'], 400);
        }
        // Normalize header: lowercase, trim, strip BOM and quotes.
        $norm = array_map(function($h) {
            $h = trim((string)$h);
            $h = trim($h, "\"' \t");
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
            return mb_strtolower($h, 'UTF-8');
        }, $header);

        // Header synonym map.
        $indexFor = function(array $needles) use ($norm): ?int {
            foreach ($needles as $needle) {
                foreach ($norm as $i => $h) {
                    if ($h === $needle || str_contains($h, $needle)) return $i;
                }
            }
            return null;
        };

        $idxPage = $indexFor(['page', 'url', 'address', 'страница', 'адрес']);
        $idxQuery = $indexFor(['query', 'keyword', 'запрос', 'ключ']);
        $idxClicks = $indexFor(['clicks', 'клики']);
        $idxImpr = $indexFor(['impressions', 'показы']);
        $idxCtr = $indexFor(['ctr']);
        $idxPos = $indexFor(['position', 'позиция', 'avg. position', 'average position']);
        $idxDate = $indexFor(['date', 'дата']);

        // Fallback: if no header matched, assume header-less with common GSC order:
        // Page, Query, Clicks, Impressions, CTR, Position [, Date]
        $hasHeader = ($idxQuery !== null || $idxClicks !== null || $idxImpr !== null);
        if (!$hasHeader) {
            // Treat first row as data, guess columns by count.
            rewind($handle);
            $header = null;
            $norm = null;
            $colCount = count($header ?? []);
            // For 6-col without header: page, query, clicks, impr, ctr, pos
            // For 5-col: query, clicks, impr, ctr, pos (no page)
            $idxPage = null; $idxQuery = 0; $idxClicks = 1; $idxImpr = 2; $idxCtr = 3; $idxPos = 4; $idxDate = null;
            // Peek first data row to refine if it looks like page URLs.
            $peek = fgetcsv($handle, 0, $delim);
            if ($peek !== false) {
                $peekStr = implode(' ', $peek);
                if (str_contains($peekStr, 'http') || str_contains($peekStr, '/')) {
                    $idxPage = 0; $idxQuery = 1; $idxClicks = 2; $idxImpr = 3; $idxCtr = 4; $idxPos = 5;
                }
            }
            rewind($handle);
        }

        // Require at least clicks/impressions or query column.
        if ($idxClicks === null && $idxImpr === null && $idxQuery === null) {
            fclose($handle);
            $this->json(['success' => false, 'message' => 'CSV header not recognized. Expected columns like: Page, Query, Clicks, Impressions, CTR, Position (export Pages+Queries from GSC). Detected: ' . implode(', ', $header ?? [])], 400);
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $replace = isset($_POST['replace']) && $_POST['replace'] === '1';
        $defaultDate = null;
        if (!empty($_POST['date'])) {
            $d = trim((string)$_POST['date']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $defaultDate = $d;
        }

        $batch = [];
        $pdo = $db->getConnection();
        $rowNum = $hasHeader ? 1 : 0;

        // If replace, clear existing rows for the affected dates/pages before insert (inside transaction).
        if ($replace) {
            // Defer actual delete until we know which dates are in the file: collect dates first pass if not supplied.
            // Simpler: if defaultDate supplied, delete that date; otherwise truncate for a full replace.
            // We do it lazily after reading a few rows — but to keep streaming, just wipe if requested.
            // Safer: only wipe if file has a single date or defaultDate is set.
        }

        try {
            $pdo->beginTransaction();
            if ($replace) {
                if ($defaultDate !== null) {
                    $db->query("DELETE FROM gsc_data WHERE date = ?", [$defaultDate]);
                } else {
                    // Without a pinned date, replace = truncate so the new file becomes the source of truth.
                    $db->query("DELETE FROM gsc_data");
                }
            }

            while (($row = fgetcsv($handle, 0, $delim)) !== false) {
                $rowNum++;
                if ($row === [null] || count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) { $skipped++; continue; }
                if ($rowNum > 20000) { $errors[] = "Stopped after 20000 rows (file too large, truncated)."; break; }

                $get = function(?int $idx) use ($row): string {
                    if ($idx === null || !isset($row[$idx])) return '';
                    return trim((string)$row[$idx]);
                };

                $rawPage = $get($idxPage);
                $rawQuery = $get($idxQuery);
                $rawClicks = $get($idxClicks);
                $rawImpr = $get($idxImpr);
                $rawCtr = $get($idxCtr);
                $rawPos = $get($idxPos);
                $rawDate = $idxDate !== null ? $get($idxDate) : '';

                // If CSV is Queries-only export (no Page column), keep rawPage empty and use slug "all" or derive later.
                // If both page and query are empty, skip.
                if ($rawPage === '' && $rawQuery === '') { $skipped++; continue; }

                // Normalize page_slug: extract last path segment from URL or path.
                $slug = '';
                if ($rawPage !== '') {
                    $page = trim($rawPage);
                    // Strip domain if full URL.
                    if (preg_match('#https?://[^/]+/(.*)#i', $page, $m)) {
                        $page = '/' . $m[1];
                    }
                    $page = trim($page, "/ \t");
                    // Remove language prefix if present (uz/)
                    // Keep it? slugs in pages table don't include "uz/". So map "uz/foo" -> "foo" but preserve.
                    // We'll store without uz prefix to match pages.slug; the UI shows language separately.
                    $page = preg_replace('#^(ru|uz)/#i', '', $page);
                    // If page is empty after stripping (= homepage), slug = "main"
                    if ($page === '' || $page === '/') $page = 'main';
                    // Take up to first segment? Keep full path? pages uses single slug like "services" not nested.
                    // For nested like "catalog/holodilniki", keep as "catalog/holodilniki" or just last segment.
                    // Safer: keep full path without domain, but GscTools queries by exact slug, so also store last segment as fallback is noisy.
                    // Store normalized path as slug (may contain slashes); prefix matching will still work via LIKE in edge cases.
                    // For now, extract slug-ish: last segment after last slash.
                    if (str_contains($page, '/')) {
                        $parts = explode('/', $page);
                        $page = end($parts);
                    }
                    $slug = mb_strtolower($page, 'UTF-8');
                    $slug = preg_replace('/[^a-z0-9_\-]/', '-', $slug);
                    $slug = trim($slug, '-');
                    if ($slug === '') $slug = 'main';
                } else {
                    // Queries-only export: group under slug "all" or try to infer from query? Keep as "_all" so page-level aggregations still work.
                    $slug = '_all';
                }

                $query = $rawQuery !== '' ? trim($rawQuery) : $slug;
                if ($query === '') $query = $slug;
                // Truncate query to 500 chars for PK limit.
                if (mb_strlen($query) > 500) $query = mb_substr($query, 0, 500);

                // Numeric parsing: handle "1 234", "1,234", "3.42%", "8,34" (EU decimal).
                $parseInt = function(string $v): int {
                    $v = str_replace(["\xc2\xa0", ' '], '', $v);
                    $v = str_replace(',', '', $v);
                    $v = preg_replace('/[^0-9\-]/', '', $v);
                    return $v === '' || $v === '-' ? 0 : (int)$v;
                };
                $parseFloat = function(string $v): float {
                    $v = trim($v);
                    $v = rtrim($v, '%');
                    $v = str_replace(["\xc2\xa0", ' '], '', $v);
                    // EU: "8,34" -> "8.34" if no dot present
                    if (str_contains($v, ',') && !str_contains($v, '.')) $v = str_replace(',', '.', $v);
                    else $v = str_replace(',', '', $v);
                    $v = preg_replace('/[^0-9\.\-]/', '', $v);
                    return $v === '' || $v === '-' || $v === '.' ? 0.0 : (float)$v;
                };

                $clicks = $rawClicks !== '' ? $parseInt($rawClicks) : 0;
                $impr = $rawImpr !== '' ? $parseInt($rawImpr) : 0;
                $ctr = $rawCtr !== '' ? $parseFloat($rawCtr) : ($impr > 0 ? round(100.0 * $clicks / $impr, 2) : 0.0);
                // CTR in GSC is often "3.2%" -> 3.2 ; if value > 1 and no percent sign, could be 0.032 -> normalize
                if ($ctr > 0 && $ctr < 1 && $impr > 100) $ctr = round($ctr * 100, 2);
                $pos = $rawPos !== '' ? $parseFloat($rawPos) : 0.0;

                $dateVal = null;
                $dateSrc = $rawDate !== '' ? $rawDate : ($defaultDate ?? '');
                if ($dateSrc !== '') {
                    // Try Y-m-d, d.m.Y, m/d/Y
                    $ts = strtotime($dateSrc);
                    if ($ts !== false) $dateVal = date('Y-m-d', $ts);
                }

                // Skip rows with no metrics at all.
                if ($clicks === 0 && $impr === 0 && $pos === 0.0) { $skipped++; continue; }

                $batch[] = [$slug, $query, $clicks, $impr, $ctr, $pos, $dateVal];
                if (count($batch) >= 500) {
                    $res = $this->flushGscBatch($batch);
                    $imported += $res['inserted'];
                    $updated += $res['updated'];
                    $batch = [];
                }
            }
            if (!empty($batch)) {
                $res = $this->flushGscBatch($batch);
                $imported += $res['inserted'];
                $updated += $res['updated'];
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            fclose($handle);
            $this->logAi('gsc_upload_error', ['message' => $e->getMessage()]);
            $this->json(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()], 500);
        }
        fclose($handle);

        $this->logAi('gsc_upload', ['file' => $file['name'], 'imported' => $imported, 'updated' => $updated, 'skipped' => $skipped, 'replace' => $replace ? 1 : 0]);
        $this->json(['success' => true, 'imported' => $imported, 'updated' => $updated, 'skipped' => $skipped, 'total' => $imported + $updated, 'errors' => $errors, 'note' => $imported + $updated === 0 ? 'No rows imported — check CSV columns (need Page/Query/Clicks/Impressions/CTR/Position).' : null]);
    }

    private function flushGscBatch(array $rows): array {
        if (empty($rows)) return ['inserted' => 0, 'updated' => 0];
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        // Use INSERT ... ON DUPLICATE KEY UPDATE to count upserts without N round-trips.
        // clicks/impressions are replaced (not summed) so re-uploading same month is idempotent.
        $placeholders = [];
        $params = [];
        foreach ($rows as $r) {
            // r = [slug, query, clicks, impr, ctr, pos, date]
            $placeholders[] = "(?, ?, ?, ?, ?, ?, ?)";
            $params[] = $r[0]; $params[] = $r[1]; $params[] = $r[2]; $params[] = $r[3]; $params[] = $r[4]; $params[] = $r[5]; $params[] = $r[6];
        }
        $sql = "INSERT INTO gsc_data (page_slug, query, clicks, impressions, ctr, position, date) VALUES " . implode(',', $placeholders) .
               " ON DUPLICATE KEY UPDATE clicks = VALUES(clicks), impressions = VALUES(impressions), ctr = VALUES(ctr), position = VALUES(position), updated_at = NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        // rowCount for ON DUPLICATE is 1 on insert, 2 on update, 0 on no-change. Approximate split:
        $affected = $stmt->rowCount();
        // We can't perfectly split without extra query, so estimate: if replace mode most are inserts.
        // Instead, re-count by checking how many were actually new vs existing via a follow-up select is expensive.
        // Return combined as inserted; caller aggregates.
        return ['inserted' => $affected, 'updated' => 0];
    }

    // ------------------------------------------------------------------
    // Prompt + helpers
    // ------------------------------------------------------------------

    private function buildSystemPrompt(): string {
        return <<<'PROMPT'
You are the AI Studio agent for kuplyu-tashkent.uz — an appliance buyback (secondhand electronics purchase) service in Tashkent, Uzbekistan. The site is bilingual (RU/UZ). You operate the site's admin backend through the tools provided to you; you do not have a chat-only mode. Always investigate before you act.

═══ OPERATING LOOP ═══
1. Understand what's actually being asked. If it references "the pages" or "underperforming content" without specifics, use read tools (list_pages, get_underperforming_pages, search_content) to find out what that means concretely before touching anything.
2. Read before you write. Never call str_replace_field or set_field against a field you haven't read this session — content may have changed since you last saw it. Prefer slugs over numeric ids whenever you know them (get_page, get_faq, list_rotations and set_rotation all accept page_slug).
3. Prefer targeted edits (str_replace_field) over full rewrites (set_field). Full rewrites are for genuinely new pages/sections, not incremental improvement.
4. For anything destructive (set_field on an existing field, delete_faq, bulk changes across many pages): state your intent in one line, call the tool, and STOP. The system will surface an approval request to the user. Your turn ends there — do not retry the guarded tool in the same turn. Wait for the user's decision.
5. Narrate briefly as you go ("checking which pages get the least traffic", "reading content_ru for page 12") so the user can follow along like a transcript, not a black box.
6. After creating or editing any visual content (section, page content), call render_preview so it shows in the live preview pane. Iterate on the section there before applying.

═══ SEO DOCTRINE ═══
- Ranking-relevant signals: INTERNAL analytics (visits, clicks, phone calls, CTR = phone calls/visits, bot crawl frequency, internal-link clicks) AND Google Search Console data (impressions, clicks, CTR, avg position, queries). Internal analytics are always present; GSC data appears only after a CSV is uploaded in AI Studio — check with get_gsc_overview first and degrade gracefully if no rows exist.
- GSC tools: get_gsc_overview (site summary), get_page_gsc (per-page totals + top queries — call this before auditing any page), get_gsc_pages (pages ranked by impressions/clicks), get_gsc_queries / search_gsc_queries (top keywords), get_page_gsc with order_by. Use them to find: (a) high-impressions / low-CTR queries → title/meta optimization, (b) low-impressions pages → content/authority gaps, (c) avg position 8-20 → quick-win content expansion.
- When auditing a page, ALWAYS call both get_page (read content) and get_page_gsc + get_page_stats: compare what the page says vs what Search Console says it ranks for, and whether internal traffic (phone calls) correlates with search clicks.
- Optimize for search-intent match, not keyword density. Every page should answer the specific question its target query implies within the first 2-3 sentences of the relevant section — don't bury the answer.
- Check internal linking: related pages (same category/brand) should link to each other where natural. Thin, orphaned pages rank worse regardless of on-page quality.
- Respect meta length conventions (titles ~60-70 chars, descriptions ~150-160 chars).
- Preserve and extend structured data (JSON-LD) where the page has it; don't strip it.
- When asked to "improve SEO" with no specifics, use get_underperforming_pages (internal) AND get_gsc_pages / get_gsc_overview together — don't guess. If GSC is empty, say so and fall back to internal analytics.
- For bespoke analysis the fixed tools cannot answer (grouping by month/UTM/hour, comparing periods, cross-referencing pages/analytics/GSC), use run_analytics_query — a read-only SELECT over the analytics tables, gsc_data and pages.

═══ DESIGN DOCTRINE ═══
- Reuse the site's design tokens (call get_design_tokens) and existing classes: content-section, info-card, process-step, faq-item, links-tile, btn, btn-primary, section-label, condition-item. Never invent new colors or spacing scales.
- Preserve template variables exactly (see get_template_variables): {{page.title}}, {{page.slug}}, {{global.phone}}, {{global.email}}, {{global.address}}, {{global.working_hours}}, {{global.site_name}}, {{date.year}}, {{date.month}}, {{date.month_name}}, {{date.day}}, {{faqs}}. Never invent new ones without the user's explicit confirmation.
- If asked for "a variation" or "make it less boring", propose 2-3 genuinely different layouts (not just recolors) and render each via render_preview before applying one.
- Avoid repeating the identical section shell twice on one page — vary layout rhythm (image-left vs image-right, card grid vs timeline, etc).
- Mobile-first: sections must hold up at ~375px width. Don't rely on hover-only affordances.
- Keep logical blocks separated with "<!-- Section Name -->" comments so future edits stay targetable.

═══ HARD RULES ═══
- Only touch data reachable through your tools. Never modify anything outside them (header/footer templates, users, rotation internals beyond the provided tools).
- Keep RU fields in Russian and UZ fields in Uzbek — never mix or auto-translate one into the other unless explicitly asked.
- If a tool call fails or returns unexpected data, say so plainly and adjust — don't silently retry the same failing call more than once.
- Finish with a short summary of what you changed or propose, and remind the user if anything still awaits their approval.
PROMPT;
    }

    private function sanitizeHistory($history): array {
        $decoded = json_decode((string)$history, true);
        $messages = [];
        if (is_array($decoded)) {
            foreach ($decoded as $turn) {
                if (!is_array($turn)) continue;
                $role = in_array($turn['role'] ?? '', ['user', 'assistant'], true) ? $turn['role'] : null;
                $content = (string)($turn['content'] ?? '');
                if ($role && $content !== '') {
                    $messages[] = ['role' => $role, 'content' => $content];
                }
            }
            if (count($messages) > self::MAX_HISTORY_TURNS * 2) {
                $messages = array_slice($messages, -self::MAX_HISTORY_TURNS * 2);
            }
        }
        return $messages;
    }

    private function sanitizeApproved($approved): array {
        $decoded = json_decode((string)$approved, true);
        if (!is_array($decoded)) return [];
        $out = [];
        foreach ($decoded as $id) {
            if (is_string($id) && preg_match('/^[a-f0-9]{40}$/', $id)) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * Validate the interrupted tool context a follow-up run (Approve/Deny)
     * sends back: an assistant message carrying exactly one tool_call,
     * immediately followed by its tool result. Strictly data — never
     * auto-executed; it just gives the model the exact call to re-issue.
     */
    private function sanitizePending($pending): array {
        $decoded = json_decode((string)$pending, true);
        if (!is_array($decoded) || count($decoded) < 2) return [];
        $out = [];

        $assistant = $decoded[0] ?? null;
        if (is_array($assistant) && ($assistant['role'] ?? '') === 'assistant') {
            $toolCalls = $assistant['tool_calls'] ?? null;
            if (is_array($toolCalls) && count($toolCalls) === 1) {
                $tc = $toolCalls[0];
                $fn = is_array($tc) ? ($tc['function'] ?? null) : null;
                if (is_array($tc) && is_array($fn)) {
                    $id = (string)($tc['id'] ?? '');
                    $name = (string)($fn['name'] ?? '');
                    $arguments = (string)($fn['arguments'] ?? '{}');
                    if ($id !== '' && $name !== '' && strlen($arguments) <= 16384
                        && json_decode($arguments) !== null) {
                        $out[] = [
                            'role' => 'assistant',
                            'content' => (string)($assistant['content'] ?? ''),
                            'tool_calls' => [[
                                'id' => substr($id, 0, 64),
                                'type' => 'function',
                                'function' => [
                                    'name' => substr($name, 0, 64),
                                    'arguments' => $arguments,
                                ],
                            ]],
                        ];
                    }
                }
            }
        }

        $tool = $decoded[1] ?? null;
        if (is_array($tool) && ($tool['role'] ?? '') === 'tool' && $out !== []) {
            $callId = (string)($tool['tool_call_id'] ?? '');
            $content = (string)($tool['content'] ?? '');
            if ($callId !== '' && $content !== '' && strlen($content) <= 16384
                && $callId === ($out[0]['tool_calls'][0]['id'] ?? '')) {
                $out[] = [
                    'role' => 'tool',
                    'tool_call_id' => substr($callId, 0, 64),
                    'content' => $content,
                ];
            } else {
                return [];
            }
        } else {
            return [];
        }

        return $out;
    }

    /** Human-readable one-liner for the transcript; keeps the feed tidy. */
    private function summarizeResult(string $tool, $result): string {
        if (is_string($result)) {
            return mb_substr($result, 0, 300);
        }
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return 'Tool returned data that could not be serialized';
        }
        return mb_substr($json, 0, 300);
    }
}
