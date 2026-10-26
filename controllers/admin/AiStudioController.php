<?php
// path: ./controllers/admin/AiStudioController.php
// AI Studio: an agent loop over the CMS admin via tools.
//   GET  /admin/ai-studio      → index()  — the chat window
//   POST /admin/ai-studio/run  → run()    — one agent turn (SSE stream)

require_once BASE_PATH . '/models/Opencode.php';
require_once BASE_PATH . '/models/ai/AiToolRegistry.php';
require_once BASE_PATH . '/models/ai/AiRunGuard.php';
require_once BASE_PATH . '/models/ai/AiSessionStore.php';
require_once BASE_PATH . '/models/ai/ContextBuilder.php';
require_once BASE_PATH . '/models/ai/PromptLoader.php';
require_once BASE_PATH . '/models/ai/SkillLoader.php';
require_once BASE_PATH . '/models/ai/AiRateLimiter.php';
require_once BASE_PATH . '/models/ai/AiPromptBuilder.php';
require_once BASE_PATH . '/services/AiHistorySanitizer.php';
require_once BASE_PATH . '/services/AiLogger.php';

class AiStudioController extends Controller {


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
        $rl = AiRateLimiter::check($uid, $ip);
        if (empty($rl['allowed'])) {
            header('Retry-After: ' . (int)($rl['retryAfter'] ?? 60));
            $this->json(['success' => false, 'message' => AiRateLimiter::MESSAGE], 429);
        }

