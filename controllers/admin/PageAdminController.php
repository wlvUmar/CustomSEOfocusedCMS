<?php
// path: ./controllers/admin/PageAdminController.php

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/IndexNow.php';
require_once BASE_PATH . '/models/ContentRotation.php';
class PageAdminController extends Controller {
    private $pageModel;
    private $rotationModel;


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
        }
        if (!in_array($field, $allowedFields, true)) {
            $this->json(['success' => false, 'message' => 'Invalid target field'], 400);
        }
        if (!in_array($mode, $allowedModes, true)) {
            $this->json(['success' => false, 'message' => 'Invalid mode'], 400);
        }
        if ($prompt === '') {
            $this->json(['success' => false, 'message' => 'Prompt cannot be empty'], 400);
        }

        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            $this->json(['success' => false, 'message' => 'Page not found'], 404);
        }

        require_once BASE_PATH . '/models/OpenRouter.php';

        $currentValue = (string)($page[$field] ?? '');
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
            'content' => 'You are a senior content editor and SEO specialist for an appliance buyback '
                . '(scrap-purchase) service company website based in Tashkent, Uzbekistan. '
                . 'The website is bilingual: Russian (RU) and Uzbek (UZ). '
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
                    ? "- The current value is HTML. Preserve the existing structure, CSS classes "
                        . "(e.g. content-section, info-card, process-step, faq-item, links-tile, btn, btn-primary) and "
                        . "inline styles unless the user explicitly asks to change them.\n"
                    : '- For short fields (titles, meta titles, meta descriptions) respect reasonable length limits '
                        . '(titles ~60-70 chars, meta descriptions ~150-160 chars).\n')
                . '- If a prompt is too vague, make reasonable SEO-focused improvements rather than asking questions.',
        ];

        $user = [
            'role' => 'user',
            'content' => ($currentValue !== ''
                    ? "Current value of the field:\n\n{$currentValue}\n\n"
                    : "The field is currently empty.\n\n")
                . "User request:\n{$prompt}",
        ];

        try {
            $result = OpenRouter::chat([$system, $user], $model);
            if ($mode === 'edits') {
                $applied = $this->applyTargetedEdits($currentValue, $result);
                $result = $applied['text'];
            }
            $this->json(['success' => true, 'result' => $result]);
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
        }
        if (!in_array($field, $allowedFields, true)) {
            $this->json(['success' => false, 'message' => 'Invalid target field'], 400);
        }
        if ($prompt === '') {
            $this->json(['success' => false, 'message' => 'Prompt cannot be empty'], 400);
        }

        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            $this->json(['success' => false, 'message' => 'Page not found'], 404);
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

        $system = [
            'role' => 'system',
            'content' => 'You are a precise line-editor tool for an appliance buyback service website '
                . 'based in Tashkent, Uzbekistan (bilingual RU/UZ). '
                . "You are editing the {$fieldLabels[$field]} of the page titled \"{$siteName}\".\n"
                . 'You work with the CURRENT value of the field, which may already contain changes from '
                . 'previous turns of this session.\n'
                . 'Rules:' . "\n"
                . '- Read the current value and the user request, then decide which small pieces must change.\n'
                . '- Respond with ONLY a JSON object of this exact shape, no explanations, no markdown fences:\n'
                . "{\"edits\":[{\"find\":\"<exact existing text>\",\"replace\":\"<new text>\",\"explanation\":\"<one short sentence>\"}]}\n"
                . '- The "find" text MUST appear verbatim in the current value; copy it exactly, character for '
                . 'character, including punctuation and HTML tags. It is searched literally.\n'
                . '- Each "find" must occur exactly once in the value; if it appears several times, include '
                . 'surrounding context to make it unique.\n'
                . '- For deletion, use "replace": "".\n'
                . '- Touch ONLY what the user asked for; leave everything else untouched. Do not rewrite '
                . 'unrelated lines and do not return the whole value.\n'
                . '- Keep the language as specified for this field (' . ($isRu ? 'Russian' : 'Uzbek') . ').\n'
                . '- Preserve all template variables exactly as-is: {{page.title}}, {{global.phone}}, '
                . '{{global.email}}, {{global.address}}, {{global.working_hours}}, {{global.site_name}}, '
                . '{{date.year}}, {{date.month}} and any other {{...}} placeholder. Never invent new variables.\n'
                . ($isHtml
                    ? '- The value is HTML. Preserve the existing structure, CSS classes '
                        . "(e.g. content-section, info-card, process-step, faq-item, links-tile, btn, btn-primary) "
                        . "and inline styles unless the user explicitly asks to change them.\n"
                    : '- For short fields (titles, meta titles, meta descriptions) respect reasonable length '
                        . 'limits (titles ~60-70 chars, meta descriptions ~150-160 chars).\n'),
        ];

        $decodedHistory = json_decode($history, true);
        $historyMessages = [];
        if (is_array($decodedHistory)) {
            foreach ($decodedHistory as $turn) {
                if (!is_array($turn)) continue;
                $role = in_array($turn['role'] ?? '', ['user', 'assistant'], true) ? $turn['role'] : null;
                $content = (string)($turn['content'] ?? '');
                if ($role && $content !== '') {
                    $historyMessages[] = ['role' => $role, 'content' => $content];
                }
            }
            // Trim to the last ~12 turns to keep context manageable
            if (count($historyMessages) > 12) {
                $historyMessages = array_slice($historyMessages, -12);
            }
        }

        $user = [
            'role' => 'user',
            'content' => ($workingContent !== ''
                    ? "Current value of the field (line numbers shown only for reference):\n\n"
                        . $this->numberLines($workingContent) . "\n\n"
                    : "The field is currently empty.\n\n")
                . "User request:\n{$prompt}",
        ];

        $messages = array_merge([$system], $historyMessages, [$user]);

        try {
            $modelOutput = OpenRouter::chat($messages, $model);
            $applied = $this->applyTargetedEdits($workingContent, $modelOutput);
            $this->json([
                'success'   => true,
                'result'    => $applied['text'],
                'changes'   => $applied['changes'],
            ]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
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
     * This applies each find/replace to the original value, leaving the rest untouched.
     * Returns ['text' => patched text, 'changes' => [['find' => ..., 'replace' => ..., 'explanation' => ...]]]
     */
    private function applyTargetedEdits($original, $modelOutput) {
        // Strip surrounding markdown fences if the model wrapped the JSON
        $clean = trim($modelOutput);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);

        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start === false || $end === false || $end <= $start) {
            throw new Exception('Targeted edits: the model did not return valid JSON. It returned: ' . mb_substr($modelOutput, 0, 400));
        }
        $json = substr($clean, $start, $end - $start + 1);

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['edits']) || !is_array($data['edits'])) {
            throw new Exception('Targeted edits: expected JSON with an "edits" array. Got: ' . mb_substr($json, 0, 400));
        }

        $text = $original;
        $applied = 0;
        $changes = [];
        foreach ($data['edits'] as $edit) {
            if (!is_array($edit)) continue;
            $find = (string)($edit['find'] ?? '');
            $replace = (string)($edit['replace'] ?? '');
            $explanation = (string)($edit['explanation'] ?? '');

            if ($find === '') continue;

            $count = substr_count($text, $find);
            if ($count === 0) {
                throw new Exception(
                    'Could not find "' . mb_substr($find, 0, 80) . '" in the current value. '
                    . 'Nothing was changed — try rephrasing.'
                );
            }
            if ($count > 1) {
                throw new Exception(
                    '"' . mb_substr($find, 0, 80) . '" occurs ' . $count . ' times. '
                    . 'Make the change request more specific. Nothing was changed.'
                );
            }
            $text = str_replace($find, $replace, $text);
            $applied++;
            $changes[] = ['find' => $find, 'replace' => $replace, 'explanation' => $explanation];
        }

        if ($applied === 0) {
            throw new Exception('No edits could be applied.');
        }

        return ['text' => $text, 'changes' => $changes];
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
        
        $id = $_POST['id'] ?? null;
        if ($id) {
            $this->pageModel->delete($id);
            $_SESSION['success'] = 'Page deleted successfully';
        }
        
        $this->redirect('/admin/pages');
    }
}