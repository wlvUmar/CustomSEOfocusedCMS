<?php
// path: ./controllers/admin/AiStudioController.php
// AI Studio: an agent loop over the CMS admin via tools.
//   GET  /admin/ai-studio      → index()  — the chat window
//   POST /admin/ai-studio/run  → run()    — one agent turn (SSE stream)

require_once BASE_PATH . '/models/OpenRouter.php';
require_once BASE_PATH . '/models/ai/AiToolRegistry.php';

class AiStudioController extends Controller {

    /** Hard cap on model↔tool round trips per HTTP request. Effectively uncapped (50) per user request to remove limit — raised 10→50. */
    private const MAX_TOOL_TURNS = 50;
    /** History depth kept for context (client sends the transcript each turn). */
    private const MAX_HISTORY_TURNS = 12;
    /** JSON-lines operational log for this feature (separate from php_errors.log). */
    private const LOG_FILE = BASE_PATH . '/logs/ai-studio.log';
    /** Verbose debug dump: raw prompts, messages, tool results (opt-in via ?debug=1 or header, always for errors). */
    private const DEBUG_LOG_FILE = BASE_PATH . '/logs/ai-studio-debug.log';

    public function index() {
        $this->requireAuth();
        require_once BASE_PATH . '/models/GscClient.php';
        $gsc = GscClient::getStatus();
        // Provide live models with pricing for initial render (fallback to MODELS if API unreachable);
        // prices come from OpenRouter API (prompt/completion per token) — see OpenRouter::fetchModels().
        $live = OpenRouter::fetchModels();
        $hasPricing = false;
        foreach ($live as $m) { if (isset($m['pricing'])) { $hasPricing = true; break; } }
        // If live fetch failed and returned fallback without usable pricing, it still contains pricing now.
        $this->view('admin/ai-studio/index', [
            'pageName' => 'ai-studio',
            'models' => OpenRouter::MODELS,
            'modelsLive' => $live,
            'hasPricing' => $hasPricing,
            'gscStatus' => $gsc,
        ]);
    }

    public function models() {
        $this->requireAuth();
        $list = OpenRouter::fetchModels();
        $this->json(['success' => true, 'models' => $list]);
    }

    public function run() {
        $this->requireAuth();

        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->json(['success' => false, 'message' => 'CSRF token validation failed'], 400);
        }

