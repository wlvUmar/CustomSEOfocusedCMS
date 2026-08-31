<?php
// path: ./controllers/admin/PageAdminController.php

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/IndexNow.php';
require_once BASE_PATH . '/models/ContentRotation.php';
class PageAdminController extends Controller {
    private $pageModel;
    private $rotationModel;


    /**
     * Output token budget for an OpenRouter call, sized to the content involved.
     * The previous flat 4096 cap was easy to exceed on a full-field rewrite of a
     * large HTML page, which truncates the response mid-JSON/mid-HTML — that
     * shows up downstream as "the model didn't return valid JSON" or an edit
     * that silently fails to apply, not as an obvious token-limit error.
     *
     * 'full' mode echoes back content roughly proportional to $contentLength,
     * so it gets a generous multiplier. 'edits' mode only echoes back the
     * specific find/replace snippets touched, so it needs much less even for
     * a large field, but still scales up a bit so many small edits in one
     * turn (or a page-wide restructure) aren't cut off either.
     */
    private function estimateMaxTokens(int $contentLength, string $mode): int {
        $charsPerToken = 3; // conservative for mixed RU/UZ/HTML content
        if ($mode === 'full') {
            $budget = intdiv($contentLength, $charsPerToken) + 1500;
            return max(4096, min($budget, 24000));
        }
        // 'edits' mode
        $budget = intdiv($contentLength, $charsPerToken * 3) + 2000;
        return max(4096, min($budget, 12000));
    }

    private function sanitizeSlug($slug) {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        if (empty($slug)) {
            throw new Exception('Invalid slug: cannot be empty');
        }

        return $slug;
    }

    public function __construct() {
        parent::__construct();
        $this->pageModel = new Page();
        $this->rotationModel = new ContentRotation();
    }

    public function index() {
        $this->requireAuth();
        $hierarchy = $this->pageModel->getHierarchy(false);
        $allPages = $this->pageModel->getAll(true);
        $this->view('admin/pages/list', ['pages' => $allPages, 'hierarchy' => $hierarchy]);
    }

    public function edit($id = null) {
        $this->requireAuth();
        
        $page = null;
        $availableRotations = [];
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        
        if ($id) {
            $page = $this->pageModel->getById($id);
            if (!$page) {
                $_SESSION['error'] = 'Page not found';
                $this->redirect('/admin/pages');
            }
            // Load available rotations for this page
            $availableRotations = $this->rotationModel->getByPageId($id);
        }
        
        // Get all pages for parent selector, excluding self and descendants
        $allPages = [];
        if ($id) {
            $allPagesRaw = $this->pageModel->getAll(true);
            $descendants = $this->pageModel->getDescendantIds($id);
            
            foreach ($allPagesRaw as $p) {
                if ($p['id'] != $id && !in_array($p['id'], $descendants)) {
                    $allPages[] = $p;
                }
            }
        } else {
            $allPages = $this->pageModel->getAll(true);
        }
        
        $this->view('admin/pages/edit', ['page' => $page, 'allPages' => $allPages, 'availableRotations' => $availableRotations, 'months' => $months, 'pageName' => 'pages/edit']);
    }

    /**
     * AI-assisted page editing via OpenRouter (POST /admin/pages/ai-edit)
     * Edits one whitelisted field with a user prompt, returns new content as JSON.
     */
    public function aiEdit() {
        $this->requireAuth();

        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->json(['success' => false, 'message' => 'CSRF token validation failed'], 400);
            return;
        }

        $pageId = intval($_POST['page_id'] ?? 0);
        $field = $_POST['field'] ?? '';
        $prompt = trim($_POST['prompt'] ?? '');
        $model = $_POST['model'] ?? '';
        $mode = $_POST['mode'] ?? 'full'; // 'full' = replace entire field, 'edits' = targeted find/replace

        $allowedFields = [
            'content_ru', 'content_uz',
            'title_ru', 'title_uz',
            'meta_title_ru', 'meta_title_uz',
            'meta_description_ru', 'meta_description_uz',
        ];
        $allowedModes = ['full', 'edits'];

