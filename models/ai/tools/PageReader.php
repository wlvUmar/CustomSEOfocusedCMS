<?php
// path: ./models/ai/tools/PageReader.php
// Read-only page tools: list/get/search + section reads. Split from PageTools (hygiene P1).

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/ai/tools/PageSectionsHelper.php';

class PageReader {

    public const FIELDS = [
        'content_ru', 'content_uz',
        'title_ru', 'title_uz',
        'meta_title_ru', 'meta_title_uz',
        'meta_description_ru', 'meta_description_uz',
    ];

    public static function definitions(): array {
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
                    'description' => 'Fetch one page by slug (preferred) or id, with its RU/UZ titles, content, and meta fields. content_* is the body only — hero, FAQ accordion, link widgets, header/footer auto-render outside it. `design` (first key) says how the page is styled: layout theme + what it is for, colour palette, any other custom CSS — respect it when writing. Long HTML fields are truncated to ~12000 chars with a "truncated" flag and a sections_hint (see list_sections/get_section/get_content_chunk for exact find). For targeted edits copy find exactly from truncated preview or fetch the section untruncated.',
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
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    /** Palette = the `--var: value` pairs of the page's `body.page-{slug} { ... }` block (what set_page_theme writes). */
    private static function paletteFromCss(string $css, string $slug): ?array {
        if (trim($css) === '' || $slug === '') return null;
        $cls = 'page-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($slug));
        if (!preg_match('/body\.' . preg_quote($cls, '/') . '\s*\{([^}]*)\}/', $css, $m)) return null;
        if (!preg_match_all('/(--[\w-]+)\s*:\s*([^;}]+)/', $m[1], $pairs, PREG_SET_ORDER)) return null;
        $out = [];
        foreach ($pairs as $p) $out[$p[1]] = trim($p[2]);
        return $out ?: null;
    }

    /**
     * How this page is styled, so the agent knows before it writes: layout theme (pages.theme),
     * colour palette and any other custom CSS. Compact on purpose (~60 tokens); $withRules adds the guidance once (get_page).
     */
    public static function designInfo(array $page, bool $withRules = false): array {
        $theme = PageTheme::normalize($page['theme'] ?? PageTheme::BASE);
        $css = (string)($page['custom_css'] ?? '');
        $rest = trim((string)preg_replace('/(?:\/\*\s*theme:[^*]*\*\/\s*)?body\.page-[a-z0-9-]+\s*\{[^}]*--[^}]*\}/', '', $css));
        $d = [
            'theme' => $theme,
            'theme_is' => PageTheme::aiHint($theme),
            'palette' => self::paletteFromCss($css, (string)($page['slug'] ?? '')),
            'other_custom_css' => $rest === '' ? null : ['chars' => mb_strlen($rest), 'preview' => mb_substr($rest, 0, 300)],
        ];
        if ($withRules) {
            $d['rules'] = 'Theme + palette restyle hero/cards/steps/FAQ/links/fonts automatically — write plain .c-*/content-section/info-card/process-step markup, never inline style or custom CSS to imitate them. Keep card/step text short (title <~35 chars, text <~110), ideally 4 cards/4 steps. Change theme/palette only when asked; other_custom_css can fight the theme.';
        }
        return $d;
    }

    private static function listPages(array $args): array {
        $limit = isset($args['limit']) ? max(1, min(500, (int)$args['limit'])) : 100;
        $model = new Page();
        $rows = $model->getAll(true, $limit);
        $out = [];
        foreach ($rows as $p) {
            $out[] = [
                'id' => (int)$p['id'],
                'slug' => $p['slug'],
                'title_ru' => $p['title_ru'] ?? '',
                'title_uz' => $p['title_uz'] ?? '',
                'is_published' => (int)($p['is_published'] ?? 1),
                'theme' => $p['theme'] ?? 'default',
                'updated_at' => $p['updated_at'] ?? '',
            ];
        }
        // Count total for truncated flag without full scan: if we got limit rows, assume truncated if limit < total estimate (cheap COUNT)
        $total = count($rows) === $limit ? (int)Database::getInstance()->fetchOne("SELECT COUNT(*) as c FROM pages")['c'] : count($rows);
        return ['pages' => $out, 'count' => count($out), 'truncated' => $total > $limit];
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
            throw new InvalidArgumentException('Page not found: ID ' . ($args['page_id'] ?? $args['slug'] ?? 'unknown') . ' not found. Call list_pages to discover slugs.');
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
            'is_published', 'show_link_widget', 'theme', 'updated_at',
        ];
        $result = [];
        foreach ($keep as $k) {
            if (array_key_exists($k, $page)) {
                $result[$k] = $page[$k];
            }
        }
        $design = self::designInfo($page, true);
        // Issue #6 mitigation: include lightweight sections_hint so model can jump to get_section without extra list_sections turn
        try {
            $hintLang = isset($page['content_ru']) ? 'ru' : 'uz';
            $hintHtml = (string)($page["content_{$hintLang}"] ?? '');
            if ($hintHtml !== '') {
                $secs = self::splitIntoSections($hintHtml);
                $hints = [];
                foreach ($secs as $idx => $s) {
                    $hints[] = ['index'=>$idx,'name'=>$s['name'],'chars'=>mb_strlen($s['text']),'hash'=>substr(md5($s['text']),0,8)];
                    if (count($hints) >= 12) break;
                }
                if (count($secs) > 0) $result['sections_hint'] = $hints;
                // flag truncation explicitly for model
                foreach (['content_ru','content_uz'] as $ck) {
                    if (isset($page[$ck]) && mb_strlen((string)$page[$ck]) > 12000) $result[$ck . '_truncated'] = true;
                }
            }
        } catch (Throwable $e) {}
        // design goes FIRST: tool results over 6000 chars are cut from the end, and content_* can be 12k.
        return ['design' => $design] + $result;
    }

