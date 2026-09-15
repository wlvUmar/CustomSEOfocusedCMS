<?php
// path: ./controllers/admin/AiStudioController.php
// AI Studio: an agent loop over the CMS admin via tools.
//   GET  /admin/ai-studio      → index()  — the chat window
//   POST /admin/ai-studio/run  → run()    — one agent turn (SSE stream)

require_once BASE_PATH . '/models/Opencode.php';
require_once BASE_PATH . '/models/ai/AiToolRegistry.php';
require_once BASE_PATH . '/models/ai/AiRunGuard.php';
require_once BASE_PATH . '/models/ai/ContextBuilder.php';
require_once BASE_PATH . '/models/ai/PromptLoader.php';

class AiStudioController extends Controller {

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
        // prices come from OpenCode Go API — see Opencode::fetchModels().
        $live = Opencode::fetchModels();
        $hasPricing = false;
        foreach ($live as $m) { if (isset($m['pricing'])) { $hasPricing = true; break; } }
        // If live fetch failed and returned fallback without usable pricing, it still contains pricing now.
        $this->view('admin/ai-studio/index', [
            'pageName' => 'ai-studio',
            'models' => Opencode::MODELS,
            'modelsLive' => $live,
            'hasPricing' => $hasPricing,
            'maxTurns' => 0,
            'gscStatus' => $gsc,
        ]);
    }

    public function models() {
        $this->requireAuth();
        $list = Opencode::fetchModels();
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
        // Model allowlist: opencode-go/* or live catalogue match.
        // Prices come from OpenCode Go API — see Opencode::fetchModels() pricing.
        $originalModel = $model;
        $model = Opencode::normalizeModel($model);
        if ($originalModel !== '' && $originalModel !== $model) {
            $sanitized = substr(preg_replace('/[^a-z0-9\/\-\.:_]/i', '', $originalModel), 0, 80);
            $this->logAi('model_fallback', ['requested' => $sanitized !== '' ? $sanitized : '[empty]', 'fallback' => $model, 'allowed_via' => Opencode::isAllowedModel($originalModel) ? 'heuristic' : 'invalid']);
        }
        $message = trim((string)($_POST['message'] ?? ''));
        // Hard cap single message to avoid token blow-up (C1/H5).
        if (mb_strlen($message) > 8000) {
            $message = mb_substr($message, 0, 8000) . "\n…[truncated to 8000 chars]";
        }
        $sessionId = trim((string)($_POST['session_id'] ?? ''));
        $history = $this->sanitizeHistory($_POST['history'] ?? '[]');
        $rawApproved = $this->sanitizeApproved($_POST['approved'] ?? '[]');
        $pendingRaw = $this->sanitizePending($_POST['pending'] ?? '[]');
        $mode = strtolower(trim((string)($_POST['mode'] ?? 'plan')));
        if (!in_array($mode, ['plan', 'build'], true)) $mode = 'plan';
        $approved = $this->filterApprovedByServerPending($rawApproved, $sessionId, $uid);
        $pending = $this->validatePendingAgainstServer($pendingRaw, $sessionId, $uid);
        $pendingMode = $this->getPendingMode($sessionId, $uid);
        if (!empty($pending) && $pendingMode !== null && $pendingMode !== $mode) {
            $this->logAi('pending_mode_mismatch', ['session_id'=>substr($sessionId,0,12),'pending_mode'=>$pendingMode,'requested_mode'=>$mode]);
            $this->json(['success'=>false,'message'=>'Mode mismatch — approval was requested in '.strtoupper($pendingMode).' but you sent '.strtoupper($mode).'. Switch back to '.strtoupper($pendingMode).' to approve/deny.'], 400);
        }
        if (!empty($approved) && $pendingMode !== null && $pendingMode !== $mode) {
            $this->logAi('approved_mode_mismatch', ['session_id'=>substr($sessionId,0,12),'pending_mode'=>$pendingMode,'requested_mode'=>$mode]);
            $this->json(['success'=>false,'message'=>'Mode mismatch — pending approval is in '.strtoupper($pendingMode).' but you sent '.strtoupper($mode).'. Switch back to '.strtoupper($pendingMode).'.'], 400);
        }
        // Tightened: require ≥22 chars (brute-force 8-char no longer accepted), UUID v4 36-char preferred.
        // Accepts UUID 36 or legacy 22-char hex; rejects 8-char guessable IDs (06-01).
        if ($sessionId !== '' && !preg_match('/^[a-z0-9\-]{22,64}$/i', $sessionId)) $sessionId = '';
        // Cross-session DB: load history+context if session_id provided
        if ($sessionId !== '') {
            $dbHistory = $this->loadSessionHistory($sessionId);
            if (!empty($dbHistory)) {
                $merged = $dbHistory;
                $existingHashes = [];
                foreach ($merged as $m) {
                    $k = ($m['role'] ?? '') . ':' . ($m['tool_call_id'] ?? '') . ':' . sha1((string)($m['content'] ?? ''));
                    if (isset($m['tool_calls'])) $k .= ':' . sha1(json_encode($m['tool_calls']));
                    $existingHashes[$k] = true;
                }
                foreach ($history as $m) {
                    $h = ($m['role'] ?? '') . ':' . ($m['tool_call_id'] ?? '') . ':' . sha1((string)($m['content'] ?? ''));
                    if (isset($m['tool_calls'])) $h .= ':' . sha1(json_encode($m['tool_calls']));
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

        // Fail loudly before claiming the run slot — a missing prompt must never burn a claim or touch history.
        try {
            $earlyCtx = $_SESSION['ai_context'] ?? $this->loadSessionContext($sessionId) ?? [];
            if (!is_array($earlyCtx)) $earlyCtx = [];
            $promptForLog = $this->buildSystemPrompt($mode, $earlyCtx);
        } catch (Throwable $e) {
            $this->logAi('prompt_missing', ['mode' => $mode, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            $this->json(['success' => false, 'message' => 'AI prompt file missing: ' . $e->getMessage()], 500);
        }

        // Per-session concurrency guard — queue follow-up if a run is already active
        $runToken = sprintf('%08x-%04x-%04x-%04x-%012x', random_int(0,0xffffffff) & 0xffffffff, random_int(0,0xffff), random_int(0,0x0fff)|0x4000, random_int(0,0x3fff)|0x8000, random_int(0,0xffffff) * 65536 + random_int(0,0xffff));
        // Prefer UUIDv4 helper if available
        try { $b = random_bytes(16); $b[6]=chr((ord($b[6])&0x0f)|0x40); $b[8]=chr((ord($b[8])&0x3f)|0x80); $h=bin2hex($b); $runToken=substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20,12); } catch(Throwable $e) {}
        $claim = AiRunGuard::tryClaim($sessionId, $uid, $runToken);
        if (empty($claim['claimed'])) {
            $qid = AiRunGuard::enqueue($sessionId, $uid, $message, $history, $model, $mode);
            $pos = AiRunGuard::queuedCount($sessionId, $uid);
            $this->logAi('run_queued', ['session_id'=>$sessionId,'queue_id'=>$qid,'position'=>$pos]);
            $this->json(['success'=>true,'queued'=>true,'queue_id'=>$qid,'position'=>$pos,'message'=>'Run queued — previous turn still active. It will auto-start after current run completes.'], 409);
        }

        $startedAt = microtime(true);
        $turnsUsed = 0;

        // Snapshot context before unlocking session — needed for persistAfterRun after headers_sent and for cached tokens prompt.
        $ctxSnapshot = $_SESSION['ai_context'] ?? $this->loadSessionContext($sessionId) ?? [];
        if (!is_array($ctxSnapshot)) $ctxSnapshot = [];
        $summary = ContextBuilder::loadSummary($sessionId, $uid);

        // Register shutdown handler to persist even if PHP-FPM kills the worker (request_terminate_timeout / host buffer)
        $finalText = '';
        $usageTotal = ['prompt' => 0, 'completion' => 0, 'total' => 0, 'cost' => 0.0];
        $didWriteHtml = false;
        $didPreview = false;
        $messages = []; // will be filled below, captured by reference for shutdown
        $shutdownDone = false;
        $shutdownUserId = $uid;
        $shutdownState = ['messages' => &$messages, 'finalText' => &$finalText, 'sessionId' => $sessionId, 'model' => $model, 'mode' => $mode, 'ctxSnapshot' => $ctxSnapshot, 'startedAt' => $startedAt, 'done' => &$shutdownDone, 'userId' => $shutdownUserId, 'runToken' => $runToken];
        register_shutdown_function(function() use (&$shutdownState) {
            $e = error_get_last();
            $isFatal = $e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
            if (!$shutdownState['done']) {
                $msg = $isFatal ? 'fatal: ' . $e['message'] . ' at ' . $e['file'] . ':' . $e['line'] : 'killed: PHP worker terminated (timeout/host buffer) without done';
                @error_log(json_encode(['ts'=>date('Y-m-d H:i:s'), 'event'=>'run_killed_shutdown', 'session_id'=>substr($shutdownState['sessionId'],0,12), 'message'=>$msg, 'messages_count'=>count($shutdownState['messages'] ?? [])], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n", 3, BASE_PATH . '/logs/ai-studio.log');
                if ((getenv('AI_STUDIO_DEBUG') === '1') || (defined('AI_STUDIO_DEBUG') && AI_STUDIO_DEBUG)) {
                    @error_log(json_encode(['ts'=>date('Y-m-d H:i:s'), 'event'=>'run_killed_shutdown', 'session_id'=>substr($shutdownState['sessionId'],0,16), 'message'=>$msg, 'messages'=>array_slice($shutdownState['messages'] ?? [], -4)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) . "\n", 3, BASE_PATH . '/logs/ai-studio-debug.log');
                }
                try {
                    AiStudioController::staticPersistAfterRun($shutdownState['sessionId'], $shutdownState['messages'] ?? [], $shutdownState['model'], $shutdownState['mode'], $shutdownState['ctxSnapshot'] ?? [], (int)($shutdownState['userId'] ?? 0));
                } catch (Throwable $ignored) {}
                try { AiRunGuard::release($shutdownState['sessionId'], (int)($shutdownState['userId'] ?? 0), (string)($shutdownState['runToken'] ?? '')); } catch(Throwable $ignored) {}
            }
        });

        // Unlock session for concurrent admin tabs / GSC calls (C3).
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        @ignore_user_abort(true);
        @set_time_limit(0);

        $this->startStream();
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
                'prompt_meta' => PromptLoader::fileMeta($mode === 'build' ? 'ai-studio-build' : 'ai-studio-plan'),
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
        // Dedupe pending against history: history already persisted the approval pair
        // so merging both would produce Duplicate function_call_output for the same call_id.
        if (!empty($pending) && count($pending) === 2 && !empty($history)) {
            $pId = (string)($pending[0]['tool_calls'][0]['id'] ?? '');
            $tId = (string)($pending[1]['tool_call_id'] ?? '');
            if ($pId !== '' || $tId !== '') {
                $tail = array_slice($history, -2);
                if (json_encode($tail) === json_encode($pending)) {
                    $history = array_slice($history, 0, -2);
                } else {
                    $hasDup = false;
                    foreach ($tail as $h) {
                        if ($pId !== '' && isset($h['tool_calls'][0]['id']) && (string)$h['tool_calls'][0]['id'] === $pId) $hasDup = true;
                        if ($tId !== '' && (string)($h['tool_call_id'] ?? '') === $tId) $hasDup = true;
                    }
                    // Also scan last 6 for stray dup from client+DB merge
                    if (!$hasDup && count($history) > 2) {
                        foreach (array_slice($history, -6) as $h) {
                            if ($pId !== '' && isset($h['tool_calls'][0]['id']) && (string)$h['tool_calls'][0]['id'] === $pId) $hasDup = true;
                            if ($tId !== '' && (string)($h['tool_call_id'] ?? '') === $tId) $hasDup = true;
                        }
                    }
                    if ($hasDup) {
                        $history = array_values(array_filter($history, function($m) use ($pId, $tId) {
                            if ($pId !== '' && isset($m['tool_calls'][0]['id']) && (string)$m['tool_calls'][0]['id'] === $pId) return false;
                            if ($tId !== '' && (string)($m['tool_call_id'] ?? '') === $tId) return false;
                            return true;
                        }));
                    }
                }
            }
        }
        $messages = ContextBuilder::buildMessages($promptForLog, $summary, $history, $pending, $message, $alreadyInHistory);

        // Anti-loop guards: track repeats & read-only streaks to stop wasteful token burns
        $callCounts = [];
        $consecutiveReadOnlyTurns = 0;
        $writeTurns = 0;
        try {
            for ($turn = 1; ; $turn++) {
                if (AiRunGuard::isCancelled($sessionId, $uid, $runToken)) {
                    $this->logAi('run_cancelled', ['turn'=>$turn,'duration_ms'=>$this->elapsedMs($startedAt)]);
                    $this->sse('activity', ['text'=>'Cancelled — stopping…']);
                    $this->persistAfterRun($sessionId, $messages, $model, $mode, $ctxSnapshot);
                    // attempt summary on cancel as well
                    try { $maybe = ContextBuilder::summarizeIfNeeded($sessionId, $uid, $messages); if ($maybe) ContextBuilder::persistSummary($sessionId,$uid,$maybe); } catch(Throwable $e) {}
                    try { AiRunGuard::release($sessionId,$uid,$runToken); } catch(Throwable $e) {}
                    $shutdownDone = true;
                    $this->sse('done', ['status'=>'cancelled','text'=>$finalText,'mode'=>$mode]);
                    return;
                }
                $turnsUsed++;
                $this->sse('turn', ['number' => $turn, 'max' => 0, 'mode' => $mode]);
                $this->sse('activity', ['text' => 'Thinking… turn ' . $turn]);

                // Soft redirect: exactly 5 consecutive read-only turns in BUILD → single nudge, then leave it alone
                if ($mode === 'build' && $consecutiveReadOnlyTurns === 5) {
                    $this->logAi('waste_guard', ['turn'=>$turn,'consecutive_readonly'=>$consecutiveReadOnlyTurns,'msg'=>'5+ read-only turns — redirecting to write']);
                    $this->sse('activity', ['text' => 'Redirecting to useful write…']);
                    $hint = $consecutiveReadOnlyTurns >= 7
                        ? 'You have done ' . $consecutiveReadOnlyTurns . ' read-only turns. Redirect: pick ONE concrete improvement for the appliance & furniture buyback funnel (e.g. refine next thinnest section, add internal links via batch_update, or polish hero copy) and WRITE it now — do not re-fetch the same get_page/get_section. If you are truly done, summarize what changed.'
                        : 'You have done ' . $consecutiveReadOnlyTurns . ' read-only turns without a write. Redirect: use the data you already have — propose a single batch_update (≤5 edits) that improves conversion for Ташкент выкуп техники/мебели and execute it. Do not re-fetch.';
                    $messages[] = ['role' => 'assistant', 'content' => $finalText ?: '...'];
                    $messages[] = ['role' => 'user', 'content' => 'SYSTEM REDIRECT (BUILD): ' . $hint];
                }

                $modelStart = microtime(true);
                // Budget guard: estimate chars → tokens (~4 chars/token), drop oldest history if over 60k tokens
                // Keep tool pairs atomic — never slice between assistant(tool_calls) and its tool results.
                $estChars = array_sum(array_map(fn($m) => mb_strlen(json_encode($m, JSON_UNESCAPED_UNICODE) ?: ''), $messages));
                if ($estChars > 200000) {
                    $keep = count($messages) - (int)(($estChars - 180000)/4000);
                    $minKeep = !empty($pending) ? 8 : 6;
                    if ($keep < $minKeep) $keep = $minKeep;
                    $system = $messages[0];
                    $rest = array_slice($messages, 1);
                    $firstUser = null;
                    foreach ($rest as $m) { if (($m['role']??'')==='user') { $firstUser = $m; break; } }
                    $rest = array_slice($rest, -$keep);
                    if ($firstUser !== null) {
                        $hasFirst = false;
                        foreach ($rest as $m) { if (($m['role']??'')==='user' && ($m['content']??'')===($firstUser['content']??'')) { $hasFirst=true; break; } }
                        if (!$hasFirst) { array_unshift($rest, $firstUser); }
                    }
                    if (!empty($pending) && count($pending)===2) {
                        foreach ($pending as $p) {
                            $found=false;
                            foreach ($rest as $rm) { if (json_encode($rm)===json_encode($p)) { $found=true; break; } }
                            if (!$found) $rest[] = $p;
                        }
                    }
                    $candidate = array_merge([$system], $rest);
                    $candidate = $this->repairMessageSequence($candidate, true);
                    $messages = $candidate;
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
                $toolChoice = 'auto';
                // Repair any orphaned tool sequences before sending (prevents 500 from invalid OpenAI/Anthropic sequences)
                // Retry once on Duplicate function_call_output — feed back as repair instead of breaking loop.
                $response = null;
                $chatAttempts = 0;
                while (true) {
                    try {
                        $messages = $this->repairMessageSequence($messages);
                        $response = Opencode::chatWithTools($messages, $model, AiToolRegistry::definitionsForMode($mode), 0.5, 8192, 2, $toolChoice, $sessionId);
                        break;
                    } catch (Throwable $e) {
                        $emsg = $e->getMessage();
                        $isDup = str_contains($emsg, 'Duplicate function_call_output') || str_contains($emsg, 'Each function_call must have exactly one');
                        if ($isDup && $chatAttempts === 0) {
                            $chatAttempts++;
                            $this->logAi('retry_dup_fix', ['turn'=>$turn,'msg'=>mb_substr($emsg,0,400)]);
                            $this->sse('activity', ['text' => 'Fixing duplicate tool sequence… retrying']);
                            continue;
                        }
                        throw $e;
                    }
                }
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
                    if ($mode === 'build' && $writeTurns === 0 && !$didWriteHtml && !$didPreview) {
                        $isContinue = preg_match('/^(continue|продолжай|дальше|далее)\s*$/iu', trim($message));
                        $isAcknowledgeLoop = preg_match('/(no more confirmations|got it|понял|без подтверждений)/iu', (string)$response['content']);
                        if ($isAcknowledgeLoop || $isContinue || mb_strlen(trim((string)$response['content'])) < 400) {
                            $this->logAi('build_nudge', ['turn'=>$turn,'reason'=> $isAcknowledgeLoop ? 'ack_loop' : ($isContinue ? 'continue_no_tool' : 'no_tool_in_build'), 'content_preview'=> mb_substr((string)$response['content'],0,200)]);
                            $this->sse('activity', ['text' => 'Build nudge: forcing tool call…']);
                            $messages[] = [
                                'role' => 'assistant',
                                'content' => $response['content'],
                            ];
                            $messages[] = [
                                'role' => 'user',
                                'content' => 'SYSTEM ENFORCEMENT (BUILD MODE): You have not shipped any write yet. Call a tool now (e.g. list_sections/get_section then batch_update/update_section/patch_section). Do not ask for confirmation. If the task is genuinely done, output your summary instead and end without a tool call.',
                            ];
                            continue;
                        }
                    }
                    break;
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => $response['content'],
                    'tool_calls' => $toolCalls,
                ];

                $haltForApproval = false;

                foreach ($toolCalls as $idx => $tc) {
                    if (AiRunGuard::isCancelled($sessionId, $uid, $runToken)) {
                        $this->logAi('tool_cancelled', ['tool'=>$tc['function']['name']??'unknown','turn'=>$turn]);
                        $toolMsgId = $tc['id'] ?? sha1(($tc['function']['name']??'').':'.(string)($tc['function']['arguments']??''));
                        $messages[] = ['role'=>'tool','tool_call_id'=>$toolMsgId,'content'=>json_encode(['status'=>'cancelled','note'=>'Run cancelled by user'], JSON_UNESCAPED_UNICODE)];
                        $haltForApproval = false;
                        // break batch, let outer cancelled check handle
                        break;
                    }
                    $name = $tc['function']['name'] ?? '';
                    $rawArgs = $tc['function']['arguments'] ?? '{}';
                    $args = json_decode((string)$rawArgs, true);
                    // 03-code-bugs #3: malformed JSON must surface as explicit tool error, not silent []
                    if (!is_array($args)) {
                        $err = json_last_error_msg();
                        $toolMsgId = $tc['id'] ?? sha1($name . ':' . (string)$rawArgs);
                        $msg = 'Invalid tool arguments JSON for ' . $name . ': ' . $err . ' — request valid JSON.';
                        $this->sse('tool_result', ['tool' => $name, 'ok' => false, 'message' => $msg, 'summary' => 'Error: ' . $msg]);
                        $messages[] = ['role' => 'tool', 'tool_call_id' => $toolMsgId, 'content' => json_encode(['error' => $msg, 'code'=>'VALIDATION_ERROR','retryable'=>false, '_untrusted_tool_output'=>true], JSON_UNESCAPED_UNICODE)];
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
                        $this->savePendingApprovals($sessionId, $uid, $name, $approvalCallIds, $pendingPair, $out['plan'] ?? '', $out['reason'] ?? '', $mode);
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolMsgId,
                            'content' => json_encode([
                                'status' => 'approval_required',
                                'call_id' => $out['call_id'],
                                'plan' => $out['plan'],
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ];
                        // Prevent orphan: remaining tool_calls in this batch need synthetic results
                        for ($ri = $idx + 1; $ri < count($toolCalls); $ri++) {
                            $rem = $toolCalls[$ri];
                            $remId = $rem['id'] ?? ('skipped_' . $ri . '_' . substr($toolMsgId, 0, 8));
                            $remName = $rem['function']['name'] ?? 'unknown';
                            $messages[] = ['role' => 'tool', 'tool_call_id' => $remId, 'content' => json_encode(['skipped' => true, 'reason' => 'halted for approval of ' . $name, 'tool' => $remName], JSON_UNESCAPED_UNICODE)];
                        }
                        break; // stop the batch here — nothing runs after a guard
                    }

                    if ($out['type'] === 'error') {
                        $this->sse('tool_result', [
                            'tool' => $name,
                            'ok' => false,
                            'message' => $out['message'],
                            'summary' => 'Error [' . ($out['code'] ?? 'ERROR') . ']: ' . $out['message'],
                        ]);
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolMsgId,
                            'content' => json_encode(['error' => $out['message'], 'code'=>$out['code'] ?? 'ERROR','retryable'=>$out['retryable'] ?? false,'_untrusted_tool_output'=>true], JSON_UNESCAPED_UNICODE),
                        ];
                        continue;
                    }

                    // Strict contract: verification_failed should be surfaced as failure so model retries with fresh read
                    if (($out['status'] ?? '') === 'verification_failed') {
                        $msg = $out['result']['note'] ?? 'Verification failed — fresh read mismatch.';
                        $this->sse('tool_result', [
                            'tool' => $name,
                            'ok' => false,
                            'message' => 'VERIFICATION_FAILED: ' . $msg . ' (fresh_hash ' . ($out['result']['fresh_hash']??'') . ')',
                            'summary' => 'VERIFICATION_FAILED: ' . mb_substr($msg,0,180),
                        ]);
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolMsgId,
                            'content' => json_encode(['error'=>'VERIFICATION_FAILED: '.$msg,'code'=>'VERIFICATION_FAILED','retryable'=>true,'_untrusted_tool_output'=>true,'fresh_hash'=>$out['result']['fresh_hash']??null], JSON_UNESCAPED_UNICODE),
                        ];
                        $this->logAi('tool_verification_failed', ['tool'=>$name,'call_id'=>$toolMsgId,'fresh_hash'=>$out['result']['fresh_hash']??'']);
                        continue;
                    }

                    $this->sse('tool_result', [
                        'tool' => $name,
                        'ok' => true,
                        'summary' => $this->summarizeResult($name, $out['result']),
                    ]);

                    // Track visual writes for soft preview reminder (04-05)
                    $visualWrites = ['update_section','patch_section','insert_section','wrap_section','add_section_marker','auto_sectionize','set_section_style','batch_update','str_replace_field','set_field'];
                    if (in_array($name, $visualWrites, true) && ($out['result']['ok'] ?? ($out['type'] === 'result'))) {
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

                // Waste tracking: read-only streak & repeat same read
                $hadWriteThisTurn = false;
                foreach ($toolCalls as $tcCheck) {
                    $nm = $tcCheck['function']['name'] ?? '';
                    if (!AiToolRegistry::isPlanAllowed($nm)) { $hadWriteThisTurn = true; break; }
                }
                if ($hadWriteThisTurn) { $writeTurns++; $consecutiveReadOnlyTurns = 0; } else { $consecutiveReadOnlyTurns++; }
                foreach ($toolCalls as $tcCheck) {
                    $nm = $tcCheck['function']['name'] ?? '';
                    $ag = $tcCheck['function']['arguments'] ?? '{}';
                    $key = $nm . ':' . $ag;
                    $callCounts[$key] = ($callCounts[$key] ?? 0) + 1;
                    if ($callCounts[$key] === 3 && AiToolRegistry::isPlanAllowed($nm)) {
                        $this->logAi('repeat_guard', ['turn'=>$turn,'tool'=>$nm,'args'=>mb_substr($ag,0,200),'count'=>3]);
                        $this->sse('activity', ['text' => 'Redirect: repeated ' . $nm . ' ×3 — using cached result.']);
                        $messages[] = ['role' => 'user', 'content' => 'SYSTEM REDIRECT: Repeated ' . $nm . ' with identical args 3× — you already have this data. Reuse it; if the requested work is done, summarize and stop without further tool calls.'];
                    }
                }

                if ($haltForApproval) {
                    $this->logAi('run_end', [
                        'status' => 'awaiting_approval',
                        'turns' => $turnsUsed,
                        'duration_ms' => $this->elapsedMs($startedAt),
                    ]);
                    $this->persistAfterRun($sessionId, $messages, $model, $mode, $ctxSnapshot);
                    try { $maybe = ContextBuilder::summarizeIfNeeded($sessionId, $uid, $messages); if ($maybe) ContextBuilder::persistSummary($sessionId,$uid,$maybe); } catch(Throwable $e) {}
                    try { AiRunGuard::release($sessionId,$uid,$runToken); } catch(Throwable $e) {}
                    $shutdownDone = true;
                    $this->sse('done', ['status' => 'awaiting_approval', 'text' => $finalText, 'mode' => $mode]);
                    return;
                }
                // For non-approval runs, clean up any stale pending store if user sent approvals (approved list non-empty)
                if (!empty($approved)) {
                    $this->clearPendingApprovals($sessionId, $uid, $approved);
                } elseif (!empty($pending) && $this->isDeniedMessage($message)) {
                    $this->clearPendingApprovals($sessionId, $uid, null);
                }
            }

            // Soft preview reminder: if HTML was written but never previewed, hint the model/next turn — do not block, just log + nudge
            if ($didWriteHtml && !$didPreview && $mode === 'build') {
                $this->logAi('preview_missing', ['turns'=>$turnsUsed,'hint'=>'visual edit without render_preview/render_full_page']);
                if ($finalText !== '' && stripos($finalText, 'preview') === false) {
                    $finalText .= "\n\n[Hint: you made HTML edits without calling render_preview/render_full_page — call render_preview for the changed section(s) + render_full_page once before marking complete.]";
                }
            }
            $this->sse('done', ['status' => 'complete', 'text' => $finalText, 'mode' => $mode]);
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
            try { $maybe = ContextBuilder::summarizeIfNeeded($sessionId, $uid, $messages); if ($maybe) ContextBuilder::persistSummary($sessionId,$uid,$maybe); } catch(Throwable $e) {}
            try { AiRunGuard::release($sessionId,$uid,$runToken); } catch(Throwable $e) {}
            $shutdownDone = true;
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $isAuth = str_contains($msg, 'invalid or unauthorized') || str_contains($msg, 'API key is not configured');
            $is5xx = str_contains($msg, 'HTTP 500') || str_contains($msg, 'Internal server error');
            $logCtx = [
                'message' => $msg,
                'at' => $e->getFile() . ':' . $e->getLine(),
                'duration_ms' => $this->elapsedMs($startedAt),
            ];
            if ($isAuth) {
                $logCtx['hint'] = 'Check OPENCODE_GO_API_KEY in .env; see https://opencode.ai/zen/go';
                @unlink(BASE_PATH . '/storage/opencode_models.json');
            }
            if ($is5xx) {
                $hasFallback = trim((string)(defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : (getenv('OPENROUTER_API_KEY') ?: ''))) !== '';
                $logCtx['hint'] = $hasFallback ? 'Opencode 500 — service temporarily down, OpenRouter fallback attempted (see fallback error in message)' : 'Opencode 500 — service temporarily down, OpenRouter fallback not configured';
                $logCtx['endpoint'] = Opencode::getEndpointForModel($model);
                $logCtx['model_format'] = Opencode::getFormatForModel($model);
            }
            $this->logAi('run_error', $logCtx);
            if ($this->shouldDebug()) {
                $this->logDebug('run_error_raw', [
                    'exception' => $msg,
                    'file' => $e->getFile() . ':' . $e->getLine(),
                    'trace' => mb_substr($e->getTraceAsString(), 0, 4000),
                    'messages_snapshot' => array_slice($messages ?? [], -6),
                ]);
            }
            try { $this->persistAfterRun($sessionId, $messages, $model, $mode, $ctxSnapshot ?? []); $shutdownDone = true; } catch (Throwable $ignored) { error_log('persist on error failed: ' . $ignored->getMessage()); }
            try { AiRunGuard::release($sessionId, (int)($uid ?? 0), $runToken ?? ''); } catch (Throwable $ignored) {}
            $userMsg = $msg;
            if ($isAuth) {
                $userMsg .= "\n\nFix: open .env and set OPENCODE_GO_API_KEY from https://opencode.ai/zen/go — no quotes, no trailing spaces. Then run: rm storage/opencode_models.json and retry. If you only have an OpenRouter key, set OPENCODE_GO_API_KEY to the same value during migration.";
            } elseif ($is5xx) {
                $hasFallback = trim((string)(defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : (getenv('OPENROUTER_API_KEY') ?: ''))) !== '';
                $fallbackNote = $hasFallback ? 'OpenRouter fallback was tried (deepseek/deepseek-chat).' : 'OpenRouter fallback not configured (set OPENROUTER_API_KEY to enable).';
                $alreadyTried = str_contains($msg, 'OpenRouter fallback also failed') ? ' Fallback error included above.' : '';
                $userMsg .= "\n\nOpencode 500 — model " . $model . " (" . Opencode::getFormatForModel($model) . " via " . Opencode::getEndpointForModel($model) . ") temporarily unavailable. " . $fallbackNote . $alreadyTried . " If this persists, wait 30s and retry with " . Opencode::DEFAULT_MODEL . " or try a cheaper chat model (deepseek-v4-flash). If message sequence was corrupted (orphan tool calls), start a new session. Docs: https://opencode.ai/docs/go";
            }
            try { $this->sse('error', ['message' => $userMsg]); } catch (Throwable $ignored) {}
            try { $this->sse('done', ['status' => 'error', 'mode' => $mode ?? 'plan']); } catch (Throwable $ignored) {}
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
        header('Pragma: no-cache');
        header('X-Accel-Buffering: no');
        header('Content-Encoding: none');
        if (ini_get('zlib.output_compression')) {
            @ini_set('zlib.output_compression', '0');
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
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
        // Redact secrets from log context (tool args may contain keys)
        $ctx = $this->redactForLog($ctx);
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

    private function shouldDebug(): bool {
        if (isset($_POST['debug']) && $_POST['debug'] === '1') return true;
        if (isset($_GET['debug']) && $_GET['debug'] === '1') return true;
        if (getenv('AI_STUDIO_DEBUG') === '1') return true;
        if (defined('AI_STUDIO_DEBUG') && AI_STUDIO_DEBUG) return true;
        if (defined('APP_ENV') && APP_ENV === 'development' && (getenv('APP_ENV') !== 'production')) {
            return false;
        }
        return false;
    }

    private function redactForLog(array $ctx): array {
        $out = [];
        foreach ($ctx as $k => $v) {
            if (is_string($v)) {
                $v = preg_replace('/(Authorization:\s*)[^\n]+/i', '$1[redacted]', $v) ?? $v;
                $v = preg_replace('/((?:api[_-]?key|secret|password|token|OPENCODE_API_KEY|OPENROUTER_API_KEY|GSC_CLIENT_SECRET|GSC_ENCRYPTION_KEY|BOT_API_SECRET)\s*[:=]\s*)([^\s\n"\'`,;]+)/i', '$1[redacted]', $v) ?? $v;
                $v = preg_replace('/(sk-[a-zA-Z0-9_\-]{10,})/', '[redacted-sk]', $v) ?? $v;
                $v = preg_replace('/(Bearer\s+[a-zA-Z0-9_\-\.]+)/i', 'Bearer [redacted]', $v) ?? $v;
            } elseif (is_array($v)) {
                $v = $this->redactForLog($v);
            }
            $out[$k] = $v;
        }
        return $out;
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
        $memoryAddon = '';
        if (is_array($ctx)) {
            $lines = [];
            $chars = 0;
            foreach ($ctx as $k => $v) {
                if (!is_string($k) || $k === '' || $k[0] === '_') continue;
                if (!is_string($v) && !is_numeric($v)) continue;
                $v = (string)$v;
                if ($v === '') continue;
                $show = mb_strlen($v) > 120 ? mb_substr($v, 0, 120) . '…' : $v;
                $line = $k . ': ' . $show;
                $chars += mb_strlen($line);
                if ($chars > 800) break;
                $lines[] = $line;
            }
            if (!empty($lines)) {
                $memoryAddon = "\n\n═══ SAVED MEMORY (auto-injected — current, do NOT call list_context/get_context to re-read; only call store_context when the user says remember/pin) ═══\n" . implode("\n", $lines);
            }
        }
        if ($mode === 'plan') {
            return $this->buildPlanPrompt() . $cachedAddon . $memoryAddon;
        }
        return $this->buildBuildPrompt() . $cachedAddon . $memoryAddon;
    }

     private function buildPlanPrompt(): string {
        return PromptLoader::load('ai-studio-plan');
    }

    private function buildBuildPrompt(): string {
        return PromptLoader::load('ai-studio-build');
    }

    private function sanitizeHistory($history): array {
        $decoded = json_decode((string)$history, true);
        $messages = [];
        if (is_array($decoded)) {
            foreach ($decoded as $turn) {
                if (!is_array($turn)) continue;
                $role = in_array($turn['role'] ?? '', ['user', 'assistant', 'tool'], true) ? $turn['role'] : null;
                if ($role === 'tool' && empty($turn['tool_call_id'])) continue;
                $content = (string)($turn['content'] ?? '');
                $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);
                if (preg_match('/^\s*(system|assistant\s*\(system\)|ignore\s+previous\s+instructions)/i', $content)) {
                    $content = '[filtered system-like prefix] ' . ltrim(preg_replace('/^\s*(system|assistant\s*\(system\)|ignore\s+previous\s+instructions)[:\-]*/i', '', $content));
                }
                if (mb_strlen($content) > 4000) $content = mb_substr($content, 0, 4000) . "\n…[truncated]";
                if ($role && $content !== '') {
                    $entry = ['role' => $role, 'content' => $content];
                    if ($role === 'tool' && !empty($turn['tool_call_id'])) {
                        $tcid = substr((string)$turn['tool_call_id'], 0, 64);
                        $entry['tool_call_id'] = $tcid;
                    }
                    if ($role === 'assistant' && isset($turn['tool_calls']) && is_array($turn['tool_calls'])) {
                        $validCalls = [];
                        foreach ($turn['tool_calls'] as $tc) {
                            if (!is_array($tc) || !isset($tc['function'])) continue;
                            $id = (string)($tc['id'] ?? '');
                            $name = (string)($tc['function']['name'] ?? '');
                            $args = (string)($tc['function']['arguments'] ?? '{}');
                            if ($id === '' || $name === '' || strlen($id) > 64 || strlen($name) > 64 || strlen($args) > 65536) continue;
                            if (json_decode($args) === null && $args !== '{}' && $args !== '') continue;
                            $validCalls[] = ['id'=>$id,'type'=>'function','function'=>['name'=>$name,'arguments'=>$args]];
                        }
                        if (!empty($validCalls)) $entry['tool_calls'] = $validCalls;
                    }
                    $hashParts = $role . ':' . ($entry['tool_call_id'] ?? '') . ':' . sha1($content);
                    if (isset($entry['tool_calls'])) $hashParts .= ':' . sha1(json_encode($entry['tool_calls']));
                    $lastHash = null;
                    if (!empty($messages)) {
                        $last = end($messages);
                        $lastHash = ($last['role'] ?? '') . ':' . ($last['tool_call_id'] ?? '') . ':' . sha1($last['content'] ?? '');
                        if (isset($last['tool_calls'])) $lastHash .= ':' . sha1(json_encode($last['tool_calls']));
                    }
                    if ($hashParts === $lastHash) continue;
                    $messages[] = $entry;
                }
                if (count($messages) >= 48) break;
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
        $summary = $row['summary'] ?? null;
        $this->json(['success'=>true,'session'=>['id'=>$row['id'],'title'=>$row['title'],'model'=>$row['model'],'mode'=>$row['mode'],'history'=>is_array($history)?array_slice($history,-24):[],'context'=>is_array($context)?$context:[],'summary'=>is_string($summary)? $summary : null,'updated_at'=>$row['updated_at']]]);
    }
    public function deleteSession(string $id) {
        $this->requireAuth();
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->json(['success'=>false,'message'=>'CSRF token validation failed'], 400);
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        Database::getInstance()->query("DELETE FROM ai_sessions WHERE id = ? AND user_id = ?", [$id,$uid]);
        try { $this->ensureAiPendingTable(); Database::getInstance()->query("DELETE FROM ai_pending_approvals WHERE session_id=? AND user_id=?", [$id,$uid]); } catch (Throwable $e) {}
        try { Database::getInstance()->query("DELETE FROM ai_run_queue WHERE session_id=? AND user_id=?", [$id,$uid]); } catch(Throwable $e) {}
        $this->json(['success'=>true]);
    }

    public function cancel(string $id) {
        $this->requireAuth();
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->json(['success'=>false,'message'=>'CSRF token validation failed'],400);
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $ok = AiRunGuard::requestCancel($id, $uid);
        $this->json(['success'=>$ok, 'cancelled'=>$ok]);
    }

    public function queueStatus(string $id) {
        $this->requireAuth();
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $row = Database::getInstance()->fetchOne("SELECT run_state, cancel_requested FROM ai_sessions WHERE id=? AND user_id=?", [$id,$uid]);
        $q = AiRunGuard::queuedCount($id,$uid);
        $this->json(['success'=>true,'run_state'=>$row['run_state']??'idle','cancel_requested'=>(int)($row['cancel_requested']??0),'queued'=>$q]);
    }
    private static bool $aiSessionsTableEnsured = false;
     private function ensureAiSessionsTable(): void {
        if (self::$aiSessionsTableEnsured) return;
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
            self::$aiSessionsTableEnsured = true;
            try { AiRunGuard::ensureColumns(); } catch(Throwable $e) {}
        } catch (Throwable $e) {
            error_log('ensureAiSessionsTable failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    private static bool $aiPendingTableEnsured = false;
    private function ensureAiPendingTable(): void {
        if (self::$aiPendingTableEnsured) return;
        try {
            Database::getInstance()->query("CREATE TABLE IF NOT EXISTS ai_pending_approvals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id CHAR(36) NOT NULL,
                user_id INT NOT NULL,
                call_id VARCHAR(64) NOT NULL,
                tool VARCHAR(64) NOT NULL,
                plan TEXT,
                reason TEXT,
                pending_json JSON NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_session_call (session_id, user_id, call_id),
                INDEX idx_session_user (session_id, user_id),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            self::$aiPendingTableEnsured = true;
            try {
                $cols = Database::getInstance()->fetchAll("SHOW COLUMNS FROM ai_pending_approvals LIKE 'mode'");
                if (empty($cols)) {
                    Database::getInstance()->query("ALTER TABLE ai_pending_approvals ADD COLUMN mode ENUM('plan','build') NOT NULL DEFAULT 'plan' AFTER reason");
                }
            } catch (Throwable $ignored) {}
        } catch (Throwable $e) {
            error_log('ensureAiPendingTable failed: ' . $e->getMessage());
        }
    }

    private function savePendingApprovals(string $sessionId, int $uid, string $tool, array $callIds, array $pendingPair, string $plan, string $reason, string $mode = 'plan'): void {
        if ($sessionId === '' || $uid <= 0 || empty($callIds)) return;
        $mode = $mode === 'build' ? 'build' : 'plan';
        try {
            $this->ensureAiPendingTable();
            $pendingJson = json_encode($pendingPair, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            foreach ($callIds as $cid) {
                if (!preg_match('/^[a-f0-9]{40}$/', $cid)) continue;
                try {
                    Database::getInstance()->query(
                        "INSERT INTO ai_pending_approvals (session_id, user_id, call_id, tool, plan, reason, mode, pending_json) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE tool=VALUES(tool), plan=VALUES(plan), reason=VALUES(reason), mode=VALUES(mode), pending_json=VALUES(pending_json), created_at=NOW()",
                        [$sessionId, $uid, $cid, $tool, mb_substr($plan,0,2000), mb_substr($reason,0,500), $mode, $pendingJson]
                    );
                } catch (Throwable $e) {
                    if (str_contains($e->getMessage(), 'Unknown column') && str_contains($e->getMessage(), 'mode')) {
                        Database::getInstance()->query(
                            "INSERT INTO ai_pending_approvals (session_id, user_id, call_id, tool, plan, reason, pending_json) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE tool=VALUES(tool), plan=VALUES(plan), reason=VALUES(reason), pending_json=VALUES(pending_json), created_at=NOW()",
                            [$sessionId, $uid, $cid, $tool, mb_substr($plan,0,2000), mb_substr($reason,0,500), $pendingJson]
                        );
                    } else throw $e;
                }
            }
        } catch (Throwable $e) {
            error_log('savePendingApprovals failed: ' . $e->getMessage());
        }
    }

    private function getPendingMode(string $sessionId, int $uid): ?string {
        if ($sessionId === '' || $uid <= 0) return null;
        try {
            $this->ensureAiPendingTable();
            $row = Database::getInstance()->fetchOne("SELECT mode FROM ai_pending_approvals WHERE session_id=? AND user_id=? LIMIT 1", [$sessionId,$uid]);
            if (!$row || !isset($row['mode'])) return null;
            $m = strtolower(trim((string)$row['mode']));
            return in_array($m, ['plan','build'], true) ? $m : null;
        } catch (Throwable $e) { return null; }
    }

    private function filterApprovedByServerPending(array $approved, string $sessionId, int $uid): array {
        if (empty($approved) || $sessionId === '' || $uid <= 0) return [];
        try {
            $this->ensureAiPendingTable();
            $placeholders = implode(',', array_fill(0, count($approved), '?'));
            $params = array_merge([$sessionId, $uid], $approved);
            $rows = Database::getInstance()->fetchAll("SELECT call_id FROM ai_pending_approvals WHERE session_id=? AND user_id=? AND call_id IN ($placeholders)", $params);
            $valid = array_column($rows, 'call_id');
            return array_values(array_intersect($approved, $valid));
        } catch (Throwable $e) { return []; }
    }

    private function validatePendingAgainstServer(array $pending, string $sessionId, int $uid): array {
        if (empty($pending) || $sessionId === '' || $uid <= 0) return [];
        try {
            $this->ensureAiPendingTable();
            $row = Database::getInstance()->fetchOne("SELECT call_id FROM ai_pending_approvals WHERE session_id=? AND user_id=? LIMIT 1", [$sessionId,$uid]);
            if (!$row) return [];
            $cid = (string)($pending[0]['tool_calls'][0]['id'] ?? '');
            if ($cid !== '') {
                $exists = Database::getInstance()->fetchOne("SELECT call_id FROM ai_pending_approvals WHERE session_id=? AND user_id=? AND call_id=?", [$sessionId,$uid,$cid]);
                if (!$exists) {
                    $any = Database::getInstance()->fetchOne("SELECT call_id FROM ai_pending_approvals WHERE session_id=? AND user_id=? LIMIT 1", [$sessionId,$uid]);
                    if (!$any) return [];
                }
            }
            return $pending;
        } catch (Throwable $e) { return []; }
    }

    private function clearPendingApprovals(string $sessionId, int $uid, ?array $callIds = null): void {
        if ($sessionId === '' || $uid <= 0) return;
        try {
            $this->ensureAiPendingTable();
            if ($callIds === null) {
                Database::getInstance()->query("DELETE FROM ai_pending_approvals WHERE session_id=? AND user_id=?", [$sessionId,$uid]);
            } elseif (!empty($callIds)) {
                $ph = implode(',', array_fill(0, count($callIds), '?'));
                $params = array_merge([$sessionId,$uid], $callIds);
                Database::getInstance()->query("DELETE FROM ai_pending_approvals WHERE session_id=? AND user_id=? AND call_id IN ($ph)", $params);
            }
        } catch (Throwable $e) {}
    }

    private function isDeniedMessage(string $msg): bool {
        return str_contains($msg, '[Denied]') || stripos($msg, 'denied') !== false;
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
            if (count($history) > 24) $history = array_slice($history, -24);
            $title = '';
            foreach ($history as $m) { if (($m['role']??'')==='user' && !empty($m['content'])) { $raw = trim((string)$m['content']); $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $raw); $raw = strip_tags($raw); $raw = preg_replace('/\s+/u', ' ', $raw); $title = mb_substr($raw,0,120); break; } }
            $histJson = json_encode($history, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $ctxJson = json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $exists = Database::getInstance()->fetchOne("SELECT id, version FROM ai_sessions WHERE id = ? AND user_id = ?", [$sessionId, $uid]);
            if ($exists) {
                try {
                    Database::getInstance()->query("UPDATE ai_sessions SET history=?, context=?, model=?, mode=?, title=?, version=version+1, updated_at=NOW() WHERE id=? AND user_id=?", [$histJson,$ctxJson,$model,$mode,$title,$sessionId,$uid]);
                } catch(Throwable $e) {
                    Database::getInstance()->query("UPDATE ai_sessions SET history=?, context=?, model=?, mode=?, title=?, updated_at=NOW() WHERE id=? AND user_id=?", [$histJson,$ctxJson,$model,$mode,$title,$sessionId,$uid]);
                }
            } else {
                try {
                    Database::getInstance()->query("INSERT INTO ai_sessions (id,user_id,title,model,mode,history,context,version) VALUES (?,?,?,?,?,?,?,1)", [$sessionId,$uid,$title,$model,$mode,$histJson,$ctxJson]);
                } catch(Throwable $e) {
                    Database::getInstance()->query("INSERT INTO ai_sessions (id,user_id,title,model,mode,history,context) VALUES (?,?,?,?,?,?,?)", [$sessionId,$uid,$title,$model,$mode,$histJson,$ctxJson]);
                }
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
                        $preserve = [];
                        foreach (['hash','fresh_hash','ok','after_preview','before_chars','after_chars','page_id','lang','section','index'] as $k) {
                            if (isset($decoded[$k])) $preserve[$k] = $decoded[$k];
                        }
                        $summary = [];
                        $i = 0;
                        foreach ($decoded as $k => $v) {
                            if (isset($preserve[$k])) continue;
                            if ($i >= 6) break;
                            $summary[$k] = $v;
                            $i++;
                        }
                        $compact = [
                            '_compacted' => true,
                            'original_chars' => mb_strlen($m['content']),
                            'summary' => $summary,
                        ] + $preserve;
                        if (isset($compact['summary']['html']) && mb_strlen((string)$compact['summary']['html']) > 500) {
                            $compact['summary']['html'] = mb_substr((string)$compact['summary']['html'], 0, 200) . '…[compacted]';
                        }
                        if (isset($compact['summary']['chunk']) && mb_strlen((string)$compact['summary']['chunk']) > 500) {
                            $compact['summary']['chunk'] = mb_substr((string)$compact['summary']['chunk'], 0, 200) . '…[compacted]';
                        }
                        if (isset($compact['after_preview'])) $compact['after_preview'] = mb_substr((string)$compact['after_preview'], 0, 200);
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
        foreach ($messages as $m) {
            if (($m['role'] ?? '') !== 'tool' || empty($m['content'])) continue;
            $decoded = json_decode($m['content'], true);
            if (!is_array($decoded)) continue;
            $hasTokens = isset($decoded['tokens']) && is_array($decoded['tokens']);
            $hasDesignTokens = isset($decoded['design_tokens']) && is_array($decoded['design_tokens']);
            $hasAllTokensCount = isset($decoded['all_tokens_count']);
            if ($hasTokens || $hasDesignTokens || $hasAllTokensCount) {
                $ctx['_cached_tokens'] = $decoded;
                $ctx['_cached_tokens_at'] = time();
            }
            $isGlobalSettings = isset($decoded['site_name_ru']) || isset($decoded['phone']) && isset($decoded['email']) || isset($decoded['global_settings']);
            if ($isGlobalSettings) {
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

    public static function staticPersistAfterRun(string $sessionId, array $messages, string $model, string $mode, ?array $ctxSnapshot, int $uid): void {
        if ($sessionId === '' || $uid <= 0) return;
        try {
            $history = [];
            foreach ($messages as $m) {
                if (($m['role']??'')==='system') continue;
                if (in_array($m['role']??'', ['user','assistant','tool'], true)) $history[] = $m;
            }
            $tmp = new self();
            $history = $tmp->compactHistoryForPersist($history);
            foreach ($history as &$h) { if (isset($h['content']) && mb_strlen($h['content'])>4000) $h['content']=mb_substr($h['content'],0,4000)."\n…[truncated]"; }
            if (count($history) > 24) $history = array_slice($history,-24);
            $ctx = is_array($ctxSnapshot) ? $ctxSnapshot : [];
            $ctx = $tmp->mergeTokensIntoContext($ctx, $messages);
            $histJson = json_encode($history, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $ctxJson = json_encode($ctx, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $tmp->ensureAiSessionsTable();
            $exists = Database::getInstance()->fetchOne("SELECT id FROM ai_sessions WHERE id = ? AND user_id = ?", [$sessionId, $uid]);
            if ($exists) {
                Database::getInstance()->query("UPDATE ai_sessions SET history=?, context=?, model=?, mode=?, updated_at=NOW() WHERE id=? AND user_id=?", [$histJson,$ctxJson,$model,$mode,$sessionId,$uid]);
            } else {
                $title = '';
                foreach ($history as $m) { if (($m['role']??'')==='user' && !empty($m['content'])) { $raw = trim((string)$m['content']); $raw = strip_tags($raw); $title = mb_substr(preg_replace('/\s+/u',' ',$raw),0,120); break; } }
                Database::getInstance()->query("INSERT INTO ai_sessions (id,user_id,title,model,mode,history,context) VALUES (?,?,?,?,?,?,?)", [$sessionId,$uid,$title,$model,$mode,$histJson,$ctxJson]);
            }
        } catch (Throwable $e) { error_log('staticPersistAfterRun failed: '.$e->getMessage()); }
    }

    private function repairMessageSequence(array $messages, bool $trimMode = false): array {
        if (empty($messages)) return $messages;
        $out = [];
        $pending = [];
        $seenCallIds = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? '';
            if ($role === 'system') {
                if (!empty($pending)) {
                    foreach (array_keys($pending) as $pid) $out[] = ['role' => 'tool', 'tool_call_id' => $pid, 'content' => json_encode(['skipped'=>true,'reason'=>'orphan repair: system interrupt'])];
                    $pending = [];
                }
                $out[] = $m;
                continue;
            }
            if ($role === 'assistant' && isset($m['tool_calls']) && is_array($m['tool_calls']) && $m['tool_calls']) {
                if (!empty($pending)) {
                    foreach (array_keys($pending) as $pid) $out[] = ['role' => 'tool', 'tool_call_id' => $pid, 'content' => json_encode(['skipped'=>true,'reason'=>'orphan repair: next assistant before tool results'])];
                    $pending = [];
                }
                // Dedupe duplicate function_call ids — prevents HTTP 400 Duplicate function_call_output
                $filteredCalls = [];
                foreach ($m['tool_calls'] as $tc) {
                    $id = (string)($tc['id'] ?? '');
                    if ($id !== '' && isset($seenCallIds[$id])) continue;
                    if ($id !== '') $seenCallIds[$id] = true;
                    $filteredCalls[] = $tc;
                }
                if (empty($filteredCalls)) continue;
                $m['tool_calls'] = $filteredCalls;
                $out[] = $m;
                foreach ($m['tool_calls'] as $tc) {
                    $id = $tc['id'] ?? '';
                    if ($id !== '') $pending[$id] = true;
                }
                continue;
            }
            if ($role === 'tool') {
                $id = (string)($m['tool_call_id'] ?? '');
                if ($id !== '' && isset($seenCallIds[$id]) && !isset($pending[$id])) {
                    // Duplicate tool output for same call_id already emitted — drop.
                    continue;
                }
                if ($id !== '' && isset($pending[$id])) {
                    $out[] = $m;
                    unset($pending[$id]);
                }
                continue;
            }
            if ($role === 'user' || $role === 'assistant') {
                if (!empty($pending)) {
                    foreach (array_keys($pending) as $pid) $out[] = ['role' => 'tool', 'tool_call_id' => $pid, 'content' => json_encode(['skipped'=>true,'reason'=>'orphan repair: interrupted by '.$role])];
                    $pending = [];
                }
                $out[] = $m;
                continue;
            }
            $out[] = $m;
        }
        if (!empty($pending)) {
            foreach (array_keys($pending) as $pid) $out[] = ['role' => 'tool', 'tool_call_id' => $pid, 'content' => json_encode(['skipped'=>true,'reason'=>'orphan repair: tail'])];
        }
        if ($trimMode && !empty($out)) {
            $first = $out[0];
            if (($first['role'] ?? '') !== 'system') {
                // ensure system stays first — if trimmed away, prepend empty system won't help; just return as is
            }
            // Drop leading orphan tool if any slipped through
            while (!empty($out) && ($out[0]['role'] ?? '') === 'tool') array_shift($out);
        }
        return $out;
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
