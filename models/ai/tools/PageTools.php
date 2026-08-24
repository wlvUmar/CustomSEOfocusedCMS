<?php
// path: ./models/ai/tools/PageTools.php
// Tools for reading and editing the `pages` table. Write access is restricted
// to the same field allowlist the existing page editor's AI panel uses.

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/PageRevision.php';

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
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_page_revisions',
                    'description' => 'List recent revision snapshots for a page (auto-saved before every update). Use to find a version to restore after a bad AI edit.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'limit' => ['type' => 'integer', 'description' => 'Max revisions to return (default 10, max 20).'],
                        ],
                        'oneOf' => [['required' => ['page_id']], ['required' => ['slug']]],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_page_revision',
                    'description' => 'Fetch a single page revision snapshot by id (titles, meta, content truncated for preview).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'revision_id' => ['type' => 'integer', 'description' => 'Revision id from list_page_revisions.'],
                        ],
                        'required' => ['revision_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'restore_page_revision',
                    'description' => 'Restore a page to a previous revision snapshot (undoes the AI or admin edit). Guarded — requires user approval. The current state is auto-saved as a new revision before restoring, so this is reversible.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'revision_id' => ['type' => 'integer', 'description' => 'Revision id to restore.'],
                        ],
                        'required' => ['revision_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_section',
                    'description' => 'Fetch ONE section of a page\'s content (untruncated, unlike get_page). Use after list_sections to target precisely. Returns full HTML of that section including its <!-- Name --> marker, plus hash so you can verify edits.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz'], 'description' => 'Which content field (default ru).'],
                            'section' => ['type' => 'string', 'description' => 'Section name (as returned by list_sections) or numeric index 0-based. Prefer name.'],
                        ],
                        'required' => ['page_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_content_chunk',
                    'description' => 'Read a raw byte-range chunk of a page\'s content field (for pages with no <!-- --> sections or when you need surrounding context beyond one section). Prefer get_section when sections exist.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz'], 'description' => 'Which content field (default ru).'],
                            'offset' => ['type' => 'integer', 'description' => 'Character offset (0-based, default 0).'],
                            'limit' => ['type' => 'integer', 'description' => 'Max characters to return (default 6000, max 12000).'],
                        ],
                        'required' => ['page_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_section',
                    'description' => 'Replace an ENTIRE section\'s HTML (including its <!-- Name --> marker is preserved). Use to restructure a section, add/remove divs, or rewrite it completely. You may use any HTML/tags and inline style="" overrides — do not edit pages.min.css. The other sections are untouched.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz'], 'description' => 'Which content field (default ru).'],
                            'section' => ['type' => 'string', 'description' => 'Section name or index to replace.'],
                            'html' => ['type' => 'string', 'description' => 'Full new HTML for this section (should start with the section\'s inner HTML; the <!-- Name --> marker is kept automatically). May contain any tags/divs and inline style="" attributes.'],
                        ],
                        'required' => ['page_id', 'section', 'html'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'patch_section',
                    'description' => 'Precise find-and-replace scoped to ONE section only (avoids the ambiguous global str_replace_field problem). The find text must appear exactly once inside that section. Use for small line edits, style tweaks, or text fixes within a section.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz'], 'description' => 'Which content field (default ru).'],
                            'section' => ['type' => 'string', 'description' => 'Section name or index to patch.'],
                            'find' => ['type' => 'string', 'description' => 'Exact text to find inside the section (must occur exactly once in that section).'],
                            'replace' => ['type' => 'string', 'description' => 'Replacement text. Use "" to delete. May include inline style="" attributes.'],
                        ],
                        'required' => ['page_id', 'section', 'find'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'set_section_style',
                    'description' => 'Override styles of a section via inline style="" on its top-level element (without touching pages.min.css). Merges the given CSS declarations into the section\'s first HTML tag\'s style attribute. By default also syncs the same style to the matching section name in the other language (ru↔uz).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz'], 'description' => 'Which content field to style (default ru).'],
                            'section' => ['type' => 'string', 'description' => 'Section name or index.'],
                            'style' => ['type' => 'string', 'description' => 'CSS declarations, e.g. "background:var(--teal); color:#fff; padding:32px; border-radius:16px" — will be merged into style="" attribute.'],
                            'sync' => ['type' => 'boolean', 'description' => 'If true (default), also apply same style to the matching section name in the other language. Set false to style only one language.'],
                        ],
                        'required' => ['page_id', 'section', 'style'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'wrap_section',
                    'description' => 'Wrap a section\'s inner content with a new div/element (useful to add a styled container, background, or layout wrapper without rewriting the section). The wrapper may contain inline style="" and any classes.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz'], 'description' => 'Which content field (default ru).'],
                            'section' => ['type' => 'string', 'description' => 'Section name or index to wrap.'],
                            'wrapper_open' => ['type' => 'string', 'description' => 'Opening tag, e.g. "<div style=\"background:var(--surface); padding:24px; border-radius:12px\">"'],
                            'wrapper_close' => ['type' => 'string', 'description' => 'Closing tag, e.g. "</div>" (default "</div>").'],
                        ],
                        'required' => ['page_id', 'section', 'wrapper_open'],
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
            case 'get_section':
                return self::getSection($args);
            case 'get_content_chunk':
                return self::getContentChunk($args);
            case 'str_replace_field':
                return self::strReplaceField($args);
            case 'set_field':
                return self::setField($args);
            case 'insert_section':
                return self::insertSection($args);
            case 'update_section':
                return self::updateSection($args);
            case 'patch_section':
                return self::patchSection($args);
            case 'set_section_style':
                return self::setSectionStyle($args);
            case 'wrap_section':
                return self::wrapSection($args);
            case 'list_page_revisions':
                return self::listRevisions($args);
            case 'get_page_revision':
                return self::getRevision($args);
            case 'restore_page_revision':
                return self::restoreRevision($args);
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

    private static function resolveGeneralPageId(array $args): int {
        $slug = trim((string)($args['slug'] ?? ''));
        if ($slug !== '') {
            $db = Database::getInstance();
            $page = $db->fetchOne("SELECT * FROM pages WHERE slug = ?", [$slug]);
            if (!$page) throw new InvalidArgumentException("Page not found: {$slug}");
            return (int)$page['id'];
        }
        $id = (int)($args['page_id'] ?? 0);
        if ($id <= 0) throw new InvalidArgumentException('page_id or slug is required');
        return $id;
    }

    private static function listSections(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found');
        $html = (string)($page["content_{$lang}"] ?? '');
        $sections = self::splitIntoSections($html);
        $out = [];
        $totalChars = 0;
        foreach ($sections as $idx => $s) {
            $chars = mb_strlen($s['text']);
            $totalChars += $chars;
            $hash = substr(md5($s['text']), 0, 8);
            $out[] = [
                'index' => $idx,
                'id' => $idx . ':' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($s['name'])),
                'name' => $s['name'],
                'chars' => $chars,
                'hash' => $hash,
                'preview' => mb_substr(trim(strip_tags($s['text'])), 0, 160),
            ];
        }
        return ['page_id' => $pageId, 'lang' => $lang, 'sections' => $out, 'count' => count($out), 'total_chars' => $totalChars, 'total_sections' => count($out)];
    }

    private static function getSection(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $sectionRef = (string)($args['section'] ?? '');
        if ($sectionRef === '') throw new InvalidArgumentException('section is required (name or index)');
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found');
        $html = (string)($page["content_{$lang}"] ?? '');
        $sections = self::splitIntoSections($html);
        if (empty($sections)) throw new InvalidArgumentException('No sections found in content_' . $lang);
        $idx = null;
        if (ctype_digit($sectionRef)) {
            $idx = (int)$sectionRef;
        } else {
            foreach ($sections as $i => $s) {
                if (mb_strtolower($s['name']) === mb_strtolower($sectionRef)) { $idx = $i; break; }
            }
            if ($idx === null) {
                // also try id match
                foreach ($sections as $i => $s) {
                    $genId = $i . ':' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($s['name']));
                    if ($genId === $sectionRef) { $idx = $i; break; }
                }
            }
        }
        if ($idx === null || !isset($sections[$idx])) throw new InvalidArgumentException('Section not found: ' . $sectionRef . ' — use list_sections to see names');
        $sec = $sections[$idx];
        return [
            'page_id' => $pageId, 'lang' => $lang, 'index' => $idx, 'name' => $sec['name'],
            'chars' => mb_strlen($sec['text']), 'hash' => substr(md5($sec['text']), 0, 8),
            'html' => $sec['text'],
            'preview' => mb_substr(trim(strip_tags($sec['text'])), 0, 300),
            'total_sections' => count($sections),
        ];
    }

    private static function getContentChunk(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $offset = max(0, (int)($args['offset'] ?? 0));
        $limit = isset($args['limit']) ? max(100, min(12000, (int)$args['limit'])) : 6000;
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found');
        $html = (string)($page["content_{$lang}"] ?? '');
        $total = mb_strlen($html);
        $chunk = mb_substr($html, $offset, $limit);
        return [
            'page_id' => $pageId, 'lang' => $lang, 'offset' => $offset, 'limit' => $limit,
            'total_chars' => $total, 'chunk_chars' => mb_strlen($chunk),
            'has_more' => ($offset + $limit) < $total,
            'chunk' => $chunk,
        ];
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

    // ---------------- Section-level granular tools ----------------

    private static function findSectionIndex(array $sections, string $ref): ?int {
        if (ctype_digit($ref)) {
            $i = (int)$ref;
            return isset($sections[$i]) ? $i : null;
        }
        foreach ($sections as $i => $s) {
            if (mb_strtolower($s['name']) === mb_strtolower($ref)) return $i;
        }
        foreach ($sections as $i => $s) {
            $genId = $i . ':' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($s['name']));
            if ($genId === $ref) return $i;
        }
        return null;
    }

    private static function rebuildContentFromSections(array $sections): string {
        $out = '';
        foreach ($sections as $i => $s) {
            if ($i > 0) $out .= "\n\n";
            $out .= rtrim($s['text']);
        }
        return $out;
    }

    private static function updateSection(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $sectionRef = (string)($args['section'] ?? '');
        $html = (string)($args['html'] ?? '');
        if ($sectionRef === '') throw new InvalidArgumentException('section is required');
        if (trim($html) === '') throw new InvalidArgumentException('html is required — may contain any tags/divs and inline style=""');
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found');
        $field = "content_{$lang}";
        $sections = self::splitIntoSections((string)($page[$field] ?? ''));
        $idx = self::findSectionIndex($sections, $sectionRef);
        if ($idx === null) throw new InvalidArgumentException('Section not found: ' . $sectionRef . ' — use list_sections');
        $oldName = $sections[$idx]['name'];
        // Preserve the <!-- Name --> marker, replace inner content
        $marker = "<!-- {$oldName} -->\n";
        // If supplied html already starts with a marker, honour it
        if (preg_match('/^\s*<!--.*?-->/s', $html)) {
            $sections[$idx]['text'] = $html;
        } else {
            $sections[$idx]['text'] = $marker . ltrim($html);
        }
        $updated = self::rebuildContentFromSections($sections);
        $model->update($pageId, [$field => $updated]);
        return ['ok'=>true,'page_id'=>$pageId,'lang'=>$lang,'section'=>$oldName,'index'=>$idx,'content_chars'=>mb_strlen($updated),'note'=>'Section replaced. Call render_preview to verify.'];
    }

    private static function patchSection(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $sectionRef = (string)($args['section'] ?? '');
        $find = (string)($args['find'] ?? '');
        $replace = (string)($args['replace'] ?? '');
        if ($sectionRef === '') throw new InvalidArgumentException('section is required');
        if ($find === '') throw new InvalidArgumentException('find is required');
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found');
        $field = "content_{$lang}";
        $sections = self::splitIntoSections((string)($page[$field] ?? ''));
        $idx = self::findSectionIndex($sections, $sectionRef);
        if ($idx === null) throw new InvalidArgumentException('Section not found: ' . $sectionRef);
        $secText = $sections[$idx]['text'];
        $count = substr_count($secText, $find);
        if ($count === 0) throw new InvalidArgumentException('find text not found inside section "' . $sections[$idx]['name'] . '" — fetch it with get_section first');
        if ($count > 1) throw new InvalidArgumentException('find text occurs ' . $count . ' times inside that section — include more surrounding context to make it unique, or use update_section');
        $sections[$idx]['text'] = str_replace($find, $replace, $secText);
        $updated = self::rebuildContentFromSections($sections);
        $model->update($pageId, [$field => $updated]);
        return ['ok'=>true,'page_id'=>$pageId,'lang'=>$lang,'section'=>$sections[$idx]['name'],'index'=>$idx,'before_chars'=>mb_strlen($secText),'after_chars'=>mb_strlen($sections[$idx]['text']),'note'=>'Section patched (1 occurrence).'];
    }

    private static function mergeStyleIntoTag(string $tagHtml, string $styleDecl): string {
        $styleDecl = trim(trim($styleDecl), ';');
        if ($styleDecl === '') return $tagHtml;
        if (!str_ends_with($styleDecl, ';')) $styleDecl .= ';';
        // If style="" already exists, merge
        if (preg_match('/\sstyle\s*=\s*"([^"]*)"/i', $tagHtml, $m)) {
            $existing = rtrim(trim($m[1]), ';');
            $merged = $existing !== '' ? $existing . '; ' . $styleDecl : $styleDecl;
            return preg_replace('/\sstyle\s*=\s*"[^"]*"/i', ' style="' . htmlspecialchars($merged, ENT_COMPAT) . '"', $tagHtml, 1);
        }
        if (preg_match('/\sstyle\s*=\s*\'([^\']*)\'/i', $tagHtml, $m)) {
            $existing = rtrim(trim($m[1]), ';');
            $merged = $existing !== '' ? $existing . '; ' . $styleDecl : $styleDecl;
            return preg_replace("/\sstyle\s*=\s*'[^']*'/i", " style=\"" . htmlspecialchars($merged, ENT_COMPAT) . "\"", $tagHtml, 1);
        }
        // No existing style — inject before >
        return preg_replace('/\s*>$/', ' style="' . htmlspecialchars($styleDecl, ENT_COMPAT) . '">', $tagHtml, 1) ?? (rtrim($tagHtml, '>') . ' style="' . htmlspecialchars($styleDecl, ENT_COMPAT) . '">');
    }

    private static function setSectionStyle(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $sectionRef = (string)($args['section'] ?? '');
        $style = trim((string)($args['style'] ?? ''));
        $sync = !array_key_exists('sync', $args) ? true : (bool)$args['sync'];
        if ($sectionRef === '') throw new InvalidArgumentException('section is required');
        if ($style === '') throw new InvalidArgumentException('style is required, e.g. "background:var(--teal); color:#fff; padding:32px"');
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found');
        $field = "content_{$lang}";
        $sections = self::splitIntoSections((string)($page[$field] ?? ''));
        $idx = self::findSectionIndex($sections, $sectionRef);
        if ($idx === null) throw new InvalidArgumentException('Section not found: ' . $sectionRef);
        $secText = $sections[$idx]['text'];
        // Find first HTML opening tag after the marker
        if (!preg_match('/<[^>]+>/', $secText, $m, PREG_OFFSET_CAPTURE)) {
            throw new InvalidArgumentException('Section "' . $sections[$idx]['name'] . '" has no HTML tag to style — use update_section or wrap_section instead');
        }
        $tag = $m[0][0];
        $pos = $m[0][1];
        $newTag = self::mergeStyleIntoTag($tag, $style);
        $sections[$idx]['text'] = substr_replace($secText, $newTag, $pos, strlen($tag));
        $updated = self::rebuildContentFromSections($sections);
        $model->update($pageId, [$field => $updated]);
        $synced = false;
        if ($sync) {
            $otherLang = $lang === 'ru' ? 'uz' : 'ru';
            $otherField = "content_{$otherLang}";
            $otherHtml = (string)($page[$otherField] ?? '');
            if ($otherHtml !== '') {
                $otherSections = self::splitIntoSections($otherHtml);
                $otherIdx = self::findSectionIndex($otherSections, $sections[$idx]['name']);
                if ($otherIdx !== null && preg_match('/<[^>]+>/', $otherSections[$otherIdx]['text'], $om, PREG_OFFSET_CAPTURE)) {
                    $oTag = $om[0][0]; $oPos = $om[0][1];
                    $newOTag = self::mergeStyleIntoTag($oTag, $style);
                    $otherSections[$otherIdx]['text'] = substr_replace($otherSections[$otherIdx]['text'], $newOTag, $oPos, strlen($oTag));
                    $otherUpdated = self::rebuildContentFromSections($otherSections);
                    $model->update($pageId, [$otherField => $otherUpdated]);
                    $synced = true;
                }
            }
        }
        return ['ok'=>true,'page_id'=>$pageId,'lang'=>$lang,'section'=>$sections[$idx]['name'],'index'=>$idx,'style'=>$style,'synced_to_other_lang'=>$synced,'content_chars'=>mb_strlen($updated),'note'=>'Inline style merged into section\'s top tag' . ($synced ? ' and synced to ' . ($lang==='ru'?'uz':'ru') : '') . '.'];
    }

    private static function wrapSection(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $sectionRef = (string)($args['section'] ?? '');
        $open = (string)($args['wrapper_open'] ?? '');
        $close = (string)($args['wrapper_close'] ?? '</div>');
        if ($sectionRef === '') throw new InvalidArgumentException('section is required');
        if (trim($open) === '') throw new InvalidArgumentException('wrapper_open is required, e.g. "<div style=\"background:var(--surface); padding:24px\">"');
        if (trim($close) === '') $close = '</div>';
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found');
        $field = "content_{$lang}";
        $sections = self::splitIntoSections((string)($page[$field] ?? ''));
        $idx = self::findSectionIndex($sections, $sectionRef);
        if ($idx === null) throw new InvalidArgumentException('Section not found: ' . $sectionRef);
        // Preserve marker on first line, wrap the rest
        $secText = $sections[$idx]['text'];
        if (preg_match('/^(<!--.*?-->\s*\n?)(.*)$/s', $secText, $mm)) {
            $marker = $mm[1];
            $inner = $mm[2];
            $sections[$idx]['text'] = $marker . $open . "\n" . $inner . "\n" . $close;
        } else {
            $sections[$idx]['text'] = $open . "\n" . $secText . "\n" . $close;
        }
        $updated = self::rebuildContentFromSections($sections);
        $model->update($pageId, [$field => $updated]);
        return ['ok'=>true,'page_id'=>$pageId,'lang'=>$lang,'section'=>$sections[$idx]['name'],'index'=>$idx,'content_chars'=>mb_strlen($updated),'note'=>'Section wrapped.'];
    }

    private static function resolvePageIdForRevision(array $args): int {
        $slug = trim((string)($args['slug'] ?? ''));
        if ($slug !== '') {
            $page = (new Page())->getBySlug($slug);
            // getBySlug only returns published pages — fall back to slug lookup via DB for revisions
            if (!$page) {
                $db = Database::getInstance();
                $page = $db->fetchOne("SELECT * FROM pages WHERE slug = ?", [$slug]);
            }
            if (!$page) throw new InvalidArgumentException("Page not found: {$slug}");
            return (int)$page['id'];
        }
        $id = (int)($args['page_id'] ?? 0);
        if ($id <= 0) throw new InvalidArgumentException('page_id or slug is required');
        return $id;
    }

    private static function listRevisions(array $args): array {
        $pageId = self::resolvePageIdForRevision($args);
        $limit = isset($args['limit']) ? max(1, min(20, (int)$args['limit'])) : 10;
        $model = new PageRevision();
        $rows = $model->getByPageId($pageId, $limit);
        return ['page_id' => $pageId, 'revisions' => $rows, 'count' => count($rows)];
    }

    private static function getRevision(array $args): array {
        $revId = (int)($args['revision_id'] ?? 0);
        if ($revId <= 0) throw new InvalidArgumentException('revision_id is required');
        $model = new PageRevision();
        $row = $model->getById($revId);
        if (!$row) throw new InvalidArgumentException('Revision not found');
        $snap = json_decode($row['snapshot'], true);
        if (!is_array($snap)) throw new RuntimeException('Revision snapshot corrupt');
        // Return a preview — clip long fields like get_page does.
        $snap = self::clipRow($snap, [
            'content_ru' => 6000, 'content_uz' => 6000,
            'meta_description_ru' => 2000, 'meta_description_uz' => 2000,
            'og_description_ru' => 2000, 'og_description_uz' => 2000,
            'jsonld_ru' => 4000, 'jsonld_uz' => 4000,
        ]);
        $keep = ['id','slug','title_ru','title_uz','content_ru','content_uz','meta_title_ru','meta_title_uz','meta_description_ru','meta_description_uz','og_title_ru','og_title_uz','og_description_ru','og_description_uz','canonical_url','is_published','updated_at'];
        $preview = [];
        foreach ($keep as $k) if (array_key_exists($k, $snap)) $preview[$k] = $snap[$k];
        return [
            'revision_id' => (int)$row['id'],
            'page_id' => (int)$row['page_id'],
            'created_at' => $row['created_at'],
            'source' => $row['source'],
            'created_by_name' => $row['created_by_name'],
            'changed_fields' => $row['changed_fields'],
            'preview' => $preview,
        ];
    }

    private static function restoreRevision(array $args): array {
        $revId = (int)($args['revision_id'] ?? 0);
        if ($revId <= 0) throw new InvalidArgumentException('revision_id is required');
        $model = new PageRevision();
        $fresh = $model->restore($revId);
        return [
            'ok' => true,
            'restored_revision' => $revId,
            'page_id' => (int)($fresh['id'] ?? $revId),
            'slug' => $fresh['slug'] ?? '',
            'updated_at' => $fresh['updated_at'] ?? '',
            'note' => 'Page restored to revision ' . $revId . '. A new revision of the previous state was auto-saved, so you can undo this restore.',
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
