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
            'message_len' => mb_strlen($message),
            'history_turns' => count($history),
            'approved_count' => count($approved),
            'pending_count' => count($pending),
        ]);

        $this->sse('activity', ['text' => 'Starting…']);

        // Deduplicate last history turn if client already pushed current message (C1).
        $alreadyInHistory = false;
        if (!empty($history)) {
            $last = end($history);
            if (($last['role'] ?? '') === 'user' && ($last['content'] ?? '') === $message) {
                $alreadyInHistory = true;
            }
        }
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->buildSystemPrompt()]],
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
- Ranking-relevant signals: INTERNAL analytics (visits, clicks, phone calls, CTR = phone calls/visits, bot crawl frequency, internal-link clicks) AND Google Search Console data (live via Search Console API — impressions, clicks, CTR, avg position, queries). Internal analytics are always present; GSC is live now (sc-domain:kuplyu-tashkent.uz, ~2-day lag) — check with get_gsc_overview first and degrade gracefully if not connected.
- GSC tools (sugar): get_gsc_overview (site summary), get_page_gsc (per-page totals + top queries — call this before auditing any page), get_gsc_pages (pages ranked by impressions/clicks), get_gsc_queries / search_gsc_queries (top keywords). + FREEDOM TOOL: query_gsc (MCP-like) — build any Search Analytics query with arbitrary dimensions [query, page, country, device, searchAppearance, date], filters (equals/contains/notContains/includingRegex/excludingRegex), explicit startDate/endDate or days, orderBy/limit. Use it for: device/country breakdowns, searchAppearance (e.g. rich results), date trends, regex page filters, period compares (call twice with different date ranges). Prefer sugar for simple cases, freedom tool when you need custom slices.
- When auditing a page, ALWAYS call both get_page (read content) and get_page_gsc + get_page_stats: compare what the page says vs what Search Console says it ranks for, and whether internal traffic (phone calls) correlates with search clicks. For deeper slices (e.g. MOBILE vs DESKTOP for that page) use query_gsc with dimensions ["query","device"] and filter page includingRegex slug.
- Optimize for search-intent match, not keyword density. Every page should answer the specific question its target query implies within the first 2-3 sentences of the relevant section — don't bury the answer.
- Check internal linking: related pages (same category/brand) should link to each other where natural. Thin, orphaned pages rank worse regardless of on-page quality.
- Respect meta length conventions (titles ~60-70 chars, descriptions ~150-160 chars).
- Preserve and extend structured data (JSON-LD) where the page has it; don't strip it.
- When asked to "improve SEO" with no specifics, use get_underperforming_pages (internal) AND get_gsc_pages / get_gsc_overview together — don't guess. If GSC is not connected, say so and fall back to internal analytics.
- For bespoke analysis the fixed tools cannot answer (grouping by month/UTM/hour, comparing periods, cross-referencing pages/analytics), use run_analytics_query — a read-only SELECT over the analytics tables and pages.

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