        // Rate limit: 20 runs / 10 min per admin bound to user_id + IP, persisted to storage (bypass via cookie clear mitigated).
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rlKey = 'ai_studio_' . $uid . '_' . sha1($ip);
        // Session bucket (fast path)
        $rlData = $_SESSION["ratelimit_ai_studio_{$rlKey}"] ?? ['count' => 0, 'timestamp' => time()];
        if (time() - $rlData['timestamp'] > 600) $rlData = ['count' => 0, 'timestamp' => time()];
        // File-backed bucket with flock for atomicity across sessions
        $rlDir = BASE_PATH . '/storage/ratelimit_ai_studio';
        if (!is_dir($rlDir)) @mkdir($rlDir, 0750, true);
        $rlFile = $rlDir . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $rlKey) . '.json';
        $fileData = ['count' => 0, 'timestamp' => time()];
        $fp = @fopen($rlFile, 'c+');
        if ($fp) {
            @flock($fp, LOCK_EX);
            $raw = stream_get_contents($fp);
            if ($raw !== false && $raw !== '') {
                $j = json_decode($raw, true);
                if (is_array($j) && isset($j['count'], $j['timestamp'])) $fileData = $j;
            }
            if (time() - (int)$fileData['timestamp'] > 600) $fileData = ['count' => 0, 'timestamp' => time()];
            // Use max of session and file counts to prevent either alone bypassing
            $effective = max((int)$rlData['count'], (int)$fileData['count']) + 1;
            if ($effective > 20) {
                $retryAfter = 600 - (time() - (int)$fileData['timestamp']);
                if ($retryAfter < 1) $retryAfter = 60;
                header('Retry-After: ' . $retryAfter);
                @flock($fp, LOCK_UN); @fclose($fp);
                $this->json(['success' => false, 'message' => 'AI Studio rate limit: max 20 runs per 10 minutes. Please wait.'], 429);
            }
            $fileData['count'] = $effective;
            $fileData['timestamp'] = $rlData['timestamp'] = $fileData['timestamp']; // keep original window start
            // persist both
            ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($fileData));
            @flock($fp, LOCK_UN); @fclose($fp);
            $rlData['count'] = $effective;
        } else {
            // Fallback to session only if file unavailable
            $rlData['count']++;
            if ($rlData['count'] > 20) {
                $retryAfter = 600 - (time() - (int)$rlData['timestamp']);
                header('Retry-After: ' . max(1, $retryAfter));
                $this->json(['success' => false, 'message' => 'AI Studio rate limit: max 20 runs per 10 minutes. Please wait.'], 429);
            }
        }
        $_SESSION["ratelimit_ai_studio_{$rlKey}"] = $rlData;

        $model = trim((string)($_POST['model'] ?? ''));
        // Model allowlist: accept any :free, openrouter/free, or live catalogue match.
        // Prices come from OpenRouter API — see OpenRouter::fetchModels() pricing.
        $originalModel = $model;
        $model = OpenRouter::normalizeModel($model);
        if ($originalModel !== '' && $originalModel !== $model) {
            $sanitized = substr(preg_replace('/[^a-z0-9\/\-\.:_]/i', '', $originalModel), 0, 80);
            $this->logAi('model_fallback', ['requested' => $sanitized !== '' ? $sanitized : '[empty]', 'fallback' => $model, 'allowed_via' => OpenRouter::isAllowedModel($originalModel) ? 'heuristic' : 'invalid']);
        }
        // If normalize fell back to default but original was free variant not in isAllowedModel edge, keep original if it ends with :free
        if ($originalModel !== '' && $model === 'deepseek/deepseek-chat' && str_ends_with($originalModel, ':free') && OpenRouter::isAllowedModel($originalModel)) {
            $model = $originalModel;
        }
        $message = trim((string)($_POST['message'] ?? ''));
        // Hard cap single message to avoid token blow-up (C1/H5).
        if (mb_strlen($message) > 8000) {
            $message = mb_substr($message, 0, 8000) . "\n…[truncated to 8000 chars]";
        }
        $history = $this->sanitizeHistory($_POST['history'] ?? '[]');
        $approved = $this->sanitizeApproved($_POST['approved'] ?? '[]');
        $pending = $this->sanitizePending($_POST['pending'] ?? '[]');
        $mode = strtolower(trim((string)($_POST['mode'] ?? 'plan')));
        if (!in_array($mode, ['plan', 'build'], true)) $mode = 'plan';
        $sessionId = trim((string)($_POST['session_id'] ?? ''));
        // Tightened: require ≥22 chars (brute-force 8-char no longer accepted), UUID v4 36-char preferred.
        // Accepts UUID 36 or legacy 22-char hex; rejects 8-char guessable IDs (06-01).
        if ($sessionId !== '' && !preg_match('/^[a-z0-9\-]{22,64}$/i', $sessionId)) $sessionId = '';
        // Cross-session DB: load history+context if session_id provided
        if ($sessionId !== '') {
            $dbHistory = $this->loadSessionHistory($sessionId);
            if (!empty($dbHistory)) {
                // Merge with dedup and ordering guarantee: DB history is canonical, client history appended only if not duplicate
                $merged = $dbHistory;
                $existingHashes = [];
                foreach ($merged as $m) $existingHashes[($m['role'] ?? '') . ':' . sha1((string)($m['content'] ?? ''))] = true;
                foreach ($history as $m) {
                    $h = ($m['role'] ?? '') . ':' . sha1((string)($m['content'] ?? ''));
                    if (!isset($existingHashes[$h])) {
                        $merged[] = $m;
                        $existingHashes[$h] = true;
                    }
                }
                if (count($merged) > 24) $merged = array_slice($merged, -24);
                $history = $merged;
            }
            $dbCtx = $this->loadSessionContext($sessionId);
            if (!empty($dbCtx)) {
                $_SESSION['ai_context'] = $dbCtx;
                $_SESSION['ai_session_id'] = $sessionId;
            }
        } else {
            // Auto-create session if missing — use UUID v4, DB canonical (02-architecture #5). localStorage is cache only.
            if (empty($_SESSION['ai_session_id'])) {
                $b = random_bytes(16);
                $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
                $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
                $h = bin2hex($b);
                $newId = substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20,12);
                $_SESSION['ai_session_id'] = $newId;
                $this->ensureAiSessionsTable();
                $this->persistSession($newId, $history, [], $model, $mode);
                $sessionId = $newId;
            } else {
                $sessionId = $_SESSION['ai_session_id'];
            }
        }
        // Ensure session persists for MemoryTools
        if (!isset($_SESSION['ai_context']) || !is_array($_SESSION['ai_context'])) $_SESSION['ai_context'] = $this->loadSessionContext($sessionId) ?? [];
        $_SESSION['ai_session_id'] = $sessionId;

        // Validate before switching to SSE — client expects JSON 400 on empty input (03-code-bugs #1)
        if ($message === '') {
            $this->json(['success' => false, 'message' => 'Message cannot be empty'], 400);
        }

        $startedAt = microtime(true);
        $turnsUsed = 0;

        // Snapshot context before unlocking session — needed for persistAfterRun after headers_sent and for cached tokens prompt.
        $ctxSnapshot = $_SESSION['ai_context'] ?? $this->loadSessionContext($sessionId) ?? [];
        if (!is_array($ctxSnapshot)) $ctxSnapshot = [];

        // Register shutdown handler to persist even if PHP-FPM kills the worker (request_terminate_timeout / host buffer)
        $finalText = '';
        $usageTotal = ['prompt' => 0, 'completion' => 0, 'total' => 0, 'cost' => 0.0];
        $didWriteHtml = false;
        $didPreview = false;
        $messages = []; // will be filled below, captured by reference for shutdown
        $shutdownDone = false;
        $shutdownState = ['messages' => &$messages, 'finalText' => &$finalText, 'sessionId' => $sessionId, 'model' => $model, 'mode' => $mode, 'ctxSnapshot' => $ctxSnapshot, 'startedAt' => $startedAt, 'done' => &$shutdownDone];
        register_shutdown_function(function() use (&$shutdownState) {
            $e = error_get_last();
            $isFatal = $e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
            if (!$shutdownState['done']) {
                $msg = $isFatal ? 'fatal: ' . $e['message'] . ' at ' . $e['file'] . ':' . $e['line'] : 'killed: PHP worker terminated (timeout/host buffer) without done';
                @error_log(json_encode(['ts'=>date('Y-m-d H:i:s'), 'event'=>'run_killed_shutdown', 'session_id'=>substr($shutdownState['sessionId'],0,12), 'message'=>$msg, 'messages_count'=>count($shutdownState['messages'] ?? [])], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n", 3, BASE_PATH . '/logs/ai-studio.log');
                @error_log(json_encode(['ts'=>date('Y-m-d H:i:s'), 'event'=>'run_killed_shutdown', 'session_id'=>substr($shutdownState['sessionId'],0,16), 'message'=>$msg, 'messages'=>array_slice($shutdownState['messages'] ?? [], -4)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) . "\n", 3, BASE_PATH . '/logs/ai-studio-debug.log');
                // Best-effort persist (DB) even though headers already sent
                try {
                    $c = new AiStudioController();
                    // Use reflection to call private persistAfterRun without session
                    $ref = new ReflectionMethod($c, 'persistAfterRun');
                    $ref->setAccessible(true);
                    $ref->invoke($c, $shutdownState['sessionId'], $shutdownState['messages'] ?? [], $shutdownState['model'], $shutdownState['mode'], $shutdownState['ctxSnapshot'] ?? []);
                } catch (Throwable $ignored) {}
            }
        });

        // Unlock session for concurrent admin tabs / GSC calls (C3).
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        @ignore_user_abort(true);
        @set_time_limit(0);

        $this->startStream();

        $promptForLog = $this->buildSystemPrompt($mode, $ctxSnapshot);
        $this->logAi('run_start', [
            'model' => $model,
            'mode' => $mode,
            'message_len' => mb_strlen($message),
            'history_turns' => count($history),
            'approved_count' => count($approved),
            'pending_count' => count($pending),
            'prompt_chars' => mb_strlen($promptForLog),
        ]);
        if ($this->shouldDebug()) {
            $this->logDebug('prompt_raw', [
                'model' => $model,
                'mode' => $mode,
                'system_prompt' => $promptForLog,
                'system_prompt_chars' => mb_strlen($promptForLog),
                'initial_messages' => array_map(fn($m)=>['role'=>$m['role']??'','content'=>isset($m['content'])? mb_substr($m['content'],0,4000):'', 'tool_calls'=>isset($m['tool_calls'])? array_slice($m['tool_calls'],0,3):null], $messages),
                'history_raw' => $history,
                'pending_raw' => $pending,
                'user_message' => $message,
                'definitions_count' => count(AiToolRegistry::definitionsForMode($mode)),
            ]);
        }

        $this->sse('activity', ['text' => ($mode === 'plan' ? 'Planning…' : 'Starting…')]);

        // Deduplicate last history turn if client already pushed current message (03-code-bugs #2: whitespace-normalized)
        $alreadyInHistory = false;
        if (!empty($history)) {
            $last = end($history);
            if (($last['role'] ?? '') === 'user') {
                $norm = function(string $s): string {
                    $s = str_replace("\r\n", "\n", $s);
                    $s = preg_replace('/\s+/u', ' ', trim($s));
                    return $s ?? '';
                };
                if ($norm((string)($last['content'] ?? '')) === $norm($message)) {
                    $alreadyInHistory = true;
                }
            }
        }
        $messages = array_merge(
            [['role' => 'system', 'content' => $promptForLog]],
            $history,
            $pending,
            $alreadyInHistory ? [] : [['role' => 'user', 'content' => $message]]
        );

        try {
            for ($turn = 1; $turn <= self::MAX_TOOL_TURNS; $turn++) {
                if (connection_aborted()) {
                    $this->logAi('run_end', [
                        'status' => 'aborted',
                        'turns' => $turnsUsed,
                        'duration_ms' => $this->elapsedMs($startedAt),
                    ]);
                    try { $this->persistAfterRun($sessionId, $messages, $model, $mode, $ctxSnapshot); $shutdownDone = true; } catch (Throwable $e) { error_log('persist on abort failed: ' . $e->getMessage()); }
                    // Try to tell the client we stopped — if the TCP is already dead the bytes are just dropped.
                    try { $this->sse('done', ['status' => 'aborted', 'text' => $finalText]); } catch (Throwable $e) {}
                    return; // client pressed Stop — don't spend more tokens
                }

                $turnsUsed++;
                $this->sse('turn', ['number' => $turn, 'max' => self::MAX_TOOL_TURNS]);
                $this->sse('activity', ['text' => 'Thinking… turn ' . $turn . '/' . self::MAX_TOOL_TURNS]);

                $modelStart = microtime(true);
                // Budget guard: estimate chars → tokens (~4 chars/token), drop oldest history if over 60k tokens
                // Reliable: never evict pending pair or first user intent — keep them pinned even when trimming.
                $estChars = array_sum(array_map(fn($m) => mb_strlen(json_encode($m, JSON_UNESCAPED_UNICODE) ?: ''), $messages));
                if ($estChars > 200000) {
                    $keep = count($messages) - (int)(($estChars - 180000)/4000);
                    $minKeep = !empty($pending) ? 8 : 6;
                    if ($keep < $minKeep) $keep = $minKeep;
                    $system = $messages[0];
                    $rest = array_slice($messages, 1);
                    // Pin first user intent if trimming would drop it
                    $firstUser = null;
                    foreach ($rest as $idx => $m) { if (($m['role']??'')==='user') { $firstUser = $m; break; } }
                    $rest = array_slice($rest, -$keep);
                    if ($firstUser !== null) {
                        $hasFirst = false;
                        foreach ($rest as $m) { if (($m['role']??'')==='user' && ($m['content']??'')===($firstUser['content']??'')) { $hasFirst=true; break; } }
                        if (!$hasFirst) { array_unshift($rest, $firstUser); }
                    }
                    // Ensure pending pair (2 msgs) stays if it was trimmed
                    if (!empty($pending) && count($pending)===2) {
                        foreach ($pending as $p) {
                            $found=false;
                            foreach ($rest as $rm) { if (json_encode($rm)===json_encode($p)) { $found=true; break; } }
                            if (!$found) $rest[] = $p;
                        }
                    }
                    $messages = array_merge([$system], $rest);
                    $this->sse('activity', ['text' => 'Context trimmed to fit token budget']);
                }
                if ($this->shouldDebug()) {
                    $this->logDebug('turn_request', [
                        'turn' => $turn,
                        'model' => $model,
                        'messages' => $messages,
                        'messages_chars' => array_sum(array_map(fn($m)=> mb_strlen(json_encode($m, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: ''), $messages)),
                        'definitions' => AiToolRegistry::definitionsForMode($mode),
                    ]);
                }
                // In BUILD, force tool use via tool_choice=required — technical enforcement, not just prompt (fixes "got it" loops)
                $toolChoice = $mode === 'build' ? 'required' : 'auto';
                // If previous turn in same run had no tool_calls in BUILD, keep required
                $response = OpenRouter::chatWithTools($messages, $model, AiToolRegistry::definitionsForMode($mode), 0.5, 8192, 2, $toolChoice);
                if ($this->shouldDebug()) {
                    $this->logDebug('turn_response', [
                        'turn' => $turn,
                        'model' => $model,
                        'content' => $response['content'] ?? '',
                        'content_chars' => mb_strlen($response['content'] ?? ''),
                        'tool_calls' => $response['tool_calls'] ?? null,
                        'finish_reason' => $response['finish_reason'] ?? null,
                        'usage' => $response['usage'] ?? null,
                    ]);
                }
                if (connection_aborted()) {
                    $this->logAi('run_end', ['status'=>'aborted_after_model','turns'=>$turnsUsed,'duration_ms'=>$this->elapsedMs($startedAt)]);
                    try { $this->persistAfterRun($sessionId, $messages, $model, $mode, $ctxSnapshot); $shutdownDone = true; } catch (Throwable $e) { error_log('persist on abort_after_model failed: ' . $e->getMessage()); }
                    try { $this->sse('done', ['status'=>'aborted','text'=>$finalText]); } catch (Throwable $e) {}
                    return;
                }
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
                    // BUILD enforcement: idle text without tool in BUILD is a violation — auto-nudge instead of ending run
                    if ($mode === 'build' && $turn < self::MAX_TOOL_TURNS) {
                        $isContinue = preg_match('/^(continue|продолжай|дальше|далее)\s*$/iu', trim($message));
                        $isAcknowledgeLoop = preg_match('/(no more confirmations|got it|понял|без подтверждений)/iu', (string)$response['content']);
                        // If model just acknowledged without acting, force it to act
                        if ($isAcknowledgeLoop || $isContinue || mb_strlen(trim((string)$response['content'])) < 400) {
                            $this->logAi('build_nudge', ['turn'=>$turn,'reason'=> $isAcknowledgeLoop ? 'ack_loop' : ($isContinue ? 'continue_no_tool' : 'no_tool_in_build'), 'content_preview'=> mb_substr((string)$response['content'],0,200)]);
                            $this->sse('activity', ['text' => 'Build nudge: forcing tool call…']);
                            // Inject system reminder as tool-level instruction for next turn
                            $messages[] = [
                                'role' => 'assistant',
                                'content' => $response['content'],
                            ];
                            $messages[] = [
                                'role' => 'user',
                                'content' => 'SYSTEM ENFORCEMENT (BUILD MODE): You just responded with text only and no tool call. In BUILD mode you MUST call a write tool now (e.g. list_sections/get_section then batch_update/update_section/patch_section). Do not ask for confirmation. Do not say "got it". CALL THE TOOL in the next turn. If you need a target, re-read the last user request and execute.',
                            ];
                            continue; // retry same turn count will increment next loop — give model another chance
                        }
                    }
                    break; // model answered without calling tools — turn complete (PLAN or legitimate chat)
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
                    // 03-code-bugs #3: malformed JSON must surface as explicit tool error, not silent []
                    if (!is_array($args)) {
                        $err = json_last_error_msg();
                        $toolMsgId = $tc['id'] ?? sha1($name . ':' . (string)$rawArgs);
                        $msg = 'Invalid tool arguments JSON for ' . $name . ': ' . $err . ' — request valid JSON.';
                        $this->sse('tool_result', ['tool' => $name, 'ok' => false, 'message' => $msg, 'summary' => 'Error: ' . $msg]);
                        $messages[] = ['role' => 'tool', 'tool_call_id' => $toolMsgId, 'content' => json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE)];
                        $this->logAi('tool_call', ['name' => $name, 'call_id' => $toolMsgId, 'type' => 'error', 'args' => mb_substr((string)$rawArgs, 0, 500), 'error' => 'malformed_json']);
                        continue;
                    }

                    $this->sse('activity', ['text' => 'Running ' . $name . '…']);

                    $toolStart = microtime(true);
                    $out = AiToolRegistry::execute($name, $args, $approved, $mode);
                    $toolMs = (int)round((microtime(true) - $toolStart) * 1000);
                    // 03-code-bugs #4: deterministic call_id fallback to avoid collisions when model omits id
                    $toolMsgId = $tc['id'] ?? $out['call_id'] ?? AiToolRegistry::callId($name, $args);

                    $this->logAi('tool_call', [
                        'name' => $name,
                        'call_id' => $out['call_id'] ?? $toolMsgId,
                        'type' => $out['type'],
                        'args' => mb_substr((string)json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 500),
                        'duration_ms' => $toolMs,
                    ]);
                    if ($this->shouldDebug()) {
                        $this->logDebug('tool_result_raw', [
                            'turn' => $turn,
                            'tool' => $name,
                            'call_id' => $out['call_id'] ?? $toolMsgId,
                            'type' => $out['type'],
                            'args' => $args,
                            'raw_args' => $rawArgs,
                            'result' => $out['result'] ?? null,
                            'message' => $out['message'] ?? null,
                            'plan' => $out['plan'] ?? null,
                            'reason' => $out['reason'] ?? null,
                            'duration_ms' => $toolMs,
                        ]);
                    }

                    if ($out['type'] === 'approval') {
                        $haltForApproval = true;
                        // Batch-aware: batch_update may require multiple per-op approvals
                        $approvalCallIds = isset($out['call_ids']) && is_array($out['call_ids']) ? $out['call_ids'] : [$out['call_id']];
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
                                    'call_ids' => $approvalCallIds,
                                    'plan' => $out['plan'],
                                    'note' => 'Re-issue the exact same tool call (same name and arguments) to execute this change.',
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            ],
                        ];
                        $this->sse('approval_required', [
                            'call_id' => $out['call_id'],
                            'call_ids' => $approvalCallIds,
                            'tool' => $name,
                            'plan' => $out['plan'],
                            'reason' => $out['reason'],
                            'pending' => $pendingPair,
                        ]);
                        $this->logAi('approval_requested', [
                            'call_id' => $out['call_id'],
                            'call_ids' => $approvalCallIds,
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

                    // Track visual writes for soft preview reminder (04-05)
                    $visualWrites = ['update_section','patch_section','insert_section','wrap_section','add_section_marker','auto_sectionize','set_section_style','batch_update','str_replace_field','set_field'];
                    if (in_array($name, $visualWrites, true) && ($out['result']['ok'] ?? $out['type'] === 'result')) {
                        // str_replace/set on content_ru/uz counts as visual; meta titles not, but we treat all as potential visual to avoid false negatives
                        $didWriteHtml = true;
                    }

                    if (($name === 'render_preview' || $name === 'render_full_page') && isset($out['result']['html'])) {
                        $didPreview = true;
                        $this->sse('activity', ['text' => 'Rendering preview…']);
                        $this->sse('preview', ['html' => $out['result']['html'], 'kind' => $name, 'tool' => $name]);
                    }

                    $toolJson = json_encode($out['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                    if ($toolJson === false) $toolJson = '{"error":"failed to encode tool result"}';
                    if (mb_strlen($toolJson) > 6000) {
                        // Performance 05 #2: reduced from 12k to 6k to halve context cost (96k→48k over 8 turns)
                        $origLen = mb_strlen($toolJson);
                        $preview = mb_substr($toolJson, 0, 5500);
                        $toolJson = json_encode([
                            '_truncated' => true,
                            'original_chars' => $origLen,
                            'preview_chars' => 5500,
                            'note' => 'Tool result truncated for context window (6000 char cap). Use get_section/get_content_chunk with offsets for full value.',
                            'preview_json' => $preview,
                            // include structured summary when possible
                            'result_summary' => is_array($out['result']) ? array_slice($out['result'], 0, 5) : mb_substr((string)json_encode($out['result']), 0, 500),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                        if ($toolJson === false) $toolJson = '{"error":"failed to encode truncated tool result","original_chars":' . $origLen . '}';
                    }
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolMsgId,
                        'content' => $toolJson,
                    ];
                }

                if ($haltForApproval) {
                    $this->logAi('run_end', [
                        'status' => 'awaiting_approval',
                        'turns' => $turnsUsed,
                        'duration_ms' => $this->elapsedMs($startedAt),
                    ]);
                    $this->persistAfterRun($sessionId, $messages, $model, $mode, $ctxSnapshot);
                    $shutdownDone = true;
                    $this->sse('done', ['status' => 'awaiting_approval', 'text' => $finalText]);
                    return;
                }
            }

            // H1: detect hitting the cap with pending tool calls
            $hitCap = ($turnsUsed >= self::MAX_TOOL_TURNS && !empty($toolCalls ?? null));

            // Soft preview reminder: if HTML was written but never previewed, hint the model/next turn (04-05) — do not block, just log + nudge
            if ($didWriteHtml && !$didPreview && !$hitCap && $mode === 'build') {
                $this->logAi('preview_missing', ['turns'=>$turnsUsed,'hint'=>'visual edit without render_preview/render_full_page']);
                // Append soft reminder to final text so client sees it without breaking complete status
                if ($finalText !== '' && stripos($finalText, 'preview') === false) {
                    $finalText .= "\n\n[Hint: you made HTML edits without calling render_preview/render_full_page — call render_preview for the changed section(s) + render_full_page once before marking complete.]";
                }
            }
            if ($hitCap) {
                $this->sse('error', ['message' => 'Reached max tool turns (' . self::MAX_TOOL_TURNS . ') — response truncated. Say "continue" to resume or simplify the request. Tip: use batch_update to combine edits.']);
                $this->sse('done', ['status' => 'max_turns_exceeded', 'text' => $finalText]);
                $this->logAi('run_end', [
                    'status' => 'max_turns_exceeded',
                    'turns' => $turnsUsed,
                    'prompt_tokens' => $usageTotal['prompt'],
                    'completion_tokens' => $usageTotal['completion'],
                    'total_tokens' => $usageTotal['total'],
                    'cost' => $usageTotal['cost'],
                    'duration_ms' => $this->elapsedMs($startedAt),
                ]);
                $this->persistAfterRun($sessionId, $messages, $model, $mode, $ctxSnapshot);
                $shutdownDone = true;
            } else {
                $this->sse('done', ['status' => 'complete', 'text' => $finalText]);
                $this->logAi('run_end', [
                    'status' => 'complete',
                    'turns' => $turnsUsed,
                    'prompt_tokens' => $usageTotal['prompt'],
                    'completion_tokens' => $usageTotal['completion'],
                    'total_tokens' => $usageTotal['total'],
                    'cost' => $usageTotal['cost'],
                    'duration_ms' => $this->elapsedMs($startedAt),
                    'preview_missing' => ($didWriteHtml && !$didPreview) ? 1 : 0,
                ]);
                $this->persistAfterRun($sessionId, $messages, $model, $mode, $ctxSnapshot);
                $shutdownDone = true;
            }
        } catch (Throwable $e) {
            $this->logAi('run_error', [
                'message' => $e->getMessage(),
                'at' => $e->getFile() . ':' . $e->getLine(),
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);
            if ($this->shouldDebug()) {
                $this->logDebug('run_error_raw', [
                    'exception' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                    'trace' => mb_substr($e->getTraceAsString(), 0, 4000),
                    'messages_snapshot' => array_slice($messages ?? [], -6),
                ]);
            }
            try { $this->persistAfterRun($sessionId, $messages, $model, $mode, $ctxSnapshot ?? []); $shutdownDone = true; } catch (Throwable $ignored) { error_log('persist on error failed: ' . $ignored->getMessage()); }
            try { $this->sse('error', ['message' => $e->getMessage()]); } catch (Throwable $ignored) {}
            try { $this->sse('done', ['status' => 'error']); } catch (Throwable $ignored) {}
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
            @ini_set('zlib.output_compression', '0');
        }
        // Discard any prior buffered HTML (front controller ob_start + header views) instead of flushing it into the SSE stream (C2).
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        // Prevent PHP session lock already released above; ensure no further buffering.
        if (function_exists('fastcgi_finish_request')) {
            // not calling, just ensuring headers sent
        }
        echo "retry: 2000\n\n";
        $this->flushAll();
    }

    private function sse(string $event, array $data = []) {
        $event = preg_replace('/[^a-zA-Z0-9_\-]/', '', $event) ?: 'message';
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = json_encode(['error' => 'Failed to encode SSE payload', 'event' => $event]);
            if ($json === false) $json = '{"error":"encode failed"}';
        }
        echo 'event: ' . $event . "\n";
        echo 'data: ' . $json . "\n\n";
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
        $sid = $_SESSION['ai_session_id'] ?? ($_POST['session_id'] ?? null);
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

    private function logDebug(string $event, array $payload = []): void {
        $sid = $_SESSION['ai_session_id'] ?? ($_POST['session_id'] ?? null);
        $line = json_encode([
            'ts' => date('Y-m-d H:i:s.v'),
            'event' => $event,
            'session_id' => $sid ? substr((string)$sid, 0, 16) : null,
        ] + $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line === false) $line = json_encode(['ts'=>date('Y-m-d H:i:s'), 'event'=>$event, 'error'=>'json_encode failed']);
        // Rotate debug log at 20MB
        if (is_file(self::DEBUG_LOG_FILE) && @filesize(self::DEBUG_LOG_FILE) > 20 * 1024 * 1024) {
            @rename(self::DEBUG_LOG_FILE, self::DEBUG_LOG_FILE . '.' . date('Y-m-d_His'));
        }
        @error_log($line . "\n", 3, self::DEBUG_LOG_FILE);
    }

    private function shouldDebug(): bool {
        if (isset($_POST['debug']) && $_POST['debug'] === '1') return true;
        if (isset($_GET['debug']) && $_GET['debug'] === '1') return true;
        if (getenv('AI_STUDIO_DEBUG') === '1') return true;
        // Always debug in development for now (can gate by APP_ENV if needed)
        return true;
    }

    // ------------------------------------------------------------------
    // Prompt + helpers
    // ------------------------------------------------------------------

    private function buildSystemPrompt(string $mode = 'plan', ?array $ctx = null): string {
        $mode = $mode === 'build' ? 'build' : 'plan';
        // Inject cached design tokens so continue doesn't re-fetch get_design_tokens (reliable history continuity)
        $cachedAddon = '';
        if (is_array($ctx) && isset($ctx['_cached_tokens']) && is_array($ctx['_cached_tokens'])) {
            $age = isset($ctx['_cached_tokens_at']) ? (time() - (int)$ctx['_cached_tokens_at']) : 999999;
            if ($age < 3600) {
                $tokJson = json_encode($ctx['_cached_tokens'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($tokJson !== false && mb_strlen($tokJson) > 20) {
                    if (mb_strlen($tokJson) > 2000) $tokJson = mb_substr($tokJson, 0, 2000) . '…[truncated]';
                    $cachedAddon = "\n\n═══ CACHED DESIGN TOKENS (from earlier turn, " . $age . "s ago — do NOT call get_design_tokens again; use this) ═══\n" . $tokJson;
                }
            }
        }
        if ($mode === 'plan') {
            return $this->buildPlanPrompt() . $cachedAddon;
        }
        return $this->buildBuildPrompt() . $cachedAddon;
    }

    private function buildPlanPrompt(): string {
        return <<<'PROMPT'
You are a Staff-level HTML/CSS & Technical SEO auditor (15+ years) for kuplyu-tashkent.uz — Tashkent's #1 appliance & furniture buyback (скупка/выкуп бытовой техники и мебели: холодильники, стиральные машины, телевизоры, газовые плиты, кондиционеры, диваны, кровати, шкафы, столы/стулья), bilingual RU/UZ. You are READ-ONLY in this session. You investigate, diagnose, and propose a precise execution plan. You never write, never mutate data.

AVAILABLE TOOLS (read-only): list_pages, get_page, search_content, list_sections, get_section, get_content_chunk, list_page_revisions, get_page_revision, get_global_settings, get_template_variables, get_design_tokens, render_preview, render_full_page, list_rotations, get_rotation, get_top_pages, get_page_stats, get_underperforming_pages, get_crawl_frequency, get_internal_links, get_rotation_effectiveness, run_analytics_query, query_builder, get_gsc_overview, get_page_gsc, get_gsc_queries, get_gsc_pages, search_gsc_queries, query_gsc, list_faqs, get_faq, list_context, get_context.

RULES:
- Use reads to ground every claim. Prefer list_sections → get_section for exact HTML; get_page is truncated at 12k.
- Never call write tools. They are not available to you.
- Be concise and factual. No chit-chat, no "I understand" filler. Output a structured plan:
  1. What you audited (pages/slugs/sections, with char counts/hashes)
  2. What you will change — per slug/field/section, with draft HTML/text snippets
  3. Risks / dependencies
  4. End with: "Switch to BUILD to apply" — nothing else.
- Quality bar: W3C-valid semantic HTML5, Lighthouse 95+, WCAG 2.2 AA, RU↔UZ parity, template vars {{page.title}} {{global.*}} {{faqs}} preserved.
- Doctrine: tokens --teal --orange --ink --surface etc. via get_design_tokens + 178 .c-* classes (c-hero-split, c-stats, c-feature-grid, c-pricing…). Call get_design_tokens + get_global_settings when they inform the plan.
PROMPT;
    }

    private function buildBuildPrompt(): string {
        return <<<'PROMPT'
You are an autonomous BUILDER — Staff-level HTML/CSS & Technical SEO specialist (15+ years) for kuplyu-tashkent.uz — Tashkent appliance & furniture buyback niche (выкуп техники и мебели: холодильники, стиралки, ТВ, плиты, кондиционеры + диваны, шкафы, кровати, столы; bilingual RU/UZ, intent: "продать б/у технику/мебель в Ташкенте, скупка, выкуп, дорого"). Your job is to ACT, not to chat. You ship code via tools. Text without a tool call is wasted.

YOU MUST CALL A TOOL EVERY TURN. Server enforces tool_choice=required — a text-only reply will be rejected and retried. Never output "got it", "no more confirmations", "понял" alone. Never ask "should I proceed?" / "do you want me to?" — you already have permission.

LOOP — ACT SAME TURN YOU READ:
1. If user named a concrete target (slug/section like "hansa-fcmw58221", "Kravat", "Features"): call list_sections or get_content_chunk for that slug IMMEDIATELY — one read — then WRITE in the same turn (batch_update / update_section / patch_section). Do not re-read what you already have. get_page's sections_hint is enough to locate. For meta_title_ru/meta_description_ru/title_ru you MUST first call get_page and copy the exact current value as "find" — do not guess (valid fields: content_ru, content_uz, title_ru, title_uz, meta_title_ru, meta_title_uz, meta_description_ru, meta_description_uz).
2. If request is vague ("the pages", "underperforming"): discover via list_pages + get_underperforming_pages/search_content + get_gsc_overview — diagnose then write.
3. Always batch: prefer batch_update for 5-10 edits in one call. Small fixes → patch_section/str_replace_field; full rewrites → update_section; new blocks → insert_section; style → set_section_style. For meta fields str_replace_field requires verbatim find — if you get "find not found (length 87)" you guessed wrong; re-fetch via get_page and retry with exact string. SHIP IT.
4. Narrative: one short line ("reading Kravat Features") then ACT.

TECHNICAL GUARANTEES:
- Every write is snapshotted to page_revisions (undo via restore_page_revision). It is safe to act.
- Only destructive wipes (set_field full overwrite, delete_faq, set_rotation, restore_page_revision) ask approval; everything else auto-executes, even >800 chars.
- After any HTML edit: render_preview for each changed section, then one render_full_page at the end.

CORE DOCTRINE:
- Tokens: call get_design_tokens first ( --teal, --teal-dark, --orange, --green, --ink, --muted, --surface, --border, --max-w, --section-gap + 178 .c-* plugin classes from components.css). Then get_global_settings for phone/address. Use tokens var(--teal) by default.
- Semantic HTML5 + WCAG 2.2 AA (4.5:1, focus-visible, 44px), container queries, BEM, mobile-first 375→1024. Legacy: content-section, info-card, process-step, faq-item, links-tile, btn. Plugin: c-hero-split/centered/mesh, c-stats/bar/dark, c-feature-grid/split, c-process/timeline, c-card/testimonial, c-cta/callout, c-gallery/carousel, c-prose/quote, c-pricing/comparison — use any .c-* via HTML, no custom CSS needed.
- Per-page theming via set_custom_css / set_page_theme (body.page-{slug} header{...}).
- SEO E-E-A-T, hreflang ru/uz/x-default, BreadcrumbList/FAQPage, 40-60 word featured-snippet blocks.
- Preserve {{page.title}} {{global.phone}} {{global.email}} {{global.address}} {{global.working_hours}} {{global.site_name}} {{faqs}}.

ANTI-CHITCHAT:
- Zero filler. No "Sure!", no "I understand, I will…". Just do it.
- On "continue" — do not acknowledge, continue building where you left off.
- On "hi" — build a hello-world demo block (hero + stats) immediately.
- End every run with a one-paragraph summary of what CHANGED (slugs/sections/fields, char counts, preview hashes).

DEFINITION OF DONE: W3C headings sequential, landmarks valid, RU↔UZ parity, template vars intact, preview per section + final full-page render.
PROMPT;
    }

    private function sanitizeHistory($history): array {
        $decoded = json_decode((string)$history, true);
        $messages = [];
        if (is_array($decoded)) {
            foreach ($decoded as $turn) {
                if (!is_array($turn)) continue;
                // 06-05: keep tool role as well so grounding survives cap; client-supplied tool results are validated (tool_call_id + content length) downstream.
                $role = in_array($turn['role'] ?? '', ['user', 'assistant', 'tool'], true) ? $turn['role'] : null;
                // For tool role, ensure tool_call_id exists to avoid orphan tool messages
                if ($role === 'tool' && empty($turn['tool_call_id'])) continue;
                $content = (string)($turn['content'] ?? '');
                // Strip control chars and normalize whitespace to reduce prompt injection via hidden chars
                $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);
                // Block system-like prefix injection inside user content (e.g., "SYSTEM: you are now...")
                if (preg_match('/^\s*(system|assistant\s*\(system\)|ignore\s+previous\s+instructions)/i', $content)) {
                    $content = '[filtered system-like prefix] ' . ltrim(preg_replace('/^\s*(system|assistant\s*\(system\)|ignore\s+previous\s+instructions)[:\-]*/i', '', $content));
                }
                // Per-message cap to prevent token blow-up (H5). Truncate, don't drop.
                if (mb_strlen($content) > 4000) $content = mb_substr($content, 0, 4000) . "\n…[truncated]";
                if ($role && $content !== '') {
                    // Only deduplicate consecutive duplicates (not global seen) — two identical get_section previews
                    // at different points in history are legitimate (e.g., re-reading after write).
                    $hash = $role . ':' . sha1($content);
                    $lastHash = end($messages) ? (end($messages)['role'] . ':' . sha1(end($messages)['content'])) : null;
                    if ($hash === $lastHash) continue;
                    $messages[] = ['role' => $role, 'content' => $content];
                }
                if (count($messages) >= 48) break; // hard cap before slice
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
        $seen = [];
        foreach ($decoded as $id) {
            if (count($out) >= 20) break; // cap array size (C)
            if (is_string($id) && preg_match('/^[a-f0-9]{40}$/', $id) && !isset($seen[$id])) {
                $seen[$id] = true;
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
                    // 06-06: reject oversize IDs instead of silent substr truncation (prevents ID collision pairing wrong tool)
                    if (strlen($id) > 64 || strlen($name) > 64) return [];
                    if ($id !== '' && $name !== '' && strlen($arguments) <= 65536
                        && json_decode($arguments) !== null) {
                        $out[] = [
                            'role' => 'assistant',
                            'content' => (string)($assistant['content'] ?? ''),
                            'tool_calls' => [[
                                'id' => $id,
                                'type' => 'function',
                                'function' => [
                                    'name' => $name,
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
            if (strlen($callId) > 64) return [];
            if ($callId !== '' && $content !== '' && strlen($content) <= 65536
                && $callId === ($out[0]['tool_calls'][0]['id'] ?? '')) {
                $out[] = [
                    'role' => 'tool',
                    'tool_call_id' => $callId,
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

    // ------------------------------------------------------------------
    // Sessions DB (cross-session persistence)
    // ------------------------------------------------------------------
    public function sessions() {
        $this->requireAuth();
        $this->ensureAiSessionsTable();
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $rows = Database::getInstance()->fetchAll("SELECT id, title, model, mode, updated_at, created_at FROM ai_sessions WHERE user_id = ? ORDER BY updated_at DESC LIMIT 50", [$uid]);
        $this->json(['success'=>true,'sessions'=>$rows]);
    }
    public function session(string $id) {
        $this->requireAuth();
        $this->ensureAiSessionsTable();
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $row = Database::getInstance()->fetchOne("SELECT * FROM ai_sessions WHERE id = ? AND user_id = ?", [$id,$uid]);
        if (!$row) $this->json(['success'=>false,'message'=>'Session not found'],404);
        $history = json_decode($row['history'] ?? '[]', true);
        $context = json_decode($row['context'] ?? '{}', true);
        $this->json(['success'=>true,'session'=>['id'=>$row['id'],'title'=>$row['title'],'model'=>$row['model'],'mode'=>$row['mode'],'history'=>is_array($history)?array_slice($history,-24):[],'context'=>is_array($context)?$context:[],'updated_at'=>$row['updated_at']]]);
    }
    public function deleteSession(string $id) {
        $this->requireAuth();
        $uid = (int)($_SESSION['user_id'] ?? 0);
        Database::getInstance()->query("DELETE FROM ai_sessions WHERE id = ? AND user_id = ?", [$id,$uid]);
        $this->json(['success'=>true]);
    }
    private function ensureAiSessionsTable(): void {
        try {
            Database::getInstance()->query("CREATE TABLE IF NOT EXISTS ai_sessions (
                id CHAR(36) PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(200) DEFAULT '',
                model VARCHAR(80) NOT NULL DEFAULT 'deepseek/deepseek-chat',
                mode ENUM('plan','build') NOT NULL DEFAULT 'plan',
                history JSON NULL,
                context JSON NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_updated (user_id, updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            // 06-10: don't swallow — log so DB permission errors are visible (persistSession would otherwise silently fail)
            error_log('ensureAiSessionsTable failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
    private function loadSessionHistory(string $sessionId): array {
        try {
            $this->ensureAiSessionsTable();
            $uid = (int)($_SESSION['user_id'] ?? 0);
            $row = Database::getInstance()->fetchOne("SELECT history FROM ai_sessions WHERE id = ? AND user_id = ?", [$sessionId,$uid]);
            if (!$row || empty($row['history'])) return [];
            $arr = is_string($row['history']) ? json_decode($row['history'], true) : $row['history'];
            return is_array($arr) ? $arr : [];
        } catch (Throwable $e) { return []; }
    }
    private function loadSessionContext(string $sessionId): array {
        try {
            $this->ensureAiSessionsTable();
            $uid = (int)($_SESSION['user_id'] ?? 0);
            $row = Database::getInstance()->fetchOne("SELECT context FROM ai_sessions WHERE id = ? AND user_id = ?", [$sessionId,$uid]);
            if (!$row || empty($row['context'])) return [];
            $arr = is_string($row['context']) ? json_decode($row['context'], true) : $row['context'];
            return is_array($arr) ? $arr : [];
        } catch (Throwable $e) { return []; }
    }
    private function persistSession(string $sessionId, array $history, array $context, string $model, string $mode): void {
        try {
            $this->ensureAiSessionsTable();
            $uid = (int)($_SESSION['user_id'] ?? 0);
            // Trim history to last 12 turns *2 =24 msgs, sanitize
            if (count($history) > 24) $history = array_slice($history, -24);
            $title = '';
            foreach ($history as $m) { if (($m['role']??'')==='user' && !empty($m['content'])) { $title = mb_substr(trim($m['content']),0,120); break; } }
            $histJson = json_encode($history, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $ctxJson = json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $exists = Database::getInstance()->fetchOne("SELECT id FROM ai_sessions WHERE id = ? AND user_id = ?", [$sessionId, $uid]);
            if ($exists) {
                Database::getInstance()->query("UPDATE ai_sessions SET history=?, context=?, model=?, mode=?, title=?, updated_at=NOW() WHERE id=? AND user_id=?", [$histJson,$ctxJson,$model,$mode,$title,$sessionId,$uid]);
            } else {
                Database::getInstance()->query("INSERT INTO ai_sessions (id,user_id,title,model,mode,history,context) VALUES (?,?,?,?,?,?,?)", [$sessionId,$uid,$title,$model,$mode,$histJson,$ctxJson]);
            }
        } catch (Throwable $e) { error_log('persistSession failed: '.$e->getMessage()); }
    }
    private function persistAfterRun(string $sessionId, array $messages, string $model, string $mode, ?array $ctxSnapshot = null): void {
        // $messages includes system + history + new turns — extract user/assistant/tool for storage
        $history = [];
        foreach ($messages as $m) {
            if (($m['role']??'')==='system') continue;
            // Keep user, assistant, tool
            if (in_array($m['role']??'', ['user','assistant','tool'], true)) $history[] = $m;
        }
        // Compact verbatim tool HTML before persisting to avoid blowing 4k cap + 24-msg window (reliable fix).
        $history = $this->compactHistoryForPersist($history);
        // Cap total chars per msg to 4000 like sanitizeHistory
        foreach ($history as &$h) {
            if (isset($h['content']) && mb_strlen($h['content'])>4000) $h['content']=mb_substr($h['content'],0,4000)."\n…[truncated]";
        }
        if (count($history) > 24) $history = array_slice($history,-24);
        // Prefer snapshot captured before startStream() — after headers_sent $_SESSION is inaccessible.
        $ctx = $ctxSnapshot;
        if ($ctx === null) {
            $ctx = [];
            if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            if (session_status() === PHP_SESSION_ACTIVE) {
                $ctx = $_SESSION['ai_context'] ?? [];
                session_write_close();
            } else {
                // headers already sent — fallback to DB context (captured before stream or load)
                $ctx = $this->loadSessionContext($sessionId);
                if (!is_array($ctx)) $ctx = [];
            }
        }
        if (!is_array($ctx)) $ctx = [];
        // Cache design tokens / global settings if seen in this run (so continue never re-reads them).
        $ctx = $this->mergeTokensIntoContext($ctx, $messages);
        $this->persistSession($sessionId, $history, is_array($ctx)?$ctx:[], $model, $mode);
        if (!headers_sent()) {
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['ai_session_id']=$sessionId;
                if (is_array($ctx)) $_SESSION['ai_context']=$ctx;
                session_write_close();
            }
        } else {
            error_log('persistAfterRun: headers already sent, DB persisted but session superglobal not restored for ' . $sessionId);
        }
    }

    private function compactHistoryForPersist(array $history): array {
        // Replace verbatim bulky tool results (e.g. get_section html, get_page content) with compact summaries
        // so history window holds actions/intent, not HTML dumps. Verbatim is kept in page_revisions.
        $out = [];
        foreach ($history as $m) {
            if (($m['role'] ?? '') === 'tool' && isset($m['content']) && mb_strlen($m['content']) > 1200) {
                $decoded = json_decode($m['content'], true);
                if (is_array($decoded)) {
                    // Already a write-result with verified hash/preview — compress to 400 chars summary
                    if (isset($decoded['ok']) || isset($decoded['html']) || isset($decoded['chunk']) || isset($decoded['preview_json'])) {
                        $compact = [
                            '_compacted' => true,
                            'original_chars' => mb_strlen($m['content']),
                            'summary' => array_slice($decoded, 0, 6),
                        ];
                        // Keep hash/preview if present for continuity
                        if (isset($decoded['hash'])) $compact['hash'] = $decoded['hash'];
                        if (isset($decoded['fresh_hash'])) $compact['fresh_hash'] = $decoded['fresh_hash'];
                        if (isset($decoded['after_preview'])) $compact['after_preview'] = mb_substr((string)$decoded['after_preview'], 0, 200);
                        // If get_section html is large, keep only preview + hash, drop html
                        if (isset($compact['summary']['html']) && mb_strlen((string)$compact['summary']['html']) > 500) {
                            $compact['summary']['html'] = mb_substr((string)$compact['summary']['html'], 0, 200) . '…[compacted]';
                        }
                        if (isset($compact['summary']['chunk']) && mb_strlen((string)$compact['summary']['chunk']) > 500) {
                            $compact['summary']['chunk'] = mb_substr((string)$compact['summary']['chunk'], 0, 200) . '…[compacted]';
                        }
                        $m['content'] = json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    } elseif (mb_strlen($m['content']) > 2000) {
                        // Generic large tool result — keep first 800 chars + marker
                        $m['content'] = mb_substr($m['content'], 0, 800) . "\n…[compacted " . mb_strlen($m['content']) . "→800]";
                    }
                } elseif (mb_strlen($m['content']) > 2000) {
                    $m['content'] = mb_substr($m['content'], 0, 800) . "\n…[compacted " . mb_strlen($m['content']) . "→800]";
                }
            }
            $out[] = $m;
        }
        return $out;
    }

    private function mergeTokensIntoContext(array $ctx, array $messages): array {
        // Extract design tokens / global settings from tool results in this run and cache in context
        // so next continue turn can see them without re-calling get_design_tokens.
        foreach ($messages as $m) {
            if (($m['role'] ?? '') !== 'tool' || empty($m['content'])) continue;
            $decoded = json_decode($m['content'], true);
            if (!is_array($decoded)) continue;
            // PageTools::getDesignTokens returns tokens array, SiteTools global settings
            if (isset($decoded['tokens']) || isset($decoded['design_tokens']) || (isset($decoded['ok']) && isset($decoded['tokens']))) {
                $ctx['_cached_tokens'] = $decoded;
                $ctx['_cached_tokens_at'] = time();
            }
            if (isset($decoded['global_settings']) || isset($decoded['phone']) || (isset($decoded['ok']) && isset($decoded['site_name']))) {
                $ctx['_cached_global_settings'] = $decoded;
                $ctx['_cached_global_settings_at'] = time();
            }
        }
        // Cap context size to prevent unbounded growth
        if (count($ctx) > 20) {
            // Keep cached tokens/settings + last 15 keys
            $keep = ['_cached_tokens','_cached_tokens_at','_cached_global_settings','_cached_global_settings_at'];
            $newCtx = [];
            foreach ($keep as $k) if (isset($ctx[$k])) $newCtx[$k] = $ctx[$k];
            $others = array_diff_key($ctx, array_flip($keep));
            if (count($others) > 15) $others = array_slice($others, -15, null, true);
            $ctx = array_merge($newCtx, $others);
        }
        return $ctx;
    }

    /** Human-readable one-liner for the transcript; keeps the feed tidy. */
    private function summarizeResult(string $tool, $result): string {
        if (is_string($result)) {
            $s = mb_substr($result, 0, 300);
            return mb_strlen($result) > 300 ? $s . '…[truncated]' : $s;
        }
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return 'Tool returned data that could not be serialized';
        }
        return mb_strlen($json) > 300 ? mb_substr($json, 0, 300) . '…[truncated]' : $json;
    }
}