        if ($pageId <= 0) {
            $this->json(['success' => false, 'message' => 'Invalid page'], 400);
            return;
        }
        if (!in_array($field, $allowedFields, true)) {
            $this->json(['success' => false, 'message' => 'Invalid target field'], 400);
            return;
        }
        if (!in_array($mode, $allowedModes, true)) {
            $this->json(['success' => false, 'message' => 'Invalid mode'], 400);
            return;
        }
        if ($prompt === '') {
            $this->json(['success' => false, 'message' => 'Prompt cannot be empty'], 400);
            return;
        }

        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            $this->json(['success' => false, 'message' => 'Page not found'], 404);
            return;
        }

        require_once BASE_PATH . '/models/OpenRouter.php';

        // Prefer whatever is currently in the browser's form/editor (unsaved edits included);
        // only fall back to the saved DB value when the client didn't send anything yet.
        $workingContent = (string)($_POST['working_content'] ?? '');
        $currentValue = $workingContent !== '' ? $workingContent : (string)($page[$field] ?? '');
        $historyMessages = $this->buildHistoryMessages($_POST['history'] ?? '[]');

        $isHtml = in_array($field, ['content_ru', 'content_uz'], true);
        $isRu = str_ends_with($field, '_ru') || $field === 'title_ru';

        $siteName = $page['title_ru'] ?? 'the page';
        $fieldLabels = [
            'content_ru' => 'page content (RU / Russian)',
            'content_uz' => 'page content (UZ / Uzbek)',
            'title_ru' => 'page title (RU / Russian)',
            'title_uz' => 'page title (UZ / Uzbek)',
            'meta_title_ru' => 'meta title (RU / Russian)',
            'meta_title_uz' => 'meta title (UZ / Uzbek)',
            'meta_description_ru' => 'meta description (RU / Russian)',
            'meta_description_uz' => 'meta description (UZ / Uzbek)',
        ];

        $system = [
            'role' => 'system',
            'content' => 'You are a Staff-level HTML/CSS & Technical SEO specialist (15+ years, judged on W3C-valid semantic HTML5, Lighthouse 95+, WCAG 2.2 AA, CLS<0.1) for appliance buyback service in Tashkent, bilingual RU/UZ. '
                . "You are editing the {$fieldLabels[$field]} of the page titled \"{$siteName}\".\n"
                . ($mode === 'edits'
                    ? "The user wants you to make TARGETED changes. Inspect the current value and decide which "
                        . "small pieces need to change. Respond with ONLY a JSON object of this exact shape, no "
                        . "explanations, no markdown fences:\n"
                        . "{\"edits\":[{\"find\":\"<exact existing text to locate>\",\"replace\":\"<new text>\"}]}\n"
                        . "- The 'find' text MUST appear verbatim in the current value; copy it exactly, character for character "
                        . "(it is searched literally, so quote it precisely including punctuation and HTML tags).\n"
                        . "- Each 'find' must be unique in the value (occur exactly once); if it appears several times, "
                        . "include surrounding context to make it unique.\n"
                        . "- For deletion, use \"replace\":\"\".\n"
                        . "- Only include edits you actually intend to make; nothing else is touched.\n"
                    : "- Respond with ONLY the final value for the field. No explanations, no markdown fences, no preamble.\n")
                . 'Rules:' . "\n"
                . '- Keep the language exactly as specified for this field (' . ($isRu ? 'Russian' : 'Uzbek') . ').' . "\n"
                . '- Preserve all template variables exactly as-is: {{page.title}}, {{global.phone}}, {{global.email}}, '
                . '{{global.address}}, {{global.working_hours}}, {{global.site_name}}, {{date.year}}, {{date.month}}, '
                . 'and any other {{...}} placeholder. Never invent new variables.' . "\n"
                . ($isHtml
                    ? "- The current value is HTML. Preserve existing structure, CSS classes "
                        . "(content-section, info-card, process-step, faq-item, links-tile, btn, btn-primary) and "
                        . "inline styles unless explicitly asked to change. Use semantic tags, landmarks, heading hierarchy (h1→h2→h3 no skips), alt quality; prefer tokens var(--teal)/var(--teal-dark)/var(--orange) via get_design_tokens — custom hex only on explicit request + note debt; ensure WCAG 4.5:1 contrast; set loading=\"lazy\" + decoding=\"async\" and fetchpriority=\"high\" for hero, width/height to avoid CLS.\n"
                    : "- For short fields respect pixel width ~580px (not just 60-70 chars); meta descriptions ~150-160 chars. Never author new meta keywords (deprecated). Consider CTR A/B: \" | Brand\" vs \" - \" testing.\n")
                . '- If prompt is vague: (1) intent-match first 2 sentences, (2) craft 40-60 word answer block for featured snippet, (3) suggest 1-2 natural internal links, (4) never keyword-stuff.',
        ];

        $user = [
            'role' => 'user',
            'content' => ($currentValue !== ''
                    ? "Current value of the field:\n\n{$currentValue}\n\n"
                    : "The field is currently empty.\n\n")
                . "User request:\n{$prompt}",
        ];

        $messages = array_merge([$system], $historyMessages, [$user]);

        try {
            $maxTokens = $this->estimateMaxTokens(strlen($currentValue) + strlen($prompt), $mode);
            $result = OpenRouter::chat($messages, $model, 0.7, $maxTokens);
            $response = ['success' => true, 'result' => $result];
            if ($mode === 'edits') {
                $parsed = $this->parseEditsResponse($result);
                $applied = $this->applyEditsPartial($currentValue, $parsed['edits']);
                $response['result'] = $applied['text'];
                $response['changes'] = $applied['applied'];
                if (!empty($applied['failed'])) {
                    $response['unresolved'] = array_map([$this, 'describeFailedEdit'], $applied['failed']);
                }
                if (empty($applied['applied'])) {
                    $this->json(['success' => false, 'message' => 'No edits could be applied — the model\'s "find" text did not match the content.'], 500);
                    return;
                }
            }
            $this->json($response);
        } catch (Exception $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * AI chat-style targeted editing (POST /admin/pages/ai-chat)
     * Multi-turn: the browser keeps a working copy + chat history; each request
     * applies the model's find/replace edits to that working copy only.
     */
    public function aiChat() {
        $this->requireAuth();

        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->json(['success' => false, 'message' => 'CSRF token validation failed'], 400);
            return;
        }

        $pageId = intval($_POST['page_id'] ?? 0);
        $field = $_POST['field'] ?? '';
        $prompt = trim($_POST['prompt'] ?? '');
        $model = $_POST['model'] ?? '';
        $workingContent = (string)($_POST['working_content'] ?? '');
        $history = $_POST['history'] ?? '[]';

        $allowedFields = [
            'content_ru', 'content_uz',
            'title_ru', 'title_uz',
            'meta_title_ru', 'meta_title_uz',
            'meta_description_ru', 'meta_description_uz',
        ];

        if ($pageId <= 0) {
            $this->json(['success' => false, 'message' => 'Invalid page'], 400);
            return;
        }
        if (!in_array($field, $allowedFields, true)) {
            $this->json(['success' => false, 'message' => 'Invalid target field'], 400);
            return;
        }
        if ($prompt === '') {
            $this->json(['success' => false, 'message' => 'Prompt cannot be empty'], 400);
            return;
        }

        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            $this->json(['success' => false, 'message' => 'Page not found'], 404);
            return;
        }

        // Fall back to the stored value on the very first turn (empty working copy)
        if ($workingContent === '') {
            $workingContent = (string)($page[$field] ?? '');
        }

        require_once BASE_PATH . '/models/OpenRouter.php';

        $isHtml = in_array($field, ['content_ru', 'content_uz'], true);
        $isRu = str_ends_with($field, '_ru') || $field === 'title_ru';
        $siteName = $page['title_ru'] ?? 'the page';
        $fieldLabels = [
            'content_ru' => 'page content (RU / Russian)',
            'content_uz' => 'page content (UZ / Uzbek)',
            'title_ru' => 'page title (RU / Russian)',
            'title_uz' => 'page title (UZ / Uzbek)',
            'meta_title_ru' => 'meta title (RU / Russian)',
            'meta_title_uz' => 'meta title (UZ / Uzbek)',
            'meta_description_ru' => 'meta description (RU / Russian)',
            'meta_description_uz' => 'meta description (UZ / Uzbek)',
        ];

        // For big HTML fields, avoid re-sending the entire field on every turn —
        // that's the token burn you're seeing. This codebase already marks logical
        // blocks with "<!-- Section Name -->" comments (Page Hero, Intro, CTA, etc.),
        // so scope the request to just the section(s) that look relevant to the
        // prompt, and escalate to the full field automatically if that guess is wrong.
        $sections = null;
        $scopedSections = null;
        if ($isHtml && mb_strlen($workingContent) > 2000) {
            $sections = $this->splitIntoSections($workingContent);
            if (count($sections) >= 3) {
                $scopedSections = $this->selectRelevantSections($sections, $prompt);
            }
        }
        $totalSections = $sections !== null ? count($sections) : 0;

        $system = [
            'role' => 'system',
            'content' => "You are a Staff-level HTML/CSS & Technical SEO specialist (15+ years, W3C/Lighthouse/WCAG) for appliance buyback service in Tashkent, bilingual RU/UZ. "
                . "You are editing the {$fieldLabels[$field]} of the page titled \"{$siteName}\".\n"
                . "You work with the CURRENT value of the field, which may already contain changes from "
                . "previous turns of this session.\n"
                . "Rules:\n"
                . "- Read the current value and the user request, then decide which small pieces must change.\n"
                . "- Respond with ONLY a JSON object of this exact shape, no explanations, no markdown fences:\n"
                . "{\"edits\":[{\"find\":\"<exact existing text>\",\"replace\":\"<new text>\",\"explanation\":\"<one short sentence>\"}]}\n"
                . "- The \"find\" text MUST appear verbatim in the current value; copy it exactly, character for "
                . "character, including punctuation and HTML tags. It is searched literally.\n"
                . "- Each \"find\" must occur exactly once in the value; if it appears several times, include "
                . "surrounding context to make it unique.\n"
                . "- For deletion, use \"replace\": \"\".\n"
                . "- Touch ONLY what the user asked for; leave everything else untouched. Do not rewrite "
                . "unrelated lines and do not return the whole value.\n"
                . "- Keep the language as specified for this field (" . ($isRu ? 'Russian' : 'Uzbek') . "). Check RU↔UZ semantic parity — keep language exactly as specified, never mix.\n"
                . "- Preserve all template variables exactly as-is: {{page.title}}, {{global.phone}}, "
                . "{{global.email}}, {{global.address}}, {{global.working_hours}}, {{global.site_name}}, "
                . "{{date.year}}, {{date.month}} and any other {{...}} placeholder. Never invent new variables.\n"
                . ($isHtml
                    ? "- The value is HTML. Preserve existing structure, CSS classes "
                        . "(content-section, info-card, process-step, faq-item, links-tile, btn, btn-primary) "
                        . "and inline styles unless explicitly asked to change. Use semantic tags, landmarks, heading hierarchy (h1→h2→h3), alt quality; prefer tokens var(--teal) — custom hex only on explicit request; ensure WCAG 4.5:1 contrast; set loading/fetchpriority as needed.\n"
                    : "- For short fields respect pixel width ~580px (not just 60-70 chars); meta descriptions ~150-160 chars. Never author new meta keywords; consider CTR A/B \" | Brand\" vs \" - \".\n")
                . "- If prompt is vague: (1) intent-match first 2 sentences, (2) 40-60 word answer block for featured snippet, (3) suggest 1-2 natural internal links, (4) never keyword-stuff.\n"
                . ($scopedSections !== null
                    ? "- To save tokens you are only shown the section(s) of the field that look relevant to the "
                        . "request, not the whole value. If the exact text you need to change is NOT visible in "
                        . "what you were shown, respond with ONLY {\"edits\":[],\"need_more_context\":true} and "
                        . "nothing else — you will then be shown the full field.\n"
                    : ""),
        ];

        $historyMessages = $this->buildHistoryMessages($history);

        $user = [
            'role' => 'user',
            'content' => $this->buildScopedContext($workingContent, $scopedSections, $totalSections)
                . "User request:\n{$prompt}",
        ];

        $messages = array_merge([$system], $historyMessages, [$user]);

        try {
            $chatContentLen = $scopedSections !== null
                ? array_sum(array_map(fn($s) => strlen($s['text']), $scopedSections))
                : strlen($workingContent);
            $maxTokens = $this->estimateMaxTokens($chatContentLen, 'edits');
            $modelOutput = OpenRouter::chat($messages, $model, 0.7, $maxTokens);
            $parsed = $this->parseEditsResponse($modelOutput);
            $usedFullContext = ($scopedSections === null);
            $sectionsUsed = $scopedSections !== null ? array_column($scopedSections, 'name') : null;

            // Escalate to the full field if the model asked for more context, or came
            // back with nothing usable — a sign the section guess missed the target.
            if ($scopedSections !== null && ($parsed['need_more_context'] || empty($parsed['edits']))) {
                $fullUser = [
                    'role' => 'user',
                    'content' => $this->buildScopedContext($workingContent, null, $totalSections)
                        . "User request:\n{$prompt}",
                ];
                $messages = array_merge([$system], $historyMessages, [$fullUser]);
                $maxTokens = $this->estimateMaxTokens(strlen($workingContent), 'edits');
                $modelOutput = OpenRouter::chat($messages, $model, 0.7, $maxTokens);
                $parsed = $this->parseEditsResponse($modelOutput);
                $usedFullContext = true;
                $sectionsUsed = null;
            }

            $round = $this->applyEditsPartial($workingContent, $parsed['edits']);
            $text = $round['text'];
            $applied = $round['applied'];
            $failed = $round['failed'];

            // Agentic self-correction: if some edits didn't land (ambiguous match,
            // text not found, etc.), give the model one shot to fix just those,
            // instead of throwing the whole turn away.
            if (!empty($failed)) {
                try {
                    $correction = "Some of your edits could not be applied to the current value:\n\n"
                        . $this->describeFailuresForModel($failed)
                        . "\nThe successful edits (if any) have already been applied. Re-examine the CURRENT "
                        . "value below and resend ONLY corrected edits for the items above, in the same JSON "
                        . "shape. If a change is no longer needed, omit it.\n\n"
                        . $this->buildScopedContext($text, null, $totalSections);
                    $retryMessages = array_merge($messages, [
                        ['role' => 'assistant', 'content' => $modelOutput],
                        ['role' => 'user', 'content' => $correction],
                    ]);
                    $retryMaxTokens = $this->estimateMaxTokens(strlen($text) + strlen($correction), 'edits');
                    $retryOutput = OpenRouter::chat($retryMessages, $model, 0.7, $retryMaxTokens);
                    $retryParsed = $this->parseEditsResponse($retryOutput);
                    $retryRound = $this->applyEditsPartial($text, $retryParsed['edits']);
                    $text = $retryRound['text'];
                    $applied = array_merge($applied, $retryRound['applied']);
                    $failed = $retryRound['failed'];
                } catch (Exception $e) {
                    // Retry failed outright (bad JSON, network, etc.) — keep the
                    // original failures as unresolved and move on with what we have.
                }
            }

            if (empty($applied)) {
                $this->json([
                    'success' => false,
                    'message' => 'Could not apply the edit. ' . $this->describeFailuresForModel($failed, true),
                ], 500);
                return;
            }

            $response = [
                'success' => true,
                'result'  => $text,
                'changes' => $applied,
            ];
            if (!empty($failed)) {
                $response['unresolved'] = array_map([$this, 'describeFailedEdit'], $failed);
            }
            if ($sectionsUsed !== null) {
                $response['scoped'] = true;
                $response['sections_used'] = $sectionsUsed;
                $response['sections_total'] = $totalSections;
            }
            $this->json($response);
        } catch (Exception $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Parse and trim a JSON-encoded chat history array from the client into
     * the {role, content} shape OpenRouter expects.
     */
    private function buildHistoryMessages($history) {
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
            // Trim to the last ~12 turns to keep context manageable
            if (count($messages) > 12) {
                $messages = array_slice($messages, -12);
            }
        }
        return $messages;
    }

    /**
     * Prefix every line with its number (reference only, never used for matching).
     */
    private function numberLines($text) {
        $lines = explode("\n", $text);
        $out = [];
        foreach ($lines as $i => $line) {
            $out[] = ($i + 1) . ': ' . $line;
        }
        return implode("\n", $out);
    }

    /**
     * In 'edits' mode the model returns a JSON patch like:
     *   {"edits":[{"find":"...","replace":"...","explanation":"..."}, ...]}
     * (optionally with "need_more_context":true when it was shown a scoped
     * excerpt and couldn't find its target in it). This parses that patch.
     * Throws only when the output isn't valid JSON of the expected shape —
     * actual find/replace failures are handled by applyEditsPartial() below
     * without discarding the whole batch.
     */
    private function parseEditsResponse($modelOutput) {
        // Strip surrounding markdown fences if the model wrapped the JSON
        $clean = trim($modelOutput);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);

        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start === false || $end === false || $end <= $start) {
            throw new Exception('The model did not return valid JSON. It returned: ' . mb_substr($modelOutput, 0, 400));
        }
        $json = substr($clean, $start, $end - $start + 1);

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['edits']) || !is_array($data['edits'])) {
            throw new Exception('Expected JSON with an "edits" array. Got: ' . mb_substr($json, 0, 400));
        }
        return [
            'edits' => $data['edits'],
            'need_more_context' => !empty($data['need_more_context']),
        ];
    }

    /**
     * Split an HTML field on its "<!-- Section Name -->" comment markers (this
     * codebase already uses these to delimit logical blocks: hero, intro, CTA,
     * etc). Text before the first marker is grouped under 'Top of document'.
     * Returns an ordered list of ['name' => ..., 'text' => ...].
     */
    private function splitIntoSections($html) {
        $parts = preg_split('/(<!--.*?-->)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $sections = [];
        $currentName = 'Top of document';
        $buffer = '';
        foreach ($parts as $part) {
            if (preg_match('/^<!--\s*(.*?)\s*-->$/s', $part, $m)) {
                if (trim($buffer) !== '') {
                    $sections[] = ['name' => $currentName, 'text' => $buffer];
                }
                $currentName = trim($m[1]) !== '' ? trim($m[1]) : $currentName;
                $buffer = $part . "\n";
            } else {
                $buffer .= $part;
            }
        }
        if (trim($buffer) !== '') {
            $sections[] = ['name' => $currentName, 'text' => $buffer];
        }
        return $sections;
    }

    /**
     * Score each section by how many meaningful prompt words it contains, and
     * greedily pick the best ones within a rough token budget. Returns null
     * (meaning: use the full field instead) when nothing scores — a vague or
     * unrelated-looking prompt isn't worth guessing on.
     */
    private function selectRelevantSections(array $sections, $prompt, $budgetChars = 6000) {
        preg_match_all('/[\p{L}\p{N}]{4,}/u', mb_strtolower($prompt), $m);
        $words = array_unique($m[0] ?? []);
        if (empty($words)) return null;

        $scored = [];
        foreach ($sections as $i => $section) {
            $haystack = mb_strtolower(strip_tags($section['name'] . ' ' . $section['text']));
            $score = 0;
            foreach ($words as $w) {
                if (mb_strpos($haystack, $w) !== false) $score++;
            }
            $scored[$i] = $score;
        }

        arsort($scored);
        if (reset($scored) === 0) return null;

        $chosenIdx = [];
        $used = 0;
        foreach ($scored as $i => $score) {
            if ($score === 0) break;
            $len = mb_strlen($sections[$i]['text']);
            if (!empty($chosenIdx) && $used + $len > $budgetChars) continue;
            $chosenIdx[] = $i;
            $used += $len;
        }
        sort($chosenIdx); // restore original document order for readability
        $selected = [];
        foreach ($chosenIdx as $i) $selected[] = $sections[$i];
        return $selected;
    }

    /**
     * Build the "current value" portion of the user message: either the full
     * field (line-numbered), or just the scoped sections when $scopedSections
     * is a non-null array.
     */
    private function buildScopedContext($workingContent, $scopedSections, $totalSections) {
        if ($scopedSections === null) {
            return $workingContent !== ''
                ? "Current value of the field (line numbers shown only for reference):\n\n"
                    . $this->numberLines($workingContent) . "\n\n"
                : "The field is currently empty.\n\n";
        }
        $shown = count($scopedSections);
        $out = "Showing {$shown} of {$totalSections} section(s) of the field — the ones that look most "
            . "relevant to the request below (this saves tokens on large fields):\n\n";
        foreach ($scopedSections as $s) {
            $out .= "--- SECTION: {$s['name']} ---\n" . $s['text'] . "\n\n";
        }
        return $out;
    }

    /**
     * Apply a list of {find, replace, explanation?} edits to $text.
     * Never throws: each edit either succeeds or is reported as failed
     * (not found / ambiguous), so one bad match doesn't discard the rest
     * of a good batch. Returns ['text' => ..., 'applied' => [...], 'failed' => [...]].
     */
    private function applyEditsPartial($text, array $edits) {
        $applied = [];
        $failed = [];
        foreach ($edits as $edit) {
            if (!is_array($edit)) continue;
            $find = (string)($edit['find'] ?? '');
            $replace = (string)($edit['replace'] ?? '');
            $explanation = (string)($edit['explanation'] ?? '');
            if ($find === '') continue;

            $count = substr_count($text, $find);
            if ($count === 0) {
                $failed[] = ['find' => $find, 'replace' => $replace, 'explanation' => $explanation, 'reason' => 'not_found'];
                continue;
            }
            if ($count > 1) {
                $failed[] = ['find' => $find, 'replace' => $replace, 'explanation' => $explanation, 'reason' => 'ambiguous', 'count' => $count];
                continue;
            }
            $text = str_replace($find, $replace, $text);
            $applied[] = ['find' => $find, 'replace' => $replace, 'explanation' => $explanation];
        }
        return ['text' => $text, 'applied' => $applied, 'failed' => $failed];
    }

    /**
     * Human-readable reason for a single failed edit, for display in the UI.
     */
    private function describeFailedEdit($edit) {
        $snippet = mb_substr($edit['find'], 0, 80);
        if (($edit['reason'] ?? '') === 'ambiguous') {
            $reason = 'appears ' . ($edit['count'] ?? 'multiple') . ' times in the content — too ambiguous to apply safely';
        } else {
            $reason = 'could not be found in the current content';
        }
        return [
            'find' => $edit['find'],
            'replace' => $edit['replace'],
            'explanation' => $edit['explanation'] ?? '',
            'reason' => $reason,
        ];
    }

    /**
     * Render a list of failed edits as text, for feeding back to the model
     * (self-correction) or showing in an error message.
     */
    private function describeFailuresForModel(array $failed, bool $short = false) {
        if (empty($failed)) return '';
        $lines = [];
        foreach ($failed as $edit) {
            $d = $this->describeFailedEdit($edit);
            $lines[] = '- "' . mb_substr($d['find'], 0, 80) . '" — ' . $d['reason'] . '.';
        }
        if ($short) {
            return implode(' ', $lines);
        }
        return implode("\n", $lines) . "\n";
    }

    public function save() {
        $this->requireAuth();
        
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'CSRF token validation failed';
            $this->redirect('/admin/pages');
        }
        
        $id = $_POST['id'] ?? null;
        
        // parent_id: if not sent or empty string, set to null (root level)
        // otherwise convert to int
        $parentId = null;
        if (!empty($_POST['parent_id'])) {
            $parentId = intval($_POST['parent_id']);
        }
        
        $data = [
            'slug' => $this->sanitizeSlug($_POST['slug']),
            'title_ru' => trim($_POST['title_ru']),
            'title_uz' => trim($_POST['title_uz']),
            'content_ru' => $_POST['content_ru'] ?? '',
            'content_uz' => $_POST['content_uz'] ?? '',
            'meta_title_ru' => trim($_POST['meta_title_ru']) ?: null,
            'meta_title_uz' => trim($_POST['meta_title_uz']) ?: null,
            'meta_keywords_ru' => trim($_POST['meta_keywords_ru']) ?: null,
            'meta_keywords_uz' => trim($_POST['meta_keywords_uz']) ?: null,
            'meta_description_ru' => trim($_POST['meta_description_ru']) ?: null,
            'meta_description_uz' => trim($_POST['meta_description_uz']) ?: null,
            'og_title_ru' => trim($_POST['og_title_ru']) ?: null,
            'og_title_uz' => trim($_POST['og_title_uz']) ?: null,
            'og_description_ru' => trim($_POST['og_description_ru']) ?: null,
            'og_description_uz' => trim($_POST['og_description_uz']) ?: null,
            'og_image' => trim($_POST['og_image']) ?: null,
            'canonical_url' => trim($_POST['canonical_url']) ?: null,
            'jsonld_ru' => trim($_POST['jsonld_ru']) ?: null,
            'jsonld_uz' => trim($_POST['jsonld_uz']) ?: null,
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
            'rotation_mode' => $_POST['rotation_mode'] ?? 'auto',
            'selected_rotation_id' => !empty($_POST['selected_rotation_id']) ? intval($_POST['selected_rotation_id']) : null,
            'sort_order' => intval($_POST['sort_order'] ?? 0),
            'parent_id' => $parentId
        ];
        
        if ($id) {
            $this->pageModel->update($id, $data);
            $_SESSION['success'] = 'Page updated successfully';
            

        } else {
            $this->pageModel->create($data);
            $_SESSION['success'] = 'Page created successfully';
            
        }

        // Trigger IndexNow submission
        try {
            $fullUrl = BASE_URL . '/' . $data['slug'];
            // If it's a multilingual site, we might want to submit both language versions if they have different URLs
            // Assuming the router handles /ru/slug and /uz/slug or just /slug depending on setup.
            // Based on code, it seems just /slug.
            
            // Also need to handle nested pages if slug doesn't include parent structure
            // But PageAdminController sanitizeSlug suggests flat slugs or manual handling.
            // Let's assume standard BASE_URL + / + slug for now.
            
            IndexNow::submit($fullUrl);
        } catch (Exception $e) {
            logDebug("IndexNow submission error: " . $e->getMessage());
        }
        
        $this->redirect('/admin/pages');
    }

    public function delete() {
        $this->requireAuth();
        
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }
        
        $id = $_POST['id'] ?? null;
        if ($id) {
            $this->pageModel->delete($id);
            $_SESSION['success'] = 'Page deleted successfully';
        }
        
        // Support both JSON (fetch) and form redirect
        if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            $this->json(['success' => true]);
            return;
        }
        $this->redirect('/admin/pages');
    }

    public function revisions($id = null) {
        $this->requireAuth();
        $pageId = (int)($id ?? $_GET['id'] ?? 0);
        if ($pageId <= 0) $this->json(['success' => false, 'message' => 'Page id required'], 400);
        require_once BASE_PATH . '/models/PageRevision.php';
        $model = new PageRevision();
        $rows = $model->getByPageId($pageId, 20);
        $this->json(['success' => true, 'revisions' => $rows]);
    }

    public function restoreRevision() {
        $this->requireAuth();
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->json(['success' => false, 'message' => 'CSRF token validation failed'], 400);
            return;
        }
        $revId = (int)($_POST['revision_id'] ?? 0);
        if ($revId <= 0) $this->json(['success' => false, 'message' => 'revision_id required'], 400);
        require_once BASE_PATH . '/models/PageRevision.php';
        try {
            $model = new PageRevision();
            $fresh = $model->restore($revId);
            $this->json(['success' => true, 'page' => $fresh, 'message' => 'Page restored to revision ' . $revId]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}