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

    public function index() {
        $this->requireAuth();
        $this->view('admin/ai-studio/index', [
            'pageName' => 'ai-studio',
            'models' => OpenRouter::MODELS,
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

        $this->startStream();

        if ($message === '') {
            $this->sse('error', ['message' => 'Message cannot be empty']);
            $this->sse('done', ['status' => 'error']);
            return;
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->buildSystemPrompt()]],
            $history,
            [['role' => 'user', 'content' => $message]]
        );

        $finalText = '';
        $usageTotal = ['prompt' => 0, 'completion' => 0, 'total' => 0, 'cost' => 0.0];

        try {
            for ($turn = 1; $turn <= self::MAX_TOOL_TURNS; $turn++) {
                if (connection_aborted()) {
                    return; // client pressed Stop — don't spend more tokens
                }

                $this->sse('turn', ['number' => $turn, 'max' => self::MAX_TOOL_TURNS]);

                $response = OpenRouter::chatWithTools($messages, $model, AiToolRegistry::definitions(), 0.5, 8192);

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

                    $out = AiToolRegistry::execute($name, $args, $approved);
                    $toolMsgId = $tc['id'] ?? $out['call_id'] ?? $name;

                    if ($out['type'] === 'approval') {
                        $haltForApproval = true;
                        $this->sse('approval_required', [
                            'call_id' => $out['call_id'],
                            'tool' => $name,
                            'plan' => $out['plan'],
                            'reason' => $out['reason'],
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
                        continue;
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
                        $this->sse('preview', ['html' => $out['result']['html']]);
                    }

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolMsgId,
                        'content' => json_encode($out['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ];
                }

                if ($haltForApproval) {
                    $this->sse('done', ['status' => 'awaiting_approval', 'text' => $finalText]);
                    return;
                }
            }

            $this->sse('done', ['status' => 'complete', 'text' => $finalText]);
        } catch (Exception $e) {
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
    // Prompt + helpers
    // ------------------------------------------------------------------

    private function buildSystemPrompt(): string {
        return <<<'PROMPT'
You are the AI Studio agent for kuplyu-tashkent.uz — an appliance buyback (secondhand electronics purchase) service in Tashkent, Uzbekistan. The site is bilingual (RU/UZ). You operate the site's admin backend through the tools provided to you; you do not have a chat-only mode. Always investigate before you act.

═══ OPERATING LOOP ═══
1. Understand what's actually being asked. If it references "the pages" or "underperforming content" without specifics, use read tools (list_pages, get_underperforming_pages, search_content) to find out what that means concretely before touching anything.
2. Read before you write. Never call str_replace_field or set_field against a field you haven't read this session — content may have changed since you last saw it.
3. Prefer targeted edits (str_replace_field) over full rewrites (set_field). Full rewrites are for genuinely new pages/sections, not incremental improvement.
4. For anything destructive (set_field on an existing field, delete_faq, bulk changes across many pages): state your intent in one line, call the tool, and STOP. The system will surface an approval request to the user. Your turn ends there — do not retry the guarded tool in the same turn. Wait for the user's decision.
5. Narrate briefly as you go ("checking which pages get the least traffic", "reading content_ru for page 12") so the user can follow along like a transcript, not a black box.
6. After creating or editing any visual content (section, page content), call render_preview so it shows in the live preview pane. Iterate on the section there before applying.

═══ SEO DOCTRINE ═══
- This CMS has NO keyword or SERP-position data. The ranking-relevant signals available are: visits, clicks, phone calls, CTR (phone calls per visit), bot crawl frequency, and internal-link clicks. Use get_top_pages, get_page_stats, get_underperforming_pages, get_crawl_frequency and get_internal_links to reason about performance.
- Optimize for search-intent match, not keyword density. Every page should answer the specific question its target query implies within the first 2-3 sentences of the relevant section — don't bury the answer.
- Check internal linking: related pages (same category/brand) should link to each other where natural. Thin, orphaned pages rank worse regardless of on-page quality.
- Respect meta length conventions (titles ~60-70 chars, descriptions ~150-160 chars).
- Preserve and extend structured data (JSON-LD) where the page has it; don't strip it.
- When asked to "improve SEO" with no specifics, use get_underperforming_pages to find the weakest pages by traffic first — don't guess.

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
