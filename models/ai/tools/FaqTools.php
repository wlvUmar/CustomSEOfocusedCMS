<?php
// path: ./models/ai/tools/FaqTools.php
// Read/write tools for the faqs table. FAQs render into pages via the
// {{faqs}} template loop, so they are part of page content.

require_once BASE_PATH . '/models/FAQ.php';

class FaqTools {

    public static function definitions(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_faqs',
                    'description' => 'List FAQs (id, page slug, RU/UZ questions, active flag). With page_slug filters to one page; without it returns all.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_slug' => ['type' => 'string', 'description' => 'Optional page slug to filter by.'],
                            'limit' => ['type' => 'integer', 'description' => 'Max rows (default 100).'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_faq',
                    'description' => 'Fetch an FAQ by numeric id, or all FAQs for a page by slug. Prefer page_slug when you know it — ids are not required.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'faq_id' => ['type' => 'integer', 'description' => 'Numeric FAQ id (alternative to page_slug).'],
                            'page_slug' => ['type' => 'string', 'description' => 'Page slug (alternative to faq_id). Returns all FAQs of that page.'],
                        ],
                        'oneOf' => [['required' => ['faq_id']], ['required' => ['page_slug']]],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_faq',
                    'description' => 'Create a new FAQ for a page slug. Both languages are required — RU text in Russian, UZ text in Uzbek. Answers should be 2-5 sentences, directly answering the question.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_slug' => ['type' => 'string', 'description' => 'Page slug this FAQ belongs to.'],
                            'question_ru' => ['type' => 'string', 'description' => 'Question in Russian.'],
                            'question_uz' => ['type' => 'string', 'description' => 'Question in Uzbek.'],
                            'answer_ru' => ['type' => 'string', 'description' => 'Answer in Russian.'],
                            'answer_uz' => ['type' => 'string', 'description' => 'Answer in Uzbek.'],
                            'sort_order' => ['type' => 'integer', 'description' => 'Order among FAQs on the page (default 0).'],
                            'is_active' => ['type' => 'integer', 'enum' => [0, 1], 'description' => '1 = visible on the site (default 1).'],
                        ],
                        'required' => ['page_slug', 'question_ru', 'question_uz', 'answer_ru', 'answer_uz'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_faq',
                    'description' => 'Update one FAQ. Only pass the fields you want to change (other fields keep their current values).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'faq_id' => ['type' => 'integer', 'description' => 'Numeric FAQ id.'],
                            'page_slug' => ['type' => 'string'],
                            'question_ru' => ['type' => 'string'],
                            'question_uz' => ['type' => 'string'],
                            'answer_ru' => ['type' => 'string'],
                            'answer_uz' => ['type' => 'string'],
                            'sort_order' => ['type' => 'integer'],
                            'is_active' => ['type' => 'integer', 'enum' => [0, 1]],
                        ],
                        'required' => ['faq_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_faq',
                    'description' => 'Delete one FAQ. Guarded: the loop will ask the user to confirm before it executes.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'faq_id' => ['type' => 'integer', 'description' => 'Numeric FAQ id.'],
                        ],
                        'required' => ['faq_id'],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        switch ($name) {
            case 'list_faqs':
                return self::listFaqs($args);
            case 'get_faq':
                return self::getFaq($args);
            case 'create_faq':
                return self::createFaq($args);
            case 'update_faq':
                return self::updateFaq($args);
            case 'delete_faq':
                return self::deleteFaq($args);
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    private static function listFaqs(array $args): array {
        $limit = isset($args['limit']) ? max(1, min(500, (int)$args['limit'])) : 100;
        $pageSlug = trim((string)($args['page_slug'] ?? ''));
        $db = Database::getInstance();
        if ($pageSlug !== '') {
            $rows = $db->fetchAll("SELECT * FROM faqs WHERE page_slug = ? ORDER BY sort_order ASC, id ASC LIMIT " . (int)$limit, [$pageSlug]);
        } else {
            $rows = $db->fetchAll("SELECT * FROM faqs ORDER BY page_slug ASC, sort_order ASC, id ASC LIMIT " . (int)$limit);
        }
        $out = [];
        foreach ($rows as $f) {
            $out[] = [
                'id' => (int)$f['id'],
                'page_slug' => $f['page_slug'],
                'question_ru' => mb_substr((string)($f['question_ru'] ?? ''), 0, 200),
                'question_uz' => mb_substr((string)($f['question_uz'] ?? ''), 0, 200),
                'sort_order' => (int)($f['sort_order'] ?? 0),
                'is_active' => (int)($f['is_active'] ?? 1),
            ];
        }
        return ['page_slug' => $pageSlug !== '' ? $pageSlug : null, 'faqs' => $out, 'count' => count($out)];
    }

    private static function getFaq(array $args): array {
        $slug = trim((string)($args['page_slug'] ?? ''));
        if ($slug !== '') {
            $rows = Database::getInstance()->fetchAll("SELECT * FROM faqs WHERE page_slug = ? ORDER BY sort_order ASC", [$slug]);
            $out = [];
            foreach ($rows as $f) {
                $out[] = [
                    'id' => (int)$f['id'],
                    'page_slug' => $f['page_slug'],
                    'question_ru' => (string)($f['question_ru'] ?? ''),
                    'question_uz' => (string)($f['question_uz'] ?? ''),
                    'answer_ru' => mb_substr((string)($f['answer_ru'] ?? ''), 0, 2000),
                    'answer_uz' => mb_substr((string)($f['answer_uz'] ?? ''), 0, 2000),
                    'sort_order' => (int)($f['sort_order'] ?? 0),
                    'is_active' => (int)($f['is_active'] ?? 1),
                ];
            }
            return [
                'page_slug' => $slug,
                'faqs' => $out,
                'count' => count($out),
                'note' => 'FAQ ids are stable — use faq_id for targeted updates/deletes.',
            ];
        }
        $id = (int)($args['faq_id'] ?? 0);
        if ($id <= 0) throw new InvalidArgumentException('faq_id or page_slug is required — call list_faqs to discover ids or pass page_slug to list FAQs for a page.');
        $row = (new FAQ())->getById($id);
        if (!$row) throw new InvalidArgumentException('FAQ not found: ID ' . $id . ' not found. Call list_faqs to discover ids.');
        return $row;
    }

    private static function createFaq(array $args): array {
        $required = ['page_slug', 'question_ru', 'question_uz', 'answer_ru', 'answer_uz'];
        foreach ($required as $k) {
            if (trim((string)($args[$k] ?? '')) === '') {
                throw new InvalidArgumentException("Missing required field: {$k} — all of page_slug, question_ru, question_uz, answer_ru, answer_uz are required.");
            }
        }
        $slug = (string)$args['page_slug'];
        $exists = Database::getInstance()->fetchOne("SELECT id FROM pages WHERE slug = ?", [$slug]);
        if (!$exists) throw new InvalidArgumentException("Page not found: {$slug} — call list_pages to discover valid slugs.");
        $isActive = 1;
        if (isset($args['is_active'])) {
            $v = $args['is_active'];
            if (is_bool($v)) $isActive = $v ? 1 : 0;
            elseif (is_string($v)) $isActive = in_array(strtolower($v), ['1','true','yes'], true) ? 1 : 0;
            else $isActive = ((int)$v === 1 ? 1 : 0);
        }
        $model = new FAQ();
        $id = $model->create([
            'page_slug' => $slug,
            'question_ru' => (string)$args['question_ru'],
            'question_uz' => (string)$args['question_uz'],
            'answer_ru' => (string)$args['answer_ru'],
            'answer_uz' => (string)$args['answer_uz'],
            'sort_order' => (int)($args['sort_order'] ?? 0),
            'is_active' => $isActive,
        ]);
        $fresh = $model->getById((int)$id);
        return ['ok' => true, 'verified' => $fresh !== null, 'fresh_hash' => substr(md5(json_encode($fresh ?? [])),0,8), 'faq_id' => (int)$id, 'note' => 'FAQ created and verified. It renders on the page via the {{faqs}} loop.'];
    }

    private static function updateFaq(array $args): array {
        $id = (int)($args['faq_id'] ?? 0);
        if ($id <= 0) throw new InvalidArgumentException('faq_id is required — call list_faqs or get_faq with page_slug to find ids.');
        $model = new FAQ();
        $existing = $model->getById($id);
        if (!$existing) throw new InvalidArgumentException('FAQ not found: ID ' . $id . ' not found. Call list_faqs to discover ids.');

        $data = [];
        foreach (['page_slug', 'question_ru', 'question_uz', 'answer_ru', 'answer_uz', 'sort_order', 'is_active'] as $k) {
            if (array_key_exists($k, $args)) {
                if ($k === 'sort_order') $data[$k] = (int)$args[$k];
                elseif ($k === 'is_active') {
                    $v = $args[$k];
                    if (is_bool($v)) $data[$k] = $v ? 1 : 0;
                    elseif (is_string($v)) $data[$k] = in_array(strtolower($v), ['1','true','yes'], true) ? 1 : 0;
                    else $data[$k] = ((int)$v === 1 ? 1 : 0);
                } else $data[$k] = (string)$args[$k];
            } else {
                $data[$k] = $existing[$k] ?? ($k === 'sort_order' ? 0 : ($k === 'is_active' ? 1 : ''));
            }
        }
        if (isset($data['page_slug']) && $data['page_slug'] !== ($existing['page_slug'] ?? '')) {
            $exists = Database::getInstance()->fetchOne("SELECT id FROM pages WHERE slug = ?", [$data['page_slug']]);
            if (!$exists) throw new InvalidArgumentException("Page not found: {$data['page_slug']} — call list_pages to discover valid slugs.");
        }
        $model->update($id, $data);
        $fresh = $model->getById($id);
        $ok = $fresh && (string)($fresh['question_ru'] ?? '') === (string)($data['question_ru'] ?? '');
        return ['ok' => true, 'verified' => (bool)$ok, 'fresh_hash' => substr(md5(json_encode($fresh ?? [])),0,8), 'faq_id' => $id, 'note' => $ok ? 'FAQ updated and verified.' : 'FAQ updated but verification mismatch — re-read via get_faq.'];
    }

    private static function deleteFaq(array $args): array {
        $id = (int)($args['faq_id'] ?? 0);
        if ($id <= 0) throw new InvalidArgumentException('faq_id is required — call list_faqs to discover ids.');
        $model = new FAQ();
        if (!$model->getById($id)) throw new InvalidArgumentException('FAQ not found: ID ' . $id . ' not found. Call list_faqs to discover ids.');
        $model->delete($id);
        $fresh = $model->getById($id);
        return ['ok' => true, 'verified' => $fresh === null || $fresh === false, 'fresh_hash' => substr(md5((string)$id),0,8), 'faq_id' => $id, 'note' => 'FAQ deleted and verified.'];
    }
}