    private static function searchContent(array $args): array {
        $query = trim((string)($args['query'] ?? ''));
        if ($query === '') {
            throw new InvalidArgumentException('query is required');
        }
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $limit = isset($args['limit']) ? max(1, min(50, (int)$args['limit'])) : 10;

        $db = Database::getInstance();
        // Robust LIKE escaping: use '!' as ESCAPE to avoid '\' quoting hell.
        // Previous ESCAPE '\\' was malformed in PHP double-quotes -> MySQL syntax error near '\') when query contained ' or \.
        // Now: ! -> !!, % -> !%, _ -> !_ . Bound via ? so ' " \ are safe via PDO.
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query);
        // Backslash is not special with ESCAPE '!', but normalize for consistency
        $like = '%' . $escaped . '%';
        $rows = $db->fetchAll(
            "SELECT id, slug, title_{$lang} AS title, content_{$lang} AS content, is_published, updated_at
             FROM pages
             WHERE (title_{$lang} LIKE ? ESCAPE '!' OR content_{$lang} LIKE ? ESCAPE '!') AND is_published = 1
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

    public static function resolveGeneralPageId(array $args): int {
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
        if (!$page) throw new InvalidArgumentException('Page not found: ID ' . $pageId . ' not found. Call list_pages to discover slugs.');
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
        return ['page_id' => $pageId, 'lang' => $lang, 'design' => self::designInfo($page), 'sections' => $out, 'count' => count($out), 'total_chars' => $totalChars, 'total_sections' => count($out)];
    }

    private static function getSection(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $sectionRef = (string)($args['section'] ?? '');
        if ($sectionRef === '') throw new InvalidArgumentException('section is required (name or index)');
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found: ID ' . $pageId . ' not found. Call list_pages to discover slugs.');
        $html = (string)($page["content_{$lang}"] ?? '');
        $sections = self::splitIntoSections($html);
        if (empty($sections)) throw new InvalidArgumentException('Field \'content_' . $lang . '\' is empty — use insert_section or set_field to create content.');
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
        if ($idx === null || !isset($sections[$idx])) throw new InvalidArgumentException('Section not found: ' . $sectionRef . ' — call list_sections for page_id ' . $pageId . ' to see available names.');
        $sec = $sections[$idx];
        return [
            'page_id' => $pageId, 'lang' => $lang, 'index' => $idx, 'name' => $sec['name'],
            'theme' => PageTheme::normalize($page['theme'] ?? PageTheme::BASE),
            'palette' => self::paletteFromCss((string)($page['custom_css'] ?? ''), (string)($page['slug'] ?? '')),
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
        if (!$page) throw new InvalidArgumentException('Page not found: ID ' . $pageId . ' not found. Call list_pages to discover slugs.');
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

    /**
     * Split an HTML field on its "<!-- Section Name -->" comment markers
     * Delegated to PageSectionsHelper (02-architecture #3) — facade kept for BC.
     */
    public static function splitIntoSections(string $html): array {
        return PageSectionsHelper::splitIntoSections($html);
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
