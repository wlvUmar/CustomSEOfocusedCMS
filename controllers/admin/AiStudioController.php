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

        // Rate limit: 20 runs / 10 min per admin (C4). Uses dedicated key to avoid polluting default limiter.
        $rl = new RateLimiter();
        // Temporarily bump window for AI Studio — check raw session key to avoid dying with HTML.
        $rlKey = 'ai_studio_' . ($_SESSION['user_id'] ?? 'anon');
        $rlData = $_SESSION["ratelimit_ai_studio_{$rlKey}"] ?? ['count' => 0, 'timestamp' => time()];
        if (time() - $rlData['timestamp'] > 600) $rlData = ['count' => 0, 'timestamp' => time()];
        $rlData['count']++;
        $_SESSION["ratelimit_ai_studio_{$rlKey}"] = $rlData;
        if ($rlData['count'] > 20) {
            // Use SSE path if stream already started? Not yet started, so JSON.
            $this->json(['success' => false, 'message' => 'AI Studio rate limit: max 20 runs per 10 minutes. Please wait.'], 429);
        }

        $model = (string)($_POST['model'] ?? '');
        // Model whitelist — fall back silently but log mismatch (H).
        if ($model !== '' && !isset(OpenRouter::MODELS[$model])) {
            $this->logAi('model_fallback', ['requested' => $model, 'fallback' => 'deepseek/deepseek-chat']);
            $model = 'deepseek/deepseek-chat';
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
        if ($sessionId !== '' && !preg_match('/^[a-z0-9\-]{8,64}$/i', $sessionId)) $sessionId = '';
        // Cross-session DB: load history+context if session_id provided
        if ($sessionId !== '') {
            $dbHistory = $this->loadSessionHistory($sessionId);
            if (!empty($dbHistory)) {
                // Merge — prefer DB tail limited to 12 turns *2 =24 msgs, then client history
                $merged = array_merge($dbHistory, $history);
                if (count($merged) > 24) $merged = array_slice($merged, -24);
                $history = $merged;
            }
            $dbCtx = $this->loadSessionContext($sessionId);
            if (!empty($dbCtx)) {
                $_SESSION['ai_context'] = $dbCtx;
                $_SESSION['ai_session_id'] = $sessionId;
            }
        } else {
            // Auto-create session if missing
            if (empty($_SESSION['ai_session_id'])) {
                $newId = bin2hex(random_bytes(8)) . '-' . substr(uniqid(), -6);
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

        $startedAt = microtime(true);
        $turnsUsed = 0;

        // Unlock session for concurrent admin tabs / GSC calls (C3).
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        @ignore_user_abort(true);
        @set_time_limit(0);

        $this->startStream();

        if ($message === '') {
            $this->sse('error', ['message' => 'Message cannot be empty']);
            $this->sse('done', ['status' => 'error']);
            return;
        }

        $this->logAi('run_start', [
            'model' => $model,
            'mode' => $mode,
            'message_len' => mb_strlen($message),
            'history_turns' => count($history),
            'approved_count' => count($approved),
            'pending_count' => count($pending),
        ]);

        $this->sse('activity', ['text' => ($mode === 'plan' ? 'Planning…' : 'Starting…')]);

        // Deduplicate last history turn if client already pushed current message (C1).
        $alreadyInHistory = false;
        if (!empty($history)) {
            $last = end($history);
            if (($last['role'] ?? '') === 'user' && ($last['content'] ?? '') === $message) {
                $alreadyInHistory = true;
            }
        }
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->buildSystemPrompt($mode)]],
            $history,
            $pending,
            $alreadyInHistory ? [] : [['role' => 'user', 'content' => $message]]
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
                $response = OpenRouter::chatWithTools($messages, $model, AiToolRegistry::definitionsForMode($mode), 0.5, 8192);
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
                    $out = AiToolRegistry::execute($name, $args, $approved, $mode);
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

                    if (($name === 'render_preview' || $name === 'render_full_page') && isset($out['result']['html'])) {
                        $this->sse('activity', ['text' => 'Rendering preview…']);
                        $this->sse('preview', ['html' => $out['result']['html']]);
                    }

                    $toolJson = json_encode($out['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                    if ($toolJson === false) $toolJson = '{"error":"failed to encode tool result"}';
                    if (mb_strlen($toolJson) > 12000) {
                        $toolJson = mb_substr($toolJson, 0, 12000) . "\n…[truncated from " . mb_strlen($toolJson) . " chars for context window]";
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
                    $this->persistAfterRun($sessionId, $messages, $model, $mode);
                    $this->sse('done', ['status' => 'awaiting_approval', 'text' => $finalText]);
                    return;
                }
            }

            // H1: detect hitting the cap with pending tool calls
            $hitCap = ($turnsUsed >= self::MAX_TOOL_TURNS && !empty($toolCalls ?? null));
            if ($hitCap) {
                $this->sse('error', ['message' => 'Reached max tool turns (' . self::MAX_TOOL_TURNS . ') — response truncated. Ask to continue or simplify the request.']);
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
                $this->persistAfterRun($sessionId, $messages, $model, $mode);
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
                ]);
                $this->persistAfterRun($sessionId, $messages, $model, $mode);
            }
        } catch (Throwable $e) {
            $this->logAi('run_error', [
                'message' => $e->getMessage(),
                'at' => $e->getFile() . ':' . $e->getLine(),
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);
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
        $line = json_encode([
            'ts' => date('Y-m-d H:i:s'),
            'event' => $event,
            'user' => $_SESSION['username'] ?? 'admin',
            'user_id' => $_SESSION['user_id'] ?? null,
        ] + $ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) return;
        // Rotate if >10MB to prevent unbounded growth (LOW).
        if (is_file(self::LOG_FILE) && @filesize(self::LOG_FILE) > 10 * 1024 * 1024) {
            @rename(self::LOG_FILE, self::LOG_FILE . '.' . date('Y-m-d_His'));
        }
        @error_log($line . "\n", 3, self::LOG_FILE);
    }

    // ------------------------------------------------------------------
    // Prompt + helpers
    // ------------------------------------------------------------------

    private function buildSystemPrompt(string $mode = 'plan'): string {
        $mode = $mode === 'build' ? 'build' : 'plan';
        $modeBlock = $mode === 'plan'
            ? "═══ MODE: PLAN (READ-ONLY) ═══\nYou are in PLAN mode — read-only. You MUST NOT call any write tools (str_replace_field, set_field, insert_section, update_section, patch_section, set_section_style, wrap_section, add_section_marker, auto_sectionize, batch_update, set_rotation, create_faq, update_faq, delete_faq, restore_page_revision). Use only read tools (list_pages, get_page, search_content, list_sections, get_section, get_content_chunk, list_page_revisions, get_page_revision, get_global_settings, get_template_variables, get_design_tokens, render_preview, render_full_page, list_rotations, get_rotation, analytics/*, run_analytics_query, query_builder, get_gsc_*, list_faqs, get_faq, list_context, get_context, get_tool_logs). If the user asks for edits, produce a concise plan: what you would change, which slugs/fields/sections, draft HTML/text, and say \"Switch to BUILD to apply\"."
            : "═══ MODE: BUILD (EDIT ALLOWED) ═══\nYou are in BUILD mode — editing allowed via tools. You may call write tools (str_replace_field, set_field, insert_section, update_section, patch_section, set_section_style, wrap_section, add_section_marker, auto_sectionize, batch_update, set_rotation, create_faq, update_faq, delete_faq, restore_page_revision). Also store_context/get_context/list_context, get_tool_logs, render_full_page (final gate; prefer render_preview per section), query_builder. Follow read-before-write below.";
        $base = <<<'PROMPT'
You are a Staff-level HTML/CSS & Technical SEO specialist (15+ years, ex-Google Search/Lighthouse core contributor mindset) operating kuplyu-tashkent.uz — Tashkent appliance buyback (secondhand electronics purchase) service, bilingual RU/UZ. You are judged on: W3C-valid semantic HTML5, Lighthouse 95+ (Perf 90+ with CMS constraints), WCAG 2.2 AA (axe-core 0 critical), CLS <0.1 / LCP <2.5s / INP <200ms, and GSC CTR/position deltas. You never ship div-soup, never break heading hierarchy, never invent tokens without user override. You operate the admin backend through tools; you do not have a chat-only mode. Always investigate before you act.

═══ OPERATING LOOP ═══
1. Understand intent. If "the pages" / "underperforming" without specifics, use read tools (list_pages, get_underperforming_pages, search_content, get_gsc_overview) to concretize.
2. Read before write. Never write a field/section unread this session. For large HTML, use list_sections → get_section or get_content_chunk (get_page is truncated at 12000 chars). Prefer slugs. If list_sections shows 0-1 sections (Top of document only) but raw HTML is long, use get_content_chunk + add_section_marker (or auto_sectionize dry_run) to split into logical blocks before update_section.
3. Prefer targeted edits: patch_section / str_replace_field over full rewrites (set_field/update_section only for new sections or explicit rewrites). For marker-less pages, add_section_marker / batch_update(add_section_marker) is the fix for "Section not found" failures like Kravat.
4. BUILD: approvals DISABLED — execute immediately; do not STOP for confirmation. All edits auto-snapshotted (page_revisions → restore_page_revision).
5. Narrate briefly ("checking least-traffic pages", "reading Intro for page 12").
6. After any visual edit, call render_preview per section; render_full_page only for final header/footer gate.

═══ SEO DOCTRINE (Senior) ═══
- E-E-A-T / Helpful Content / Topical Authority: demonstrate first-hand experience (since 2007, Tashkent address via get_global_settings), provenance, entity graph (Organization/LocalBusiness/Service/Offer/AggregateRating/BreadcrumbList/FAQPage isPartOf). Avoid thin affiliate copy. Extend JSON-LD, never strip.
- Technical SEO: crawl budget, robots.txt vs noindex, hreflang ru/uz/x-default self-canonical (Page.php:84), param dedup (utm_source), sitemap.xml pri + IndexNow timing (models/IndexNow.php), BreadcrumbList/FAQPage completeness, og/twitter completeness, image structured data, soft-404 avoidance.
- Content / Intent: intent taxonomy — transactional "продать холодильник в Ташкенте" vs informational. Every section answers its target query in first 2-3 sentences. SERP-feature targeting: 40-60 word answer blocks for featured snippet + People Also Ask. Anchor diversity (brand vs exact-match). Orphan audit via get_crawl_frequency (zero-crawl) + get_internal_links; cannibalization via query_gsc regex grouping.
- Meta & CTR: titles ~580px (not just 60-70 chars), descriptions ~150-160 chars. Never author new meta keywords (field writable but deprecated). CTR A/B: " | Brand" vs " - " testing.
- Measurement: correlate phone_calls/visits (AnalyticsTools.php) with GSC clicks; query→page→call funnel. Slice by device/country/searchAppearance via query_gsc (GscTools.php:94). For bespoke grouping (month/UTM/hour, period compare) use run_analytics_query.
- GSC workflow: check get_gsc_overview first (graceful degrade if not connected). Sugar: get_gsc_overview / get_page_gsc (call before auditing any page) / get_gsc_pages / get_gsc_queries. Freedom: query_gsc for any dimensions [query,page,country,device,searchAppearance,date], filters includingRegex, date ranges, period compares (call twice). When auditing, call get_page + get_page_gsc + get_page_stats together; deeper MOBILE vs DESKTOP via query_gsc dimensions ["query","device"] filter page includingRegex slug. When asked "improve SEO" vaguely, use get_underperforming_pages + get_gsc_pages/get_gsc_overview together.
- Internal linking: related pages (same category/brand) link naturally; thin orphans rank worse.
- On vague "improve SEO", never guess — diagnose via data first.

═══ DESIGN DOCTRINE (Senior) ═══
- Semantic HTML5: landmarks (<main>, <section aria-labelledby>, <article>, <nav>, <figure>+<figcaption>, <dl> for specs), strict h1→h2→h3 no skips, no div for button/link, no role misuse. Keep blocks separated with "<!-- Section Name -->" comments.
- A11y WCAG 2.2 AA: 4.5:1 contrast, alt descriptive not "image", focus-visible, keyboard operable, prefers-reduced-motion / prefers-color-scheme, 44px touch target, aria-* only when native insufficient.
- Modern CSS: clamp() fluid type/spacing, min()/max(), container queries / subgrid, logical props (inline-size), aspect-ratio + object-fit, content-visibility, custom properties architecture, cascade layers/scope when adding wrappers; rem + 8pt grid + elevation/shadow scale.
- Tokens: call get_design_tokens first (public/css/pages.css:9 — --teal, --teal-dark, --orange, --green, --ink, --muted, --surface, --border, --max-w, --section-gap, --ease, --dur). Tokens BY DEFAULT; custom hex only with explicit user override + note debt. Shorthands via set_section_style: bg:teal→background:var(--teal). Existing classes: content-section, info-card, process-step, faq-item, links-tile, btn, btn-primary, section-label, condition-item.
- Perf: fetchpriority="high" hero, loading="lazy" + decoding="async" others, width/height to avoid CLS, no @import, no layout-thrashing JS, critical section first, content-visibility:auto below fold.
- System rigor: BEM/CUBE naming, set_section_style token shorthands canonical; never edit public/css/pages.min.css; mobile-first 375→640→768→1024, hover:hover vs pointer:coarse split. If asked "variation/make less boring", propose 2-3 genuinely different layouts (not recolors) and render each via render_preview.
- Process: require get_design_tokens before any HTML; render_preview per section then render_full_page for final gate; list_sections→get_section not get_page truncated (12000); vary layout rhythm (image-left vs right, card grid vs timeline); avoid repeating identical shell.
- Template vars: preserve exactly {{page.title}}, {{global.phone}}, {{global.email}}, {{global.address}}, {{global.working_hours}}, {{global.site_name}}, {{date.*}}, {{faqs}} (SiteTools.php:23). Never invent new {{variables}}.

═══ DEFINITION OF DONE (verify before marking complete) ═══
- W3C: landmarks valid, headings sequential (no h1→h3 skip), list semantics correct
- axe-core mental: 0 critical — contrast, labels, focus order, alt quality
- Lighthouse mental: CLS/LCP/INP budgets respected (sizes, fetchpriority, no CLS)
- RU↔UZ parity: meaning intact, not machine translation, no fixed px that clips UZ (~30% longer)
- Template vars intact: {{page.title}}, {{global.phone}}, {{global.email}}, {{global.address}}, {{global.working_hours}}, {{global.site_name}}, {{date.*}}, {{faqs}}
- Preview: render_preview called per section, render_full_page for final

═══ ANTI-PATTERNS — NEVER ═══
- !important wars, fixed px widths breaking i18n, meta keywords stuffing, keyword-density writing, hover-only affordances, div-soup, duplicate h1, inline style bloat (use set_section_style/wrap_section only), inventing new {{variables}}

═══ HARD RULES ═══
- Only touch data reachable through tools. Never modify header/footer templates, users, rotation internals beyond tools.
- Keep RU in Russian and UZ in Uzbek — never mix/auto-translate unless explicitly asked.
- If tool fails, say so plainly; don't silently retry same failing call >1.
- Finish with short summary of what you changed/propose; note if awaiting approval.
PROMPT;
        return $modeBlock . "\n\n" . $base;
    }

    private function sanitizeHistory($history): array {
        $decoded = json_decode((string)$history, true);
        $messages = [];
        if (is_array($decoded)) {
            foreach ($decoded as $turn) {
                if (!is_array($turn)) continue;
                $role = in_array($turn['role'] ?? '', ['user', 'assistant'], true) ? $turn['role'] : null;
                $content = (string)($turn['content'] ?? '');
                // Per-message cap to prevent token blow-up (H5). Truncate, don't drop.
                if (mb_strlen($content) > 4000) $content = mb_substr($content, 0, 4000) . "\n…[truncated]";
                if ($role && $content !== '') {
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
                    if ($id !== '' && $name !== '' && strlen($arguments) <= 65536
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
            if ($callId !== '' && $content !== '' && strlen($content) <= 65536
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
        } catch (Throwable $e) {}
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
            $exists = Database::getInstance()->fetchOne("SELECT id FROM ai_sessions WHERE id = ?", [$sessionId]);
            if ($exists) {
                Database::getInstance()->query("UPDATE ai_sessions SET history=?, context=?, model=?, mode=?, title=?, updated_at=NOW() WHERE id=?", [$histJson,$ctxJson,$model,$mode,$title,$sessionId]);
            } else {
                Database::getInstance()->query("INSERT INTO ai_sessions (id,user_id,title,model,mode,history,context) VALUES (?,?,?,?,?,?,?)", [$sessionId,$uid,$title,$model,$mode,$histJson,$ctxJson]);
            }
        } catch (Throwable $e) { error_log('persistSession failed: '.$e->getMessage()); }
    }
    private function persistAfterRun(string $sessionId, array $messages, string $model, string $mode): void {
        // $messages includes system + history + new turns — extract user/assistant/tool for storage
        $history = [];
        foreach ($messages as $m) {
            if (($m['role']??'')==='system') continue;
            // Keep user, assistant, tool
            if (in_array($m['role']??'', ['user','assistant','tool'], true)) $history[] = $m;
        }
        // Cap total chars per msg to 4000 like sanitizeHistory
        foreach ($history as &$h) {
            if (isset($h['content']) && mb_strlen($h['content'])>4000) $h['content']=mb_substr($h['content'],0,4000)."\n…[truncated]";
        }
        if (count($history) > 48) $history = array_slice($history,-48);
        // Need session session id — reopen session briefly
        @session_start();
        $ctx = $_SESSION['ai_context'] ?? [];
        // Persist needs DB — close session first to avoid lock?
        if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
        // Re-open DB connection without session lock
        $this->persistSession($sessionId, $history, is_array($ctx)?$ctx:[], $model, $mode);
        // Restore ai_session_id in superglobal for next request
        @session_start();
        $_SESSION['ai_session_id']=$sessionId;
        if (is_array($ctx)) $_SESSION['ai_context']=$ctx;
        @session_write_close();
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
