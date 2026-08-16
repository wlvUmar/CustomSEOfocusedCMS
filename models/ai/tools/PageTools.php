<?php
// path: ./models/ai/tools/PageTools.php
// Tools for reading and editing the `pages` table. Write access is restricted
// to the same field allowlist the existing page editor's AI panel uses.

require_once BASE_PATH . '/models/Page.php';

class PageTools {

    /** Fields the agent may write to. Kept in sync with the page editor's AI allowlist. */
    public const FIELDS = [
        'content_ru', 'content_uz',
        'title_ru', 'title_uz',
        'meta_title_ru', 'meta_title_uz',
        'meta_description_ru', 'meta_description_uz',
    ];

    public static function definitions(): array {
        $fieldEnum = self::FIELDS;
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_pages',
                    'description' => 'List pages (id, slug, RU/UZ titles, published flag). Use this to orient before touching anything.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => ['type' => 'integer', 'description' => 'Max rows to return (default 100).'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_page',
                    'description' => 'Fetch one page by slug (preferred) or id, with its RU/UZ titles, content, and meta fields. Long HTML fields are truncated to ~12000 chars with a "truncated" flag — targeted edits on the full value should use str_replace_field with the snippets you see here.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                        ],
                        'oneOf' => [['required' => ['page_id']], ['required' => ['slug']]],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_content',
                    'description' => 'Full-text-ish search across page titles and content in one language. Returns slug, title, and a short text snippet.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Search term (plain text, no regex).'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz'], 'description' => 'Language of the fields to search (default ru).'],
                            'limit' => ['type' => 'integer', 'description' => 'Max results (default 10).'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_sections',
                    'description' => 'List the logical sections of a page\'s content field (they are delimited by "<!-- Section Name -->" comments in this CMS). Returns section names, char counts, and a short preview so you can target an edit precisely.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz'], 'description' => 'Which content field (default ru).'],
                        ],
                        'required' => ['page_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'str_replace_field',
                    'description' => 'Apply a precise find-and-replace to one field of one page. The "find" text must appear EXACTLY once in the current stored value (copy it character-for-character from get_page output, including HTML tags). This is the preferred edit tool — use it instead of set_field whenever possible.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'field' => ['type' => 'string', 'enum' => self::FIELDS, 'description' => 'Target field.'],
                            'find' => ['type' => 'string', 'description' => 'Exact existing text to locate (must occur exactly once).'],
                            'replace' => ['type' => 'string', 'description' => 'New text. Use "" to delete the found text.'],
                        ],
                        'required' => ['page_id', 'field', 'find'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'set_field',
                    'description' => 'Replace the ENTIRE value of one field of one page. Guarded: the loop will ask the user to confirm before it executes. Prefer str_replace_field for incremental changes.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'field' => ['type' => 'string', 'enum' => self::FIELDS, 'description' => 'Target field.'],
                            'value' => ['type' => 'string', 'description' => 'The complete new value of the field.'],
                        ],
                        'required' => ['page_id', 'field', 'value'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'insert_section',
                    'description' => 'Insert a new HTML section (wrapped in a "<!-- Name -->" marker) into a page\'s content field, either at the top or the end. Sections must use the site\'s existing design classes (content-section, info-card, process-step, faq-item, links-tile, btn/btn-primary, ...) and preserve template variables.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz'], 'description' => 'Which content field (default ru).'],
                            'html' => ['type' => 'string', 'description' => 'The full HTML of the new section.'],
                            'name' => ['type' => 'string', 'description' => 'Section name for the HTML comment marker (default "Section").'],
                            'position' => ['type' => 'string', 'enum' => ['top', 'end'], 'description' => 'Where to insert (default end).'],
                        ],
                        'required' => ['page_id', 'html'],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        switch ($name) {
            case 'list_pages':
                return self::listPages($args);
            case 'get_page':
                return self::getPage($args);
            case 'search_content':
                return self::searchContent($args);
            case 'list_sections':
                return self::listSections($args);
            case 'str_replace_field':
                return self::strReplaceField($args);
            case 'set_field':
                return self::setField($args);
            case 'insert_section':
                return self::insertSection($args);
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    private static function listPages(array $args): array {
        $limit = isset($args['limit']) ? max(1, min(500, (int)$args['limit'])) : 100;
        $model = new Page();
        $rows = $model->getAll(true);
        $out = [];
        foreach (array_slice($rows, 0, $limit) as $p) {
            $out[] = [
                'id' => (int)$p['id'],
                'slug' => $p['slug'],
                'title_ru' => $p['title_ru'] ?? '',
                'title_uz' => $p['title_uz'] ?? '',
                'is_published' => (int)($p['is_published'] ?? 1),
                'updated_at' => $p['updated_at'] ?? '',
            ];
        }
        return ['pages' => $out, 'count' => count($out), 'truncated' => count($rows) > $limit];
    }

    private static function getPage(array $args): array {
        $model = new Page();
        if (!empty($args['page_id'])) {
            $page = $model->getById((int)$args['page_id']);
        } elseif (!empty($args['slug'])) {
            $page = $model->getBySlug((string)$args['slug']);
        } else {
            throw new InvalidArgumentException('Provide either page_id or slug');
        }
        if (!$page) {
            throw new InvalidArgumentException('Page not found');
        }

        $page = self::clipRow($page, [
            'content_ru' => 12000, 'content_uz' => 12000,
            'meta_description_ru' => 2000, 'meta_description_uz' => 2000,
            'og_description_ru' => 2000, 'og_description_uz' => 2000,
            'jsonld_ru' => 4000, 'jsonld_uz' => 4000,
        ]);

        $keep = [
            'id', 'parent_id', 'depth', 'slug',
            'title_ru', 'title_uz', 'content_ru', 'content_uz',
            'meta_title_ru', 'meta_title_uz', 'meta_description_ru', 'meta_description_uz',
            'og_title_ru', 'og_title_uz', 'og_description_ru', 'og_description_uz',
            'canonical_url', 'enable_rotation', 'rotation_mode', 'selected_rotation_id',
            'is_published', 'show_link_widget', 'updated_at',
        ];
        $result = [];
        foreach ($keep as $k) {
            if (array_key_exists($k, $page)) {
                $result[$k] = $page[$k];
            }
        }
        return $result;
    }

    private static function searchContent(array $args): array {
        $query = trim((string)($args['query'] ?? ''));
        if ($query === '') {
            throw new InvalidArgumentException('query is required');
        }
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $limit = isset($args['limit']) ? max(1, min(50, (int)$args['limit'])) : 10;

        $db = Database::getInstance();
        $like = '%' . $query . '%';
        $rows = $db->fetchAll(
            "SELECT id, slug, title_{$lang} AS title, content_{$lang} AS content, is_published, updated_at
             FROM pages
             WHERE title_{$lang} LIKE ? OR content_{$lang} LIKE ?
             ORDER BY updated_at DESC
             LIMIT " . (int)$limit,
            [$like, $like]
        );
        $out = [];
        foreach ($rows as $r) {
            $plain = trim(strip_tags((string)$r['content']));
            $snippet = mb_substr($plain, 0, 220);
            $out[] = [
                'id' => (int)$r['id'],
                'slug' => $r['slug'],
                'title' => $r['title'] ?? '',
                'is_published' => (int)$r['is_published'],
                'snippet' => $snippet,
            ];
        }
        return ['query' => $query, 'lang' => $lang, 'results' => $out, 'count' => count($out)];
    }

    private static function listSections(array $args): array {
        $pageId = (int)($args['page_id'] ?? 0);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        if ($pageId <= 0) {
            throw new InvalidArgumentException('page_id is required');
        }
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) {
            throw new InvalidArgumentException('Page not found');
        }
        $html = (string)($page["content_{$lang}"] ?? '');
        $sections = self::splitIntoSections($html);
        $out = [];
        foreach ($sections as $s) {
            $out[] = [
                'name' => $s['name'],
                'chars' => mb_strlen($s['text']),
                'preview' => mb_substr(trim(strip_tags($s['text'])), 0, 160),
            ];
        }
        return ['page_id' => $pageId, 'lang' => $lang, 'sections' => $out, 'count' => count($out)];
    }

    private static function strReplaceField(array $args): array {
        $pageId = (int)($args['page_id'] ?? 0);
        $field = (string)($args['field'] ?? '');
        $find = (string)($args['find'] ?? '');
        $replace = (string)($args['replace'] ?? '');
        if ($pageId <= 0) throw new InvalidArgumentException('page_id is required');
        if (!in_array($field, self::FIELDS, true)) throw new InvalidArgumentException("Field not writable: {$field}");
        if ($find === '') throw new InvalidArgumentException('find is required');

        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found');

        $current = (string)($page[$field] ?? '');
        $count = substr_count($current, $find);
        if ($count === 0) {
            throw new InvalidArgumentException('The "find" text was not found in the current value — copy it exactly from get_page output.');
        }
        if ($count > 1) {
            throw new InvalidArgumentException('The "find" text occurs ' . $count . ' times — too ambiguous to apply safely. Include surrounding context to make it unique.');
        }
        $updated = str_replace($find, $replace, $current);
        $model->update($pageId, [$field => $updated]);
        return [
            'ok' => true,
            'page_id' => $pageId,
            'field' => $field,
            'applied' => 1,
            'before_chars' => mb_strlen($current),
            'after_chars' => mb_strlen($updated),
            'note' => 'Change saved to the database. The live site shows it after the next cache refresh.',
        ];
    }

    private static function setField(array $args): array {
        $pageId = (int)($args['page_id'] ?? 0);
        $field = (string)($args['field'] ?? '');
        $value = (string)($args['value'] ?? '');
        if ($pageId <= 0) throw new InvalidArgumentException('page_id is required');
        if (!in_array($field, self::FIELDS, true)) throw new InvalidArgumentException("Field not writable: {$field}");

        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found');

        $model->update($pageId, [$field => $value]);
        return [
            'ok' => true,
            'page_id' => $pageId,
            'field' => $field,
            'chars' => mb_strlen($value),
            'note' => 'Change saved to the database. The live site shows it after the next cache refresh.',
        ];
    }

    private static function insertSection(array $args): array {
        $pageId = (int)($args['page_id'] ?? 0);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $html = (string)($args['html'] ?? '');
        $name = trim((string)($args['name'] ?? ''));
        $position = ($args['position'] ?? 'end') === 'top' ? 'top' : 'end';
        if ($pageId <= 0) throw new InvalidArgumentException('page_id is required');
        if (trim($html) === '') throw new InvalidArgumentException('html is required');

        if ($name === '') $name = 'Section';
        $block = "<!-- {$name} -->\n" . $html;

        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found');

        $current = (string)($page["content_{$lang}"] ?? '');
        if ($position === 'top') {
            $updated = $block . "\n\n" . ltrim($current);
        } else {
            $updated = rtrim($current) . "\n\n" . $block;
        }
        $model->update($pageId, ["content_{$lang}" => $updated]);
        return [
            'ok' => true,
            'page_id' => $pageId,
            'lang' => $lang,
            'position' => $position,
            'name' => $name,
            'content_chars' => mb_strlen($updated),
            'note' => 'Section inserted. Call render_preview to judge it visually.',
        ];
    }

    /**
     * Split an HTML field on its "<!-- Section Name -->" comment markers
     * (mirrors the page editor's scoped-edit logic).
     */
    public static function splitIntoSections(string $html): array {
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

    /** Truncate long string fields to keep tool results small enough to feed back to the model. */
    public static function clipRow(array $row, array $limits): array {
        foreach ($limits as $field => $max) {
            if (isset($row[$field]) && is_string($row[$field]) && mb_strlen($row[$field]) > $max) {
                $row[$field . '_truncated'] = true;
                $row[$field] = mb_substr($row[$field], 0, $max) . "\n… [truncated — full field is " . mb_strlen($row[$field]) . " chars; target edits with str_replace_field]";
            }
        }
        return $row;
    }
}