        $model = trim((string)($_POST['model'] ?? ''));
        // Model allowlist: opencode-go/* or live catalogue match.
        // Prices come from OpenCode Go API — see Opencode::fetchModels() pricing.
        $originalModel = $model;
        $model = Opencode::normalizeModel($model);
        if ($originalModel !== '' && $originalModel !== $model) {
            $sanitized = substr(preg_replace('/[^a-z0-9\/\-\.:_]/i', '', $originalModel), 0, 80);
            AiLogger::logAi('model_fallback', ['requested' => $sanitized !== '' ? $sanitized : '[empty]', 'fallback' => $model, 'allowed_via' => Opencode::isAllowedModel($originalModel) ? 'heuristic' : 'invalid']);
        }
        $message = trim((string)($_POST['message'] ?? ''));
        // Hard cap single message to avoid token blow-up (C1/H5).
        if (mb_strlen($message) > 8000) {
            $message = mb_substr($message, 0, 8000) . "\n…[truncated to 8000 chars]";
        }
        $sessionId = trim((string)($_POST['session_id'] ?? ''));
        $history = AiHistorySanitizer::sanitizeHistory($_POST['history'] ?? '[]');
        $rawApproved = AiHistorySanitizer::sanitizeApproved($_POST['approved'] ?? '[]');
        $pendingRaw = AiHistorySanitizer::sanitizePending($_POST['pending'] ?? '[]');
        $mode = strtolower(trim((string)($_POST['mode'] ?? 'plan')));
        if (!in_array($mode, ['plan', 'build'], true)) $mode = 'plan';
        $approved = $this->filterApprovedByServerPending($rawApproved, $sessionId, $uid);
        $pending = $this->validatePendingAgainstServer($pendingRaw, $sessionId, $uid);
        $pendingMode = $this->getPendingMode($sessionId, $uid);
        if (!empty($pending) && $pendingMode !== null && $pendingMode !== $mode) {
            AiLogger::logAi('pending_mode_mismatch', ['session_id'=>substr($sessionId,0,12),'pending_mode'=>$pendingMode,'requested_mode'=>$mode]);
            $this->json(['success'=>false,'message'=>'Mode mismatch — approval was requested in '.strtoupper($pendingMode).' but you sent '.strtoupper($mode).'. Switch back to '.strtoupper($pendingMode).' to approve/deny.'], 400);
        }
        if (!empty($approved) && $pendingMode !== null && $pendingMode !== $mode) {
            AiLogger::logAi('approved_mode_mismatch', ['session_id'=>substr($sessionId,0,12),'pending_mode'=>$pendingMode,'requested_mode'=>$mode]);
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
            $promptForLog = AiPromptBuilder::buildSystemPrompt($mode, $earlyCtx);
        } catch (Throwable $e) {
            AiLogger::logAi('prompt_missing', ['mode' => $mode, 'error' => mb_substr($e->getMessage(), 0, 200)]);
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
            AiLogger::logAi('run_queued', ['session_id'=>$sessionId,'queue_id'=>$qid,'position'=>$pos]);
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
        AiLogger::logAi('run_start', [
            'model' => $model,
            'mode' => $mode,
            'message_len' => mb_strlen($message),
            'history_turns' => count($history),
            'approved_count' => count($approved),
            'pending_count' => count($pending),
            'prompt_chars' => mb_strlen($promptForLog),
        ]);
        if ($this->shouldDebug()) {
            AiLogger::logDebug('prompt_raw', [
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
                    AiLogger::logAi('run_cancelled', ['turn'=>$turn,'duration_ms'=>$this->elapsedMs($startedAt)]);
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
                    AiLogger::logAi('waste_guard', ['turn'=>$turn,'consecutive_readonly'=>$consecutiveReadOnlyTurns,'msg'=>'5+ read-only turns — redirecting to write']);
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
                    AiLogger::logDebug('turn_request', [
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
                            AiLogger::logAi('retry_dup_fix', ['turn'=>$turn,'msg'=>mb_substr($emsg,0,400)]);
                            $this->sse('activity', ['text' => 'Fixing duplicate tool sequence… retrying']);
                            continue;
                        }
                        throw $e;
                    }
                }
                if ($this->shouldDebug()) {
                    AiLogger::logDebug('turn_response', [
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

                AiLogger::logAi('model_turn', [
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
                            AiLogger::logAi('build_nudge', ['turn'=>$turn,'reason'=> $isAcknowledgeLoop ? 'ack_loop' : ($isContinue ? 'continue_no_tool' : 'no_tool_in_build'), 'content_preview'=> mb_substr((string)$response['content'],0,200)]);
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
                        AiLogger::logAi('tool_cancelled', ['tool'=>$tc['function']['name']??'unknown','turn'=>$turn]);
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
                        AiLogger::logAi('tool_call', ['name' => $name, 'call_id' => $toolMsgId, 'type' => 'error', 'args' => mb_substr((string)$rawArgs, 0, 500), 'error' => 'malformed_json']);
                        continue;
                    }

                    $this->sse('activity', ['text' => 'Running ' . $name . '…']);

                    $toolStart = microtime(true);
                    $out = AiToolRegistry::execute($name, $args, $approved, $mode);
                    $toolMs = (int)round((microtime(true) - $toolStart) * 1000);
                    // 03-code-bugs #4: deterministic call_id fallback to avoid collisions when model omits id
                    $toolMsgId = $tc['id'] ?? $out['call_id'] ?? AiToolRegistry::callId($name, $args);

                    AiLogger::logAi('tool_call', [
                        'name' => $name,
                        'call_id' => $out['call_id'] ?? $toolMsgId,
                        'type' => $out['type'],
                        'args' => mb_substr((string)json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 500),
                        'duration_ms' => $toolMs,
                    ]);
                    if ($this->shouldDebug()) {
                        AiLogger::logDebug('tool_result_raw', [
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
                        AiLogger::logAi('approval_requested', [
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
                        AiLogger::logAi('tool_verification_failed', ['tool'=>$name,'call_id'=>$toolMsgId,'fresh_hash'=>$out['result']['fresh_hash']??'']);
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
                        AiLogger::logAi('repeat_guard', ['turn'=>$turn,'tool'=>$nm,'args'=>mb_substr($ag,0,200),'count'=>3]);
                        $this->sse('activity', ['text' => 'Redirect: repeated ' . $nm . ' ×3 — using cached result.']);
                        $messages[] = ['role' => 'user', 'content' => 'SYSTEM REDIRECT: Repeated ' . $nm . ' with identical args 3× — you already have this data. Reuse it; if the requested work is done, summarize and stop without further tool calls.'];
                    }
                }

                if ($haltForApproval) {
                    AiLogger::logAi('run_end', [
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
                AiLogger::logAi('preview_missing', ['turns'=>$turnsUsed,'hint'=>'visual edit without render_preview/render_full_page']);
                if ($finalText !== '' && stripos($finalText, 'preview') === false) {
                    $finalText .= "\n\n[Hint: you made HTML edits without calling render_preview/render_full_page — call render_preview for the changed section(s) + render_full_page once before marking complete.]";
                }
            }
            $this->sse('done', ['status' => 'complete', 'text' => $finalText, 'mode' => $mode]);
            AiLogger::logAi('run_end', [
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
            AiLogger::logAi('run_error', $logCtx);
            if ($this->shouldDebug()) {
                AiLogger::logDebug('run_error_raw', [
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

    // ------------------------------------------------------------------
    // Sessions DB (cross-session persistence)
    // ------------------------------------------------------------------
    public function sessions() {
        $this->requireAuth();
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $rows = AiSessionStore::listSessions($uid);
        $this->json(['success'=>true,'sessions'=>$rows]);
    }
    public function session(string $id) {
        $this->requireAuth();
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $row = AiSessionStore::getSession($id, $uid);
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
        AiSessionStore::deleteSession($id, $uid);
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
        $st = AiSessionStore::queueStatus($id, $uid);
        $this->json(['success'=>true,'run_state'=>$st['run_state'],'cancel_requested'=>(int)$st['cancel_requested'],'queued'=>$st['queued']]);
    }
    private function ensureAiSessionsTable(): void {
        AiSessionStore::ensureSessionsTable();
    }

    private function ensureAiPendingTable(): void {
        AiSessionStore::ensurePendingTable();
    }

    private function savePendingApprovals(string $sessionId, int $uid, string $tool, array $callIds, array $pendingPair, string $plan, string $reason, string $mode = 'plan'): void {
        AiSessionStore::savePending($sessionId, $uid, $tool, $callIds, $pendingPair, $plan, $reason, $mode);
    }

    private function getPendingMode(string $sessionId, int $uid): ?string {
        return AiSessionStore::pendingMode($sessionId, $uid);
    }

    private function filterApprovedByServerPending(array $approved, string $sessionId, int $uid): array {
        return AiSessionStore::filterApproved($sessionId, $uid, $approved);
    }

    private function validatePendingAgainstServer(array $pending, string $sessionId, int $uid): array {
        return AiSessionStore::validatePending($sessionId, $uid, $pending);
    }

    private function clearPendingApprovals(string $sessionId, int $uid, ?array $callIds = null): void {
        AiSessionStore::clearPending($sessionId, $uid, $callIds);
    }

    private function isDeniedMessage(string $msg): bool {
        return str_contains($msg, '[Denied]') || stripos($msg, 'denied') !== false;
    }
    private function loadSessionHistory(string $sessionId): array {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        return AiSessionStore::loadHistory($sessionId, $uid);
    }
    private function loadSessionContext(string $sessionId): array {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        return AiSessionStore::loadContext($sessionId, $uid);
    }
    private function persistSession(string $sessionId, array $history, array $context, string $model, string $mode): void {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        AiSessionStore::saveSession($sessionId, $uid, $history, $context, $model, $mode);
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
        $ctx = $this->mergeWorkingSetIntoContext($ctx, $messages);
        $uid = (int)($_SESSION['user_id'] ?? 0);
        AiSessionStore::saveSession($sessionId, $uid, $history, is_array($ctx) ? $ctx : [], $model, $mode);
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
            // Keep cached tokens/settings + working set + last 15 keys
            $keep = ['_cached_tokens','_cached_tokens_at','_cached_global_settings','_cached_global_settings_at',
                '_pages','_pages_total','_page_slugs','_sections','_sec_hash','_gsc','_gsc_pages','_queries','_stats','_underperforming','_links','_sql','_serp','_search','_writes'];
            $newCtx = [];
            foreach ($keep as $k) if (isset($ctx[$k])) $newCtx[$k] = $ctx[$k];
            $others = array_diff_key($ctx, array_flip($keep));
            if (count($others) > 15) $others = array_slice($others, -15, null, true);
            $ctx = array_merge($newCtx, $others);
        }
        return $ctx;
    }

    /**
     * Session working set: numbers-only facts extracted from this run's tool
     * results (pages seen, GSC/analytics snapshots, section hashes, writes).
     * Rendered as SESSION STATE in the next run's prompt so the model reuses
     * data instead of re-fetching it. Never stores HTML.
     */
    private function mergeWorkingSetIntoContext(array $ctx, array $messages): array {
        $ctx = $this->mergeTokensIntoContext($ctx, $messages);
        $calls = [];
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'assistant' && isset($m['tool_calls']) && is_array($m['tool_calls'])) {
                foreach ($m['tool_calls'] as $tc) {
                    $id = (string)($tc['id'] ?? '');
                    $nm = (string)($tc['function']['name'] ?? '');
                    if ($id === '' || $nm === '') continue;
                    $ag = json_decode((string)($tc['function']['arguments'] ?? '{}'), true);
                    $calls[$id] = [$nm, is_array($ag) ? $ag : []];
                }
            }
            if (($m['role'] ?? '') !== 'tool' || empty($m['content'])) continue;
            $meta = $calls[(string)($m['tool_call_id'] ?? '')] ?? null;
            if ($meta === null) continue;
            $decoded = json_decode((string)$m['content'], true);
            if (!is_array($decoded) || isset($decoded['error']) || isset($decoded['skipped'])) continue;
            $this->accumulateWorkingSet($ctx, $meta[0], $meta[1], $decoded);
        }
        $caps = ['_pages' => 15, '_page_slugs' => 100, '_sections' => 15, '_sec_hash' => 30, '_gsc_pages' => 15, '_queries' => 8, '_stats' => 15, '_monthly' => 1, '_links' => 15, '_sql' => 5, '_serp' => 5, '_search' => 10, '_writes' => 10];
        foreach ($caps as $k => $max) {
            if (isset($ctx[$k]) && is_array($ctx[$k]) && count($ctx[$k]) > $max) {
                $ctx[$k] = array_slice($ctx[$k], -$max, null, true);
            }
        }
        return $ctx;
    }

    private function accumulateWorkingSet(array &$ctx, string $name, array $args, array $r): void {
        switch ($name) {
            case 'list_pages':
                if (isset($r['pages']) && is_array($r['pages'])) {
                    $slugs = [];
                    foreach ($r['pages'] as $p) {
                        if (is_array($p) && isset($p['slug'])) $slugs[] = (string)$p['slug'];
                    }
                    $ctx['_page_slugs'] = array_values(array_unique($slugs));
                    $ctx['_pages_total'] = (int)($r['count'] ?? count($slugs));
                }
                break;
            case 'get_page':
                $slug = (string)($r['slug'] ?? $args['slug'] ?? '');
                if ($slug === '') break;
                $chars = mb_strlen((string)($r['content_ru'] ?? '')) + mb_strlen((string)($r['content_uz'] ?? ''));
                $ctx['_pages'][$slug] = ['id' => (int)($r['id'] ?? 0), 't' => mb_substr((string)($r['title_ru'] ?? ''), 0, 60), 'c' => $chars];
                break;
            case 'search_content':
                $ctx['_search'][(string)($r['query'] ?? '')] = (int)($r['count'] ?? 0);
                break;
            case 'list_sections':
                $key = (int)($r['page_id'] ?? 0) . ':' . (string)($r['lang'] ?? '');
                $names = [];
                foreach (array_slice($r['sections'] ?? [], 0, 10) as $s) {
                    if (is_array($s) && isset($s['name'])) $names[] = mb_substr((string)$s['name'], 0, 30);
                }
                $ctx['_sections'][$key] = ['n' => (int)($r['count'] ?? count($names)), 'names' => $names];
                break;
            case 'get_section':
                $key = (int)($r['page_id'] ?? 0) . ':' . (string)($r['lang'] ?? '') . ':' . (string)($r['name'] ?? '');
                if (isset($r['hash'])) $ctx['_sec_hash'][$key] = ['h' => (string)$r['hash'], 'c' => (int)($r['chars'] ?? 0)];
                break;
            case 'get_gsc_overview':
                $ctx['_gsc'] = ['d' => (int)($r['days'] ?? 0), 'imp' => (int)($r['impressions'] ?? 0), 'clk' => (int)($r['clicks'] ?? 0), 'ctr' => (float)($r['ctr_percent'] ?? 0), 'pos' => (float)($r['avg_position'] ?? 0)];
                break;
            case 'get_page_gsc':
                $slug = (string)($r['slug'] ?? '');
                if ($slug !== '' && isset($r['totals']) && is_array($r['totals'])) {
                    $t = $r['totals'];
                    $ctx['_gsc_pages'][$slug] = ['imp' => (int)($t['impressions'] ?? 0), 'clk' => (int)($t['clicks'] ?? 0), 'ctr' => (float)($t['ctr_percent'] ?? 0), 'pos' => (float)($t['avg_position'] ?? 0)];
                }
                break;
            case 'get_gsc_queries':
                $out = [];
                foreach (array_slice($r['queries'] ?? [], 0, 8) as $q) {
                    if (is_array($q) && isset($q['query'])) $out[] = mb_substr((string)$q['query'], 0, 60) . ' (' . (int)($q['impressions'] ?? 0) . ')';
                }
                if (!empty($out)) $ctx['_queries'] = $out;
                break;
            case 'get_gsc_pages':
                foreach (array_slice($r['pages'] ?? [], 0, 15) as $p) {
                    if (!is_array($p) || !isset($p['slug'])) continue;
                    $ctx['_gsc_pages'][(string)$p['slug']] = ['imp' => (int)($p['impressions'] ?? 0), 'clk' => (int)($p['clicks'] ?? 0), 'ctr' => (float)($p['ctr_percent'] ?? 0), 'pos' => (float)($p['avg_position'] ?? 0)];
                }
                break;
            case 'search_gsc_queries':
                $ctx['_search'][(string)($r['term'] ?? '')] = (int)($r['count'] ?? 0);
                break;
            case 'get_top_pages':
                foreach ($r['pages'] ?? [] as $p) {
                    if (!is_array($p) || !isset($p['slug'])) continue;
                    $ctx['_stats'][(string)$p['slug']] = ['v' => (int)($p['visits'] ?? 0), 'c' => (int)($p['phone_calls'] ?? 0)];
                }
                break;
            case 'get_monthly_summary':
                if (isset($r['totals']) && is_array($r['totals'])) {
                    $ctx['_monthly'] = ['m' => (int)($r['months'] ?? 0), 'v' => (int)($r['totals']['visits'] ?? 0), 'c' => (int)($r['totals']['phone_calls'] ?? 0)];
                }
                foreach ($r['top'] ?? [] as $p) {
                    if (!is_array($p) || !isset($p['slug'])) continue;
                    $ctx['_stats'][(string)$p['slug']] = ['v' => (int)($p['visits'] ?? 0), 'c' => (int)($p['phone_calls'] ?? 0)];
                }
                break;
            case 'get_page_stats':
                $slug = (string)($r['slug'] ?? '');
                if ($slug !== '' && isset($r['totals']) && is_array($r['totals'])) {
                    $ctx['_stats'][$slug] = ['v' => (int)($r['totals']['visits'] ?? 0), 'c' => (int)($r['totals']['phone_calls'] ?? 0)];
                }
                break;
            case 'get_underperforming_pages':
                $slugs = [];
                foreach (array_slice($r['pages'] ?? [], 0, 8) as $p) {
                    if (is_array($p) && isset($p['slug'])) $slugs[] = (string)$p['slug'];
                }
                $ctx['_underperforming'] = ['m' => (int)($r['months'] ?? 0), 'n' => (int)($r['count'] ?? 0), 'slugs' => $slugs];
                break;
            case 'get_internal_links':
                $slug = (string)($r['slug'] ?? '');
                if ($slug !== '') {
                    $ctx['_links'][$slug] = ['in' => count($r['inbound'] ?? []), 'out' => count($r['outbound'] ?? [])];
                }
                break;
            case 'run_analytics_query':
            case 'query_builder':
                $sql = (string)($r['sql'] ?? '');
                if ($sql === '') break;
                $top = [];
                foreach (array_slice($r['rows'] ?? [], 0, 4) as $row) {
                    $j = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($j !== false) $top[] = mb_substr($j, 0, 150);
                }
                $ctx['_sql'][substr(sha1($sql), 0, 8)] = ['q' => mb_substr(preg_replace('/\s+/u', ' ', $sql) ?? $sql, 0, 80), 'n' => (int)($r['count'] ?? 0), 'top' => $top];
                break;
            case 'serp_search':
                $domains = [];
                foreach (array_slice($r['organic'] ?? [], 0, 3) as $o) {
                    if (is_array($o) && isset($o['domain'])) $domains[] = (string)$o['domain'];
                }
                $ctx['_serp'][(string)($r['query'] ?? '')] = ['domains' => $domains];
                break;
            case 'serp_niche_overview':
                $ctx['_serp'][(string)($r['query'] ?? '')] = [
                    'related' => array_slice($r['related_searches'] ?? [], 0, 5),
                    'domains' => array_column(array_slice($r['top_domains'] ?? [], 0, 5), 'domain'),
                ];
                break;
            case 'update_section': case 'patch_section': case 'str_replace_field': case 'set_field':
            case 'insert_section': case 'wrap_section': case 'batch_update': case 'set_custom_css':
            case 'set_page_theme': case 'set_page_style': case 'set_section_style': case 'auto_sectionize':
                if (!isset($r['ok']) || $r['ok'] !== true) break;
                $page = (string)($r['slug'] ?? $args['slug'] ?? $args['page_id'] ?? '?');
                $sec = (string)($r['section'] ?? '');
                if ($sec === '' && $name === 'batch_update' && isset($args['operations']) && is_array($args['operations'])) {
                    $ops = [];
                    foreach (array_slice($args['operations'], 0, 3) as $op) {
                        if (is_array($op)) $ops[] = ($op['op'] ?? '?') . ':' . ($op['section'] ?? ($op['field'] ?? ''));
                    }
                    $sec = implode(',', $ops);
                }
                $ctx['_writes'][] = ['p' => $page, 's' => mb_substr($sec, 0, 80), 'h' => (string)($r['fresh_hash'] ?? ''), 'v' => !empty($r['verified']) ? 1 : 0];
                break;
        }
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
            $ctx = $tmp->mergeWorkingSetIntoContext($ctx, $messages);
            AiSessionStore::saveAfterRun($sessionId, $uid, $history, $ctx, $model, $mode);
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
        if (is_array($result) && isset($result['markdown']) && is_string($result['markdown'])) {
            $md = $result['markdown'];
            return mb_strlen($md) > 600 ? mb_substr($md, 0, 600) . '…[truncated]' : $md;
        }
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
