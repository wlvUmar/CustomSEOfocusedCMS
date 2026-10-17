<?php
// path: ./models/ai/PageAiEditor.php
// AI-assisted page-field editing engine, extracted from
// controllers/admin/PageAdminController.php (hygiene audit P1).
// Owns the Opencode edit/chat flows plus all section/parsing helpers.
// HTTP stays in the controller: it authenticates, delegates here with
// $_POST, and renders the returned [data, code] pair as JSON.

require_once BASE_PATH . '/models/ai/PromptLoader.php';

class PageAiEditor {
    private $pageModel;

    public function __construct($pageModel) {
        $this->pageModel = $pageModel;
    }

    public function handleAiEdit(array $post) {
        if (!isset($post['csrf_token']) || !validateCSRFToken($post['csrf_token'])) {
            return [['success' => false, 'message' => 'CSRF token validation failed'], 400];
        }

        $pageId = intval($post['page_id'] ?? 0);
        $field = $post['field'] ?? '';
        $prompt = trim($post['prompt'] ?? '');
        $model = $post['model'] ?? '';
        $mode = $post['mode'] ?? 'full'; // 'full' = replace entire field, 'edits' = targeted find/replace

        $allowedFields = [
            'content_ru', 'content_uz',
            'title_ru', 'title_uz',
            'meta_title_ru', 'meta_title_uz',
            'meta_description_ru', 'meta_description_uz',
        ];
        $allowedModes = ['full', 'edits'];

        if ($pageId <= 0) {
            return [['success' => false, 'message' => 'Invalid page'], 400];
        }
        if (!in_array($field, $allowedFields, true)) {
            return [['success' => false, 'message' => 'Invalid target field'], 400];
        }
        if (!in_array($mode, $allowedModes, true)) {
            return [['success' => false, 'message' => 'Invalid mode'], 400];
        }
        if ($prompt === '') {
            return [['success' => false, 'message' => 'Prompt cannot be empty'], 400];
        }

        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            return [['success' => false, 'message' => 'Page not found'], 404];
        }

        require_once BASE_PATH . '/models/Opencode.php';

        // Prefer whatever is currently in the browser's form/editor (unsaved edits included);
        // only fall back to the saved DB value when the client didn't send anything yet.
        $workingContent = (string)($post['working_content'] ?? '');
        $currentValue = $workingContent !== '' ? $workingContent : (string)($page[$field] ?? '');
        $historyMessages = $this->buildHistoryMessages($post['history'] ?? '[]');

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

        try {
            $systemContent = PromptLoader::render('page-editor-generate', [
                'field_label' => $fieldLabels[$field],
                'site_name' => $siteName,
                'lang_name' => $isRu ? 'Russian' : 'Uzbek',
                'mode_edits' => $mode === 'edits',
                'mode_full' => $mode !== 'edits',
                'is_html' => $isHtml,
                'is_short' => !$isHtml,
            ]);
        } catch (Throwable $e) {
            return [['success' => false, 'message' => 'AI prompt file missing: ' . $e->getMessage()], 500];
        }

        $system = [
            'role' => 'system',
            'content' => $systemContent,
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
            $result = Opencode::chat($messages, $model, 0.7, $maxTokens);
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
                    return [['success' => false, 'message' => 'No edits could be applied — the model\'s "find" text did not match the content.'], 500];
                }
            }
            return [$response, 200];
        } catch (Exception $e) {
            return [['success' => false, 'message' => $e->getMessage()], 500];
        }
    }

    public function handleAiChat(array $post) {
        if (!isset($post['csrf_token']) || !validateCSRFToken($post['csrf_token'])) {
            return [['success' => false, 'message' => 'CSRF token validation failed'], 400];
        }

        $pageId = intval($post['page_id'] ?? 0);
        $field = $post['field'] ?? '';
        $prompt = trim($post['prompt'] ?? '');
        $model = $post['model'] ?? '';
        $workingContent = (string)($post['working_content'] ?? '');
        $history = $post['history'] ?? '[]';

        $allowedFields = [
            'content_ru', 'content_uz',
            'title_ru', 'title_uz',
            'meta_title_ru', 'meta_title_uz',
            'meta_description_ru', 'meta_description_uz',
        ];

        if ($pageId <= 0) {
            return [['success' => false, 'message' => 'Invalid page'], 400];
        }
        if (!in_array($field, $allowedFields, true)) {
            return [['success' => false, 'message' => 'Invalid target field'], 400];
        }
        if ($prompt === '') {
            return [['success' => false, 'message' => 'Prompt cannot be empty'], 400];
        }

        $page = $this->pageModel->getById($pageId);
        if (!$page) {
            return [['success' => false, 'message' => 'Page not found'], 404];
        }

        // Fall back to the stored value on the very first turn (empty working copy)
        if ($workingContent === '') {
            $workingContent = (string)($page[$field] ?? '');
        }

        require_once BASE_PATH . '/models/Opencode.php';

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

        try {
            $systemContent = PromptLoader::render('page-editor-chat', [
                'field_label' => $fieldLabels[$field],
                'site_name' => $siteName,
                'lang_name' => $isRu ? 'Russian' : 'Uzbek',
                'is_html' => $isHtml,
                'is_short' => !$isHtml,
                'scoped' => $scopedSections !== null,
            ]);
        } catch (Throwable $e) {
            return [['success' => false, 'message' => 'AI prompt file missing: ' . $e->getMessage()], 500];
        }

        $system = [
            'role' => 'system',
            'content' => $systemContent,
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
            $modelOutput = Opencode::chat($messages, $model, 0.7, $maxTokens);
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
                $modelOutput = Opencode::chat($messages, $model, 0.7, $maxTokens);
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
                    $retryOutput = Opencode::chat($retryMessages, $model, 0.7, $retryMaxTokens);
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
                return [[
                    'success' => false,
                    'message' => 'Could not apply the edit. ' . $this->describeFailuresForModel($failed, true),
                ], 500];
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
            return [$response, 200];
        } catch (Exception $e) {
            return [['success' => false, 'message' => $e->getMessage()], 500];
        }
    }

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
            if (count($messages) > 12) {
                $messages = array_slice($messages, -12);
            }
        }
        return $messages;
    }

    private function numberLines($text) {
        $lines = explode("\n", $text);
        $out = [];
        foreach ($lines as $i => $line) {
            $out[] = ($i + 1) . ': ' . $line;
        }
        return implode("\n", $out);
    }

    private function parseEditsResponse($modelOutput) {
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
        sort($chosenIdx);
        $selected = [];
        foreach ($chosenIdx as $i) $selected[] = $sections[$i];
        return $selected;
    }

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

    private function describeFailedEdit($edit) {
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
}
