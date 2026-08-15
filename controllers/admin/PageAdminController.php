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
                    : "- For short fields (titles, meta titles, meta descriptions) respect reasonable length limits "
                        . "(titles ~60-70 chars, meta descriptions ~150-160 chars).\n")
                . '- If a prompt is too vague, make reasonable SEO-focused improvements rather than asking questions.',
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
            $result = OpenRouter::chat($messages, $model);
            $response = ['success' => true, 'result' => $result];
            if ($mode === 'edits') {
                $edits = $this->parseEditsJson($result);
                $applied = $this->applyEditsPartial($currentValue, $edits);
                $response['result'] = $applied['text'];
                $response['changes'] = $applied['applied'];
                if (!empty($applied['failed'])) {
                    $response['unresolved'] = array_map([$this, 'describeFailedEdit'], $applied['failed']);
                }
                if (empty($applied['applied'])) {
                    $this->json(['success' => false, 'message' => 'No edits could be applied — the model\'s "find" text did not match the content.'], 500);
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
            'content' => "You are a precise line-editor tool for an appliance buyback service website "
                . "based in Tashkent, Uzbekistan (bilingual RU/UZ). "
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
                . "- Keep the language as specified for this field (" . ($isRu ? 'Russian' : 'Uzbek') . ").\n"
                . "- Preserve all template variables exactly as-is: {{page.title}}, {{global.phone}}, "
                . "{{global.email}}, {{global.address}}, {{global.working_hours}}, {{global.site_name}}, "
                . "{{date.year}}, {{date.month}} and any other {{...}} placeholder. Never invent new variables.\n"
                . ($isHtml
                    ? "- The value is HTML. Preserve the existing structure, CSS classes "
                        . "(e.g. content-section, info-card, process-step, faq-item, links-tile, btn, btn-primary) "
                        . "and inline styles unless the user explicitly asks to change them.\n"
                    : "- For short fields (titles, meta titles, meta descriptions) respect reasonable length "
                        . "limits (titles ~60-70 chars, meta descriptions ~150-160 chars).\n"),
        ];

        $historyMessages = $this->buildHistoryMessages($history);

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
            $edits = $this->parseEditsJson($modelOutput);
            $round = $this->applyEditsPartial($workingContent, $edits);
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
                        . "Current value of the field (line numbers shown only for reference):\n\n"
                        . $this->numberLines($text);
                    $retryMessages = array_merge($messages, [
                        ['role' => 'assistant', 'content' => $modelOutput],
                        ['role' => 'user', 'content' => $correction],
                    ]);
                    $retryOutput = OpenRouter::chat($retryMessages, $model);
                    $retryEdits = $this->parseEditsJson($retryOutput);
                    $retryRound = $this->applyEditsPartial($text, $retryEdits);
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
            }

            $response = [
                'success' => true,
                'result'  => $text,
                'changes' => $applied,
            ];
            if (!empty($failed)) {
                $response['unresolved'] = array_map([$this, 'describeFailedEdit'], $failed);
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
     * This parses that patch. Throws only when the output isn't valid JSON
     * of the expected shape — actual find/replace failures are handled by
     * applyEditsPartial() below without discarding the whole batch.
     */
    private function parseEditsJson($modelOutput) {
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
        return $data['edits'];
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
        
        $id = $_POST['id'] ?? null;
        if ($id) {
            $this->pageModel->delete($id);
            $_SESSION['success'] = 'Page deleted successfully';
        }
        
        $this->redirect('/admin/pages');
    }
}