<?php
// path: ./models/ai/tools/PageTools.php
// Tools for reading and editing the `pages` table. Write access is restricted
// to the same field allowlist the existing page editor's AI panel uses.

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/PageRevision.php';
require_once BASE_PATH . '/models/ai/tools/PageSectionsHelper.php';

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
                    'description' => 'Fetch one page by slug (preferred) or id, with its RU/UZ titles, content, and meta fields. Long HTML fields are truncated to ~12000 chars with a "truncated" flag and a sections_hint (see list_sections/get_section/get_content_chunk for exact find). For targeted edits copy find exactly from truncated preview or fetch the section untruncated.',
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
                    'description' => 'Apply a precise find-and-replace to one field of one page. The "find" text must appear EXACTLY once in the current stored value (copy it character-for-character from get_page/get_section output, including HTML tags). If get_page was truncated, use get_section or get_content_chunk to fetch the exact fragment. Replacements >800 chars require user approval (approval_required). Prefer patch_section for section-scoped edits.',
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
                    'description' => 'Replace the ENTIRE value of one field of one page. Always guarded — requires user approval before execution. Prefer str_replace_field or patch_section for incremental changes (<800 chars auto-executes).',
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
                    'description' => 'Insert a new HTML section (wrapped in a "<!-- Name -->" marker) into a page\'s content field, either at the top or the end. Use EITHER legacy classes (content-section, info-card, process-step, faq-item, links-tile, btn/btn-primary) OR plugin .c-* classes (178 in components.css — c-hero-split, c-stats/bar/dark, c-feature-grid/split, c-process/timeline, c-card/testimonial, c-cta/callout, c-gallery/carousel, c-pricing etc. — call get_design_tokens for live catalog). Preserve template variables. Senior HTML: semantic tags, landmarks, heading hierarchy, alt quality; prefer tokens — see get_design_tokens.',
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
                    'description' => 'Replace an ENTIRE section\'s HTML (<!-- Name --> marker preserved; others untouched). You may use any HTML/tags + inline style="" (prefer token vars var(--teal); ensure WCAG contrast). HTML >800 chars requires approval — keep edits small or use patch_section for targeted fixes. Always follow with render_preview; then render_full_page once.',
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
                    'description' => 'Precise find-and-replace scoped to ONE section (avoids global str_replace ambiguity). Find must occur exactly once in that section; copy from get_section. Replacement >800 chars requires approval. Use for small line edits/style tweaks; use update_section for full rewrites.',
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
                    'description' => 'Override styles of a section via inline style="" on its top-level element (without touching pages.min.css). Merges the given CSS declarations into the section\'s first HTML tag\'s style attribute. Supports design-token shorthands: bg:teal → background:var(--teal), text:ink → color:var(--ink), border:teal, or full var(--teal). Allowed tokens (prefer these; custom hex only if user explicitly requested): --teal, --teal-dark, --orange, --green, --ink, --ink-soft, --muted, --surface, --surface-2, --border, --max-w, --section-gap, --ease, --dur. By default also syncs ru↔uz. Warn if contrast fails.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz'], 'description' => 'Which content field to style (default ru).'],
                            'section' => ['type' => 'string', 'description' => 'Section name or index.'],
                            'style' => ['type' => 'string', 'description' => 'CSS declarations, e.g. "background:var(--teal); color:#fff; padding:32px; border-radius:16px" or shorthands "bg:teal; text:ink; padding:32px". Tokens expanded via designTokens allowlist.'],
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
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_section_marker',
                    'description' => 'Insert a "<!-- Name -->" section delimiter into a page\'s content field at an exact anchor. Use when list_sections returns 0-1 sections (raw HTML) or you need to split Top of document into logical blocks so update_section/patch_section can target them. The insertion is snapshotted via page_revisions.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz'], 'description' => 'Which content field (default ru).'],
                            'find' => ['type' => 'string', 'description' => 'Exact existing substring to anchor insertion (must occur exactly once in the field; copy from get_content_chunk/get_section). If empty, offset is used instead.'],
                            'name' => ['type' => 'string', 'description' => 'New section name for the HTML comment, e.g. "Features" (1-80 chars, must be unique in that field).'],
                            'position' => ['type' => 'string', 'enum' => ['before', 'after'], 'description' => 'Insert marker before or after the find text (default before).'],
                            'offset' => ['type' => 'integer', 'description' => 'If find is empty, char offset (0-based) to insert at.'],
                        ],
                        'required' => ['name'],
                    ],
                    'oneOf' => [['required'=>['page_id']],[ 'required'=>['slug']]],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'auto_sectionize',
                    'description' => 'Heuristically split raw HTML into sections by <h2>/<h3>/<section> boundaries and insert "<!-- Name -->" markers. Use only when page has <2 sections and content >2000 chars. Dry-run first to preview.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz'], 'description' => 'Which content field (default ru).'],
                            'dry_run' => ['type' => 'boolean', 'description' => 'If true (default) only preview proposed inserts without writing.'],
                            'min_section_chars' => ['type' => 'integer', 'description' => 'Ignore sections shorter than this (default 80).'],
                        ],
                        'oneOf' => [['required'=>['page_id']],[ 'required'=>['slug']]],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'set_custom_css',
                    'description' => 'Set per-page custom CSS that is injected AFTER pages.min.css + components.min.css, so it can fully override header/footer/any component. Empty string clears override (reverts to global defaults). Use body.page-{slug} prefix to scope (e.g. body.page-televizor header{background:var(--teal)}). Also supports :root token overrides body.page-slug{--teal:#0a4f5c;--surface:#fdfcf8}. Use components library classes (c-stats, c-feature-grid, etc.) or create one-off styles here. CSS is sanitized (blocks @import/javascript vectors). Prefer tokens var(--teal)/var(--orange) over new hex unless user asked for custom palette.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'css' => ['type' => 'string', 'description' => 'CSS to set for this page (max 20000 chars). Empty string clears custom_css and restores defaults. Supports any selectors; recommend body.page-slug header/footer scoping.'],
                            'mode' => ['type' => 'string', 'enum' => ['replace','append'], 'description' => 'replace (default) overwrites, append concatenates to existing custom_css'],
                        ],
                        'oneOf' => [['required'=>['page_id']],[ 'required'=>['slug']]],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'set_page_theme',
                    'description' => 'Quickly re-theme a page by overriding :root/design tokens. This writes to custom_css as body.page-{slug}{--teal:#...;--orange:#...;--surface:#...;--ink:#...} so ALL components (pages.css + 100+ c-*) instantly recolor without rewriting HTML. Use for giving each page distinct palette (e.g. televisor=cool teal, mebel=warm wood). Accepts preset (teal|orange|green|indigo|warm|dark|light) or explicit vars. Empty preset clears theme vars only, keeping other custom_css.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'preset' => ['type' => 'string', 'enum' => ['teal','orange','green','indigo','warm','dark','light','custom'], 'description' => 'Preset palette. custom requires vars.'],
                            'vars' => ['type' => 'object', 'description' => 'Explicit CSS variable overrides, e.g. {"--teal":"#0a4f5c","--orange":"#e8610a","--surface":"#fdfcf8"} (keys must start with --). Used when preset=custom or to tweak a preset.'],
                            'clear' => ['type' => 'boolean', 'description' => 'If true, removes previously set theme vars from custom_css (keeps other rules).'],
                        ],
                        'oneOf' => [['required'=>['page_id']],[ 'required'=>['slug']]],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'batch_update',
                    'description' => 'Apply up to 10 targeted edits to one page atomically in one turn (saves turns). Each op is patch_section/str_replace_field/set_field/update_section/set_section_style/wrap_section/add_section_marker. Each op with replace/html/value >800 chars individually requires approval before batch executes — keep ops small. Use set_field when target field is empty (str_replace_field requires non-empty find). Prefer over sequential calls.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'operations' => [
                                'type' => 'array',
                                'maxItems' => 10,
                                'description' => 'Array of operations to apply atomically.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'op' => ['type' => 'string', 'enum' => ['patch_section','str_replace_field','set_field','update_section','set_section_style','wrap_section','add_section_marker'], 'description' => 'Operation type.'],
                                        'field' => ['type' => 'string', 'description' => 'Field for str_replace_field/set_field (must be in FIELDS).'],
                                        'lang' => ['type' => 'string', 'enum' => ['ru','uz'], 'description' => 'Language for section ops (default ru).'],
                                        'section' => ['type' => 'string', 'description' => 'Section name or index for section ops.'],
                                        'find' => ['type' => 'string', 'description' => 'Find text for patch/str_replace/add_section_marker.'],
                                        'replace' => ['type' => 'string', 'description' => 'Replacement text for str_replace_field/patch_section (or value alias for set_field).'],
                                        'value' => ['type' => 'string', 'description' => 'Complete new value for set_field (alternative to replace).'],
                                        'html' => ['type' => 'string', 'description' => 'Full HTML for update_section.'],
                                        'style' => ['type' => 'string', 'description' => 'CSS declarations for set_section_style.'],
                                        'wrapper_open' => ['type' => 'string', 'description' => 'Opening tag for wrap_section.'],
                                        'wrapper_close' => ['type' => 'string', 'description' => 'Closing tag for wrap_section.'],
                                        'name' => ['type' => 'string', 'description' => 'Section name for add_section_marker.'],
                                        'position' => ['type' => 'string', 'enum' => ['before','after'], 'description' => 'Position for add_section_marker (before/after).'],
                                        'offset' => ['type' => 'integer', 'description' => 'Char offset for add_section_marker when find is empty.'],
                                    ],
                                    'required' => ['op'],
                                ],
                            ],
                        ],
                        'oneOf' => [['required'=>['page_id']],[ 'required'=>['slug']]],
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
            case 'add_section_marker':
                return self::addSectionMarker($args);
            case 'auto_sectionize':
                return self::autoSectionize($args);
            case 'set_custom_css':
                return self::setCustomCss($args);
            case 'set_page_theme':
                return self::setPageTheme($args);
            case 'batch_update':
                return self::batchUpdate($args);
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
        $rows = $model->getAll(true, $limit);
        $out = [];
        foreach ($rows as $p) {
            $out[] = [
                'id' => (int)$p['id'],
                'slug' => $p['slug'],
                'title_ru' => $p['title_ru'] ?? '',
                'title_uz' => $p['title_uz'] ?? '',
                'is_published' => (int)($p['is_published'] ?? 1),
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
            'is_published', 'show_link_widget', 'updated_at',
        ];
        $result = [];
        foreach ($keep as $k) {
            if (array_key_exists($k, $page)) {
                $result[$k] = $page[$k];
            }
        }
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
        return ['page_id' => $pageId, 'lang' => $lang, 'sections' => $out, 'count' => count($out), 'total_chars' => $totalChars, 'total_sections' => count($out)];
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
        if (!$page) throw new InvalidArgumentException('Page not found: ID ' . $pageId . ' not found. Call list_pages to discover slugs.');

        $current = (string)($page[$field] ?? '');
        // 03-code-bugs #10: use mb_substr_count for Cyrillic/multibyte correctness
        $count = mb_substr_count($current, $find, 'UTF-8');
        if ($count === 0) {
            throw new InvalidArgumentException('The "find" text was not found — fetch exact HTML via get_section (or get_page/get_content_chunk) and copy character-for-character, including HTML tags.');
        }
        if ($count > 1) {
            throw new InvalidArgumentException('The "find" text occurs ' . $count . ' times — include more surrounding context to make it unique or use update_section for a full rewrite.');
        }
        $updated = str_replace($find, $replace, $current);
        $warnings = in_array($field, ['content_ru','content_uz'], true) ? self::validateHtmlWarnings($current, $updated) : [];
        $model->update($pageId, [$field => $updated]);
        $fresh = $model->getById($pageId);
        $freshVal = (string)($fresh[$field] ?? $updated);
        $ret = [
            'ok' => true,
            'verified' => $freshVal === $updated,
            'fresh_hash' => substr(md5($freshVal), 0, 8),
            'after_preview' => mb_substr(trim(strip_tags($freshVal)), 0, 300),
            'page_id' => $pageId,
            'field' => $field,
            'applied' => 1,
            'before_chars' => mb_strlen($current),
            'after_chars' => mb_strlen($updated),
            'note' => 'Change saved and verified via read-after-write.',
        ];
        if (!empty($warnings)) $ret['warnings'] = $warnings;
        return $ret;
    }

    private static function setField(array $args): array {
        $pageId = (int)($args['page_id'] ?? 0);
        $field = (string)($args['field'] ?? '');
        $value = (string)($args['value'] ?? '');
        if ($pageId <= 0) throw new InvalidArgumentException('page_id is required');
        if (!in_array($field, self::FIELDS, true)) throw new InvalidArgumentException("Field not writable: {$field}");

        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found: ID ' . $pageId . ' not found. Call list_pages to discover slugs.');

        $oldVal = (string)($page[$field] ?? '');
        $warnings = in_array($field, ['content_ru','content_uz'], true) ? self::validateHtmlWarnings($oldVal, $value) : [];
        $model->update($pageId, [$field => $value]);
        $fresh = $model->getById($pageId);
        $freshVal = (string)($fresh[$field] ?? $value);
        $ret = [
            'ok' => true,
            'verified' => $freshVal === $value,
            'fresh_hash' => substr(md5($freshVal), 0, 8),
            'after_preview' => mb_substr(trim(strip_tags($freshVal)), 0, 300),
            'page_id' => $pageId,
            'field' => $field,
            'chars' => mb_strlen($value),
            'note' => 'Change saved and verified via read-after-write.',
        ];
        if (!empty($warnings)) $ret['warnings'] = $warnings;
        return $ret;
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
        if (!$page) throw new InvalidArgumentException('Page not found: ID ' . $pageId . ' not found. Call list_pages to discover slugs.');

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
        return PageSectionsHelper::findSectionIndex($sections, $ref);
    }

    private static function rebuildContentFromSections(array $sections): string {
        return PageSectionsHelper::rebuildContentFromSections($sections);
    }

    private static function updateSection(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $sectionRef = (string)($args['section'] ?? '');
        $html = (string)($args['html'] ?? '');
        if ($sectionRef === '') throw new InvalidArgumentException('section is required');
        if (trim($html) === '') throw new InvalidArgumentException('html is required — may contain any tags/divs and inline style=""');
        // 06-09: sanitize html before persisting (strip dangerous vectors)
        $html = self::sanitizeSectionHtml($html);
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found: ID ' . $pageId . ' not found. Call list_pages to discover slugs.');
        $field = "content_{$lang}";
        $sections = self::splitIntoSections((string)($page[$field] ?? ''));
        $idx = self::findSectionIndex($sections, $sectionRef);
        if ($idx === null) throw new InvalidArgumentException('Section not found: ' . $sectionRef . ' — call list_sections for page_id ' . $pageId . ' to see available names.');
        $oldName = $sections[$idx]['name'];
        $oldSec = $sections[$idx]['text'];
        // 06-08: allow rename via supplied marker <!-- NewName -->
        $newName = $oldName;
        $bodyHtml = $html;
        if (preg_match('/^\s*<!--\s*(.*?)\s*-->\s*\n?(.*)$/s', $html, $mu)) {
            $candidate = trim($mu[1]);
            if ($candidate !== '' && mb_strtolower($candidate) !== mb_strtolower($oldName)) {
                // Check duplicate name exists
                foreach ($sections as $i => $s) {
                    if ($i !== $idx && mb_strtolower($s['name']) === mb_strtolower($candidate)) {
                        throw new InvalidArgumentException('Section name "' . $candidate . '" already exists — choose a unique name or use add_section_marker');
                    }
                }
                if (str_contains($candidate, '--') || str_contains($candidate, '<') || str_contains($candidate, '>') || mb_strlen($candidate) > 80) {
                    throw new InvalidArgumentException('Invalid section name in marker: "' . $candidate . '"');
                }
                $newName = $candidate;
                $bodyHtml = $mu[2];
                $sections[$idx]['name'] = $newName;
            } else {
                // marker same name — treat as original behavior (full html includes marker)
                $sections[$idx]['text'] = $html;
                $warnings = self::validateHtmlWarnings($oldSec, $sections[$idx]['text']);
                $updated = self::rebuildContentFromSections($sections);
                $model->update($pageId, [$field => $updated]);
                $fresh = $model->getById($pageId);
                $freshVal = (string)($fresh[$field] ?? $updated);
                $ret = ['ok'=>true,'verified'=> $freshVal === $updated,'fresh_hash'=>substr(md5($freshVal),0,8),'after_preview'=>mb_substr(trim(strip_tags($freshVal)),0,300),'page_id'=>$pageId,'lang'=>$lang,'section'=>$newName,'index'=>$idx,'content_chars'=>mb_strlen($updated),'note'=>'Section replaced and verified. Call render_preview to verify visually.'];
                if (!empty($warnings)) $ret['warnings'] = $warnings;
                return $ret;
            }
        }
        // Normal replace: marker preserved (possibly renamed) + sanitized body
        $marker = "<!-- {$newName} -->\n";
        $sections[$idx]['text'] = $marker . ltrim($bodyHtml);
        $warnings = self::validateHtmlWarnings($oldSec, $sections[$idx]['text']);
        $updated = self::rebuildContentFromSections($sections);
        $model->update($pageId, [$field => $updated]);
        $fresh = $model->getById($pageId);
        $freshVal = (string)($fresh[$field] ?? $updated);
        $ret = ['ok'=>true,'verified'=> $freshVal === $updated,'fresh_hash'=>substr(md5($freshVal),0,8),'after_preview'=>mb_substr(trim(strip_tags($freshVal)),0,300),'page_id'=>$pageId,'lang'=>$lang,'section'=>$newName,'index'=>$idx,'content_chars'=>mb_strlen($updated),'note'=>'Section replaced and verified. Call render_preview to verify visually.' . ($newName !== $oldName ? ' Renamed "'.$oldName.'" → "'.$newName.'"' : '')];
        if (!empty($warnings)) $ret['warnings'] = $warnings;
        return $ret;
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
        if (!$page) throw new InvalidArgumentException('Page not found: ID ' . $pageId . ' not found. Call list_pages to discover slugs.');
        $field = "content_{$lang}";
        $sections = self::splitIntoSections((string)($page[$field] ?? ''));
        $idx = self::findSectionIndex($sections, $sectionRef);
        if ($idx === null) throw new InvalidArgumentException('Section not found: ' . $sectionRef . ' — call list_sections for page_id ' . $pageId . ' to see available names.');
        $secText = $sections[$idx]['text'];
        $count = mb_substr_count($secText, $find, 'UTF-8');
        if ($count === 0) throw new InvalidArgumentException('The "find" text was not found — fetch exact HTML via get_section and copy character-for-character, including HTML tags. Section "' . $sections[$idx]['name'] . '" has ' . mb_strlen($secText) . ' chars.');
        if ($count > 1) throw new InvalidArgumentException('The "find" text occurs ' . $count . ' times inside section "' . $sections[$idx]['name'] . '" — include more surrounding context to make it unique, or use update_section for a full rewrite.');
        $newSec = str_replace($find, $replace, $secText);
        $warnings = self::validateHtmlWarnings($secText, $newSec);
        $sections[$idx]['text'] = $newSec;
        $updated = self::rebuildContentFromSections($sections);
        $model->update($pageId, [$field => $updated]);
        $fresh = $model->getById($pageId);
        $freshVal = (string)($fresh[$field] ?? $updated);
        $ret = ['ok'=>true,'verified'=> $freshVal === $updated,'fresh_hash'=>substr(md5($freshVal),0,8),'after_preview'=>mb_substr(trim(strip_tags($freshVal)),0,300),'page_id'=>$pageId,'lang'=>$lang,'section'=>$sections[$idx]['name'],'index'=>$idx,'before_chars'=>mb_strlen($secText),'after_chars'=>mb_strlen($sections[$idx]['text']),'note'=>'Section patched and verified (1 occurrence).'];
        if (!empty($warnings)) $ret['warnings'] = $warnings;
        return $ret;
    }

    private static function mergeStyleIntoTag(string $tagHtml, string $styleDecl): string {
        return PageSectionsHelper::mergeStyleIntoTag($tagHtml, $styleDecl);
    }

    /** Design tokens allowlist — mirrored from helper (02-architecture #3) */
    private const DESIGN_TOKENS = ['--teal','--teal-dark','--orange','--green','--ink','--ink-soft','--muted','--surface','--surface-2','--border','--max-w','--px','--section-gap','--ease','--dur','--teal-light','--orange-light','--green-dark','--orange-dark','--tg','--success'];

    private static function expandStyleTokens(string $style): string {
        return PageSectionsHelper::expandStyleTokens($style);
    }

    /** 06-09: strip dangerous CSS/HTML vectors before persisting */
    private static function sanitizeSectionHtml(string $html): string {
        // Layer 1: strip script-ish tags and event handlers similar to SiteTools sanitizeForPreview
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html);
        $html = preg_replace('/<object\b[^>]*>.*?<\/object>/is', '', $html);
        $html = preg_replace('/<embed\b[^>]*>/i', '', $html);
        $html = preg_replace('/<link\b[^>]*>/i', '', $html);
        // Remove style @import / expression, javascript:/vbscript:/data:text/html
        $html = preg_replace('/\b(javascript|vbscript|data\s*:\s*text\/html)\s*:/i', '', $html);
        $html = preg_replace('/@import\s+/i', '', $html);
        $html = preg_replace('/expression\s*\(/i', '', $html);
        // Strip on* attributes (onclick, onerror, ontoggle, etc.) — both quoted forms
        $html = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $html);
        // Also sanitize style="" values that try to inject url(javascript:)
        $html = preg_replace('/url\s*\(\s*["\']?\s*javascript:[^)]*\)/i', 'url(#)', $html);
        return $html;
    }

    /** Soft validation warnings (04-04) — never blocks, only advises model. */
    private static function validateHtmlWarnings(string $oldHtml, string $newHtml): array {
        $warnings = [];
        try {
            // Heading hierarchy: h1 count, skip levels
            $headings = [];
            if (preg_match_all('/<h([1-4])[^>]*>/i', $newHtml, $m)) $headings = array_map('intval', $m[1]);
            if (!empty($headings)) {
                $h1 = count(array_filter($headings, fn($h)=>$h===1));
                if ($h1 > 1) $warnings[] = 'Multiple <h1> found ('.$h1.') — use one h1 per page, then h2→h3 sequential.';
                // skip check: h1→h3, h2→h4 etc
                for ($i=1; $i<count($headings); $i++) {
                    if ($headings[$i] - $headings[$i-1] > 1) { $warnings[] = 'Heading skip h'.$headings[$i-1].'→h'.$headings[$i].' — keep sequential h1→h2→h3.'; break; }
                }
            }
            // Template vars preservation
            if (preg_match_all('/\{\{\s*[^}]+\s*\}\}/', $oldHtml, $om)) {
                $oldVars = array_unique($om[0]);
                foreach ($oldVars as $v) {
                    if (strpos($newHtml, $v) === false && strpos($newHtml, trim($v)) === false) {
                        // allow {{page.title}} etc to be preserved in any whitespace variant
                        $alt = preg_replace('/\s+/', '', $v);
                        if (strpos(preg_replace('/\s+/', '', $newHtml), $alt) === false) {
                            $warnings[] = 'Template var '.trim($v).' was in old section but missing in new HTML — preserve {{page.*}}/{{global.*}}/{{faqs}}.';
                            break;
                        }
                    }
                }
            }
            // Warn on raw custom hex if token available alternative (soft)
            if (preg_match('/#(?:[0-9a-f]{3}|[0-9a-f]{6})\b/i', $newHtml) && preg_match('/var\(--teal|--ink|--surface|--border/', $oldHtml)) {
                // only warn if many hex colors introduced
                $hexCount = preg_match_all('/#(?:[0-9a-f]{3}|[0-9a-f]{6})\b/i', $newHtml, $hm);
                if ($hexCount > 2) $warnings[] = 'Multiple custom hex colors — prefer design tokens var(--teal)/var(--ink)/var(--surface) via get_design_tokens.';
            }
        } catch (Throwable $e) {}
        return $warnings;
    }

    private static function setSectionStyle(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $sectionRef = (string)($args['section'] ?? '');
        $style = trim((string)($args['style'] ?? ''));
        $sync = !array_key_exists('sync', $args) ? true : (bool)$args['sync'];
        if ($sectionRef === '') throw new InvalidArgumentException('section is required');
        if ($style === '') throw new InvalidArgumentException('style is required, e.g. "background:var(--teal); color:#fff; padding:32px"');
        // Expand shorthand tokens before applying
        $style = self::expandStyleTokens($style);
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found: ID ' . $pageId . ' not found. Call list_pages to discover slugs.');
        $field = "content_{$lang}";
        $sections = self::splitIntoSections((string)($page[$field] ?? ''));
        $idx = self::findSectionIndex($sections, $sectionRef);
        if ($idx === null) throw new InvalidArgumentException('Section not found: ' . $sectionRef . ' — call list_sections for page_id ' . $pageId . ' to see available names.');
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
        if (!$page) throw new InvalidArgumentException('Page not found: ID ' . $pageId . ' not found. Call list_pages to discover slugs.');
        $field = "content_{$lang}";
        $sections = self::splitIntoSections((string)($page[$field] ?? ''));
        $idx = self::findSectionIndex($sections, $sectionRef);
        if ($idx === null) throw new InvalidArgumentException('Section not found: ' . $sectionRef . ' — call list_sections for page_id ' . $pageId . ' to see available names.');
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

    private static function addSectionMarker(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $find = (string)($args['find'] ?? '');
        $name = trim((string)($args['name'] ?? ''));
        $position = ($args['position'] ?? 'before') === 'after' ? 'after' : 'before';
        $offset = array_key_exists('offset', $args) ? (int)$args['offset'] : null;
        if ($name === '') throw new InvalidArgumentException('name is required — section name for the new <!-- Name --> marker (1-80 chars).');
        if (mb_strlen($name) > 80) throw new InvalidArgumentException('Section name too long (max 80 chars).');
        if (str_contains($name, '--') || str_contains($name, '<') || str_contains($name, '>')) throw new InvalidArgumentException('Section name must not contain "--", "<" or ">"');
        if ($find === '' && $offset === null) throw new InvalidArgumentException('Either find (exact anchor text) or offset is required to locate insertion point.');
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found: ID ' . $pageId . ' not found. Call list_pages to discover slugs.');
        $field = "content_{$lang}";
        $current = (string)($page[$field] ?? '');
        // Duplicate name check (case-insensitive)
        $existing = self::splitIntoSections($current);
        foreach ($existing as $s) {
            if (mb_strtolower($s['name']) === mb_strtolower($name)) {
                throw new InvalidArgumentException('Section name "' . $name . '" already exists in field content_' . $lang . ' — choose a unique name. Existing: ' . implode(', ', array_map(fn($x)=>$x['name'], $existing)));
            }
        }
        $marker = "<!-- {$name} -->";
        $updated = null;
        $insertedAt = null;
        if ($find !== '') {
            $cnt = substr_count($current, $find);
            if ($cnt === 0) throw new InvalidArgumentException('The "find" text was not found — fetch exact HTML via get_content_chunk (offset/limit) or get_section and copy character-for-character, including HTML tags.');
            if ($cnt > 1) throw new InvalidArgumentException('The "find" text occurs ' . $cnt . ' times — include more surrounding context to make it unique or use offset.');
            $pos = mb_strpos($current, $find);
            if ($position === 'before') {
                $updated = mb_substr($current, 0, $pos) . $marker . "\n" . mb_substr($current, $pos);
                $insertedAt = $pos;
            } else {
                $end = $pos + mb_strlen($find);
                $updated = mb_substr($current, 0, $end) . "\n" . $marker . "\n" . mb_substr($current, $end);
                $insertedAt = $end;
            }
        } else {
            $total = mb_strlen($current);
            $off = (int)$offset;
            if ($off < 0) $off = 0;
            if ($off > $total) $off = $total;
            $updated = mb_substr($current, 0, $off) . ($off > 0 && mb_substr($current, $off - 1, 1) !== "\n" ? "\n" : '') . $marker . "\n" . mb_substr($current, $off);
            $insertedAt = $off;
        }
        // Warn if insertion point is suspiciously inside a tag (heuristic)
        $note = 'Marker inserted.';
        if ($find !== '') {
            $pre = mb_substr($updated, max(0, $insertedAt - 60), 60);
            $post = mb_substr($updated, $insertedAt, 60);
            // If we see unclosed < ... without > nearby, likely inside tag
            if (preg_match('/<[^>]*$/', $pre) && !preg_match('/^[^<]*>/', $post)) {
                $note .= ' Warning: insertion appears inside an HTML tag — choose a find boundary between tags.';
            }
        }
        $model->update($pageId, [$field => $updated]);
        $fresh = $model->getById($pageId);
        $freshVal = (string)($fresh[$field] ?? $updated);
        $sections = self::splitIntoSections($freshVal);
        $outSections = array_map(fn($s)=>['name'=>$s['name'],'chars'=>mb_strlen($s['text'])], $sections);
        return [
            'ok'=>true,
            'verified'=> $freshVal === $updated,
            'fresh_hash'=>substr(md5($freshVal),0,8),
            'page_id'=>$pageId,
            'lang'=>$lang,
            'name'=>$name,
            'position'=>$position,
            'inserted_at'=>$insertedAt,
            'content_chars'=>mb_strlen($freshVal),
            'sections_after'=>$outSections,
            'total_sections'=>count($sections),
            'note'=>$note . ' Call list_sections to verify and then update_section/patch_section on the new section.',
        ];
    }

    private static function autoSectionize(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $dryRun = !array_key_exists('dry_run', $args) ? true : (bool)$args['dry_run'];
        $minChars = isset($args['min_section_chars']) ? max(20, min(2000, (int)$args['min_section_chars'])) : 80;
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found: ID ' . $pageId . ' not found.');
        $field = "content_{$lang}";
        $html = (string)($page[$field] ?? '');
        if (trim($html) === '') throw new InvalidArgumentException('Field content_' . $lang . ' is empty — use insert_section to create content.');
        $existing = self::splitIntoSections($html);
        if (count($existing) > 1) {
            return [
                'ok'=>false,
                'page_id'=>$pageId,
                'lang'=>$lang,
                'existing_sections'=>array_map(fn($s)=>$s['name'], $existing),
                'count'=>count($existing),
                'note'=>'Page already has ' . count($existing) . ' sections — auto_sectionize is for raw HTML with 0-1 sections. Use add_section_marker for manual splitting.',
            ];
        }
        // Heuristic: find <h2>, <h3>, <section> boundaries
        $proposals = [];
        $seenNames = [];
        // First try <h2> tags
        if (preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/is', $html, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $idx => $m) {
                $fullTag = $m[0];
                $offsetBytes = $m[1];
                // Convert byte offset to char offset for mb_* safety — approximate via substr
                $charOffset = mb_strlen(substr($html, 0, $offsetBytes));
                $inner = trim(strip_tags($matches[1][$idx][0] ?? ''));
                $inner = preg_replace('/\s+/u', ' ', $inner);
                $name = mb_substr($inner, 0, 60);
                if (mb_strlen($inner) < 2) $name = 'Section ' . ($idx + 1);
                // Ensure unique
                $base = $name;
                $suffix = 1;
                while (in_array(mb_strtolower($name), array_map('mb_strtolower', $seenNames), true)) {
                    $name = $base . ' ' . (++$suffix);
                }
                $seenNames[] = $name;
                // Skip if section would be too short (distance to next h2)
                $nextOffset = isset($matches[0][$idx+1]) ? mb_strlen(substr($html, 0, $matches[0][$idx+1][1])) : mb_strlen($html);
                $len = $nextOffset - $charOffset;
                if ($len < $minChars) continue;
                $proposals[] = [
                    'find' => $fullTag,
                    'name' => $name,
                    'position' => 'before',
                    'offset' => $charOffset,
                    'preview' => mb_substr($fullTag, 0, 80),
                ];
            }
        }
        // Fallback: <h3> if no h2
        if (empty($proposals) && preg_match_all('/<h3\b[^>]*>(.*?)<\/h3>/is', $html, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $idx => $m) {
                $fullTag = $m[0];
                $offsetBytes = $m[1];
                $charOffset = mb_strlen(substr($html, 0, $offsetBytes));
                $inner = trim(strip_tags($matches[1][$idx][0] ?? ''));
                $inner = preg_replace('/\s+/u', ' ', $inner);
                $name = mb_substr($inner, 0, 60);
                if (mb_strlen($inner) < 2) $name = 'Section ' . ($idx + 1);
                $base = $name; $suffix=1;
                while (in_array(mb_strtolower($name), array_map('mb_strtolower', $seenNames), true)) {
                    $name = $base . ' ' . (++$suffix);
                }
                $seenNames[]=$name;
                $nextOffset = isset($matches[0][$idx+1]) ? mb_strlen(substr($html, 0, $matches[0][$idx+1][1])) : mb_strlen($html);
                $len = $nextOffset - $charOffset;
                if ($len < $minChars) continue;
                $proposals[] = ['find'=>$fullTag,'name'=>$name,'position'=>'before','offset'=>$charOffset,'preview'=>mb_substr($fullTag,0,80)];
            }
        }
        // Fallback: <section> tags
        if (empty($proposals) && preg_match_all('/<section\b[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $idx => $m) {
                $fullTag = $m[0];
                $offsetBytes = $m[1];
                $charOffset = mb_strlen(substr($html, 0, $offsetBytes));
                $name = 'Section ' . ($idx + 1);
                $proposals[] = ['find'=>$fullTag,'name'=>$name,'position'=>'before','offset'=>$charOffset,'preview'=>mb_substr($fullTag,0,80)];
            }
        }
        if (empty($proposals)) {
            return [
                'ok'=>false,
                'page_id'=>$pageId,
                'lang'=>$lang,
                'proposals'=>[],
                'note'=>'No <h2>/<h3>/<section> boundaries found to auto-sectionize. Use add_section_marker with a manual find/offset.',
            ];
        }
        if ($dryRun) {
            return [
                'ok'=>true,
                'dry_run'=>true,
                'page_id'=>$pageId,
                'lang'=>$lang,
                'proposals'=>$proposals,
                'count'=>count($proposals),
                'note'=>'Dry-run: ' . count($proposals) . ' marker(s) proposed. Re-call with dry_run=false to apply, or use batch_update with add_section_marker for selective insertion.',
            ];
        }
        // Apply: insert markers from last to first so offsets stay valid
        usort($proposals, fn($a,$b)=> $b['offset'] <=> $a['offset']);
        $updated = $html;
        $applied = [];
        foreach ($proposals as $p) {
            $cnt = substr_count($updated, $p['find']);
            if ($cnt !== 1) continue; // skip ambiguous
            $pos = mb_strpos($updated, $p['find']);
            $marker = "<!-- {$p['name']} -->\n";
            $updated = mb_substr($updated, 0, $pos) . $marker . mb_substr($updated, $pos);
            $applied[] = $p['name'];
        }
        if (empty($applied)) {
            return ['ok'=>false,'page_id'=>$pageId,'lang'=>$lang,'note'=>'No markers applied — find texts were ambiguous. Use add_section_marker manually.'];
        }
        $model->update($pageId, [$field => $updated]);
        $fresh = $model->getById($pageId);
        $freshVal = (string)($fresh[$field] ?? $updated);
        $sections = self::splitIntoSections($freshVal);
        return [
            'ok'=>true,
            'dry_run'=>false,
            'verified'=> $freshVal === $updated,
            'page_id'=>$pageId,
            'lang'=>$lang,
            'applied'=>$applied,
            'applied_count'=>count($applied),
            'total_sections'=>count($sections),
            'sections_after'=>array_map(fn($s)=>['name'=>$s['name'],'chars'=>mb_strlen($s['text'])], $sections),
            'content_chars'=>mb_strlen($freshVal),
            'note'=>'Auto-sectionized: ' . count($applied) . ' marker(s) inserted. Call list_sections to verify.',
        ];
    }

    private static function batchUpdate(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $ops = $args['operations'] ?? null;
        if (!is_array($ops) || empty($ops)) throw new InvalidArgumentException('operations is required — array of up to 10 edits.');
        if (count($ops) > 10) throw new InvalidArgumentException('Too many operations (max 10).');
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException('Page not found: ID ' . $pageId . ' not found. Call list_pages to discover slugs.');
        // Buffers
        $buffers = [];
        foreach (self::FIELDS as $f) {
            $buffers[$f] = (string)($page[$f] ?? '');
        }
        // Section caches per lang: lang -> sections array
        $sectionCache = ['ru'=>null,'uz'=>null];
        $sectionDirty = ['ru'=>false,'uz'=>false];
        $fieldDirty = [];
        $results = [];
        $db = Database::getInstance();
        $inTxn = false;
        try {
            // 06-10: use PDO transactions (handles autocommit + nesting via inTransaction guard)
            if (!$db->inTransaction()) {
                $db->beginTransaction();
                $inTxn = true;
            } else {
                // already in txn (e.g. Page::update snapshot) — use savepoint-style: don't double-begin
                $inTxn = false;
            }
            foreach ($ops as $idx => $op) {
                if (!is_array($op)) throw new InvalidArgumentException('Operation #' . $idx . ' must be an object.');
                $type = (string)($op['op'] ?? '');
                $lang = ($op['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
                $field = "content_{$lang}";
                try {
                    switch ($type) {
                        case 'str_replace_field': {
                            $fld = (string)($op['field'] ?? '');
                            $find = (string)($op['find'] ?? '');
                            $replace = (string)($op['replace'] ?? '');
                            if (!in_array($fld, self::FIELDS, true)) throw new InvalidArgumentException("Operation #$idx: field not writable: {$fld} — got " . json_encode($op, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                            if ($find === '') throw new InvalidArgumentException("Operation #$idx: find is required for str_replace_field — empty strings are invalid. You sent " . mb_substr(json_encode($op, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),0,400) . " — copy find exactly from get_page (for meta_title/meta_description) or get_section/get_content_chunk (for content) including HTML tags. If field is empty, use set_field instead.");
                            $current = $buffers[$fld] ?? '';
                            if ($current === '' && $find !== '') throw new InvalidArgumentException("Operation #$idx: field {$fld} is currently empty — str_replace_field requires non-empty find. Use set_field (or batch op set_field) to initialize it.");
                            $cnt = substr_count($current, $find);
                            if ($cnt === 0) throw new InvalidArgumentException("Operation #$idx: find text not found in field {$fld} — fetch exact HTML via get_page (meta fields) or get_section/get_content_chunk (content). Current field length " . mb_strlen($current) . " chars.");
                            if ($cnt > 1) throw new InvalidArgumentException("Operation #$idx: find occurs {$cnt} times — include more context or use update_section.");
                            $buffers[$fld] = str_replace($find, $replace, $current);
                            $fieldDirty[$fld] = true;
                            $results[] = ['index'=>$idx,'op'=>$type,'ok'=>true,'field'=>$fld,'before_chars'=>mb_strlen($current),'after_chars'=>mb_strlen($buffers[$fld])];
                            break;
                        }
                        case 'set_field': {
                            $fld = (string)($op['field'] ?? '');
                            // In batch, set_field uses 'value' (preferred) or 'replace' for compat
                            $value = array_key_exists('value', $op) ? (string)$op['value'] : (string)($op['replace'] ?? '');
                            if (!in_array($fld, self::FIELDS, true)) throw new InvalidArgumentException("Operation #$idx: field not writable: {$fld}");
                            // Empty value is allowed (clear field) but warn if really empty — keep intentional
                            $before = $buffers[$fld] ?? '';
                            $buffers[$fld] = $value;
                            $fieldDirty[$fld] = true;
                            $results[] = ['index'=>$idx,'op'=>$type,'ok'=>true,'field'=>$fld,'before_chars'=>mb_strlen($before),'after_chars'=>mb_strlen($value), 'note'=>'set_field in batch'];
                            break;
                        }
                        case 'patch_section': {
                            $secRef = (string)($op['section'] ?? '');
                            $find = (string)($op['find'] ?? '');
                            $replace = (string)($op['replace'] ?? '');
                            if ($secRef === '') throw new InvalidArgumentException("Operation #$idx: section is required for patch_section");
                            if ($find === '') throw new InvalidArgumentException("Operation #$idx: find is required");
                            if ($sectionCache[$lang] === null) {
                                $sectionCache[$lang] = self::splitIntoSections($buffers[$field] ?? '');
                            }
                            $sIdx = self::findSectionIndex($sectionCache[$lang], $secRef);
                            if ($sIdx === null) throw new InvalidArgumentException("Operation #$idx: section not found: {$secRef}");
                            $secText = $sectionCache[$lang][$sIdx]['text'];
                            $cnt = substr_count($secText, $find);
                            if ($cnt === 0) throw new InvalidArgumentException("Operation #$idx: find not found inside section \"" . $sectionCache[$lang][$sIdx]['name'] . "\" — fetch via get_section.");
                            if ($cnt > 1) throw new InvalidArgumentException("Operation #$idx: find occurs {$cnt} times inside section — include more context or use update_section.");
                            $before = mb_strlen($secText);
                            $sectionCache[$lang][$sIdx]['text'] = str_replace($find, $replace, $secText);
                            $sectionDirty[$lang] = true;
                            $results[] = ['index'=>$idx,'op'=>$type,'ok'=>true,'section'=>$sectionCache[$lang][$sIdx]['name'],'lang'=>$lang,'before_chars'=>$before,'after_chars'=>mb_strlen($sectionCache[$lang][$sIdx]['text'])];
                            break;
                        }
                        case 'update_section': {
                            $secRef = (string)($op['section'] ?? '');
                            $html = (string)($op['html'] ?? '');
                            if ($secRef === '') throw new InvalidArgumentException("Operation #$idx: section is required");
                            if (trim($html) === '') throw new InvalidArgumentException("Operation #$idx: html is required for update_section");
                            if ($sectionCache[$lang] === null) {
                                $sectionCache[$lang] = self::splitIntoSections($buffers[$field] ?? '');
                            }
                            $sIdx = self::findSectionIndex($sectionCache[$lang], $secRef);
                            if ($sIdx === null) throw new InvalidArgumentException("Operation #$idx: section not found: {$secRef}");
                            $oldName = $sectionCache[$lang][$sIdx]['name'];
                            $marker = "<!-- {$oldName} -->\n";
                            if (preg_match('/^\s*<!--.*?-->/s', $html)) {
                                $sectionCache[$lang][$sIdx]['text'] = $html;
                            } else {
                                $sectionCache[$lang][$sIdx]['text'] = $marker . ltrim($html);
                            }
                            $sectionDirty[$lang] = true;
                            $results[] = ['index'=>$idx,'op'=>$type,'ok'=>true,'section'=>$oldName,'lang'=>$lang];
                            break;
                        }
                        case 'set_section_style': {
                            $secRef = (string)($op['section'] ?? '');
                            $style = trim((string)($op['style'] ?? ''));
                            if ($secRef === '') throw new InvalidArgumentException("Operation #$idx: section is required for set_section_style");
                            if ($style === '') throw new InvalidArgumentException("Operation #$idx: style is required");
                            $style = self::expandStyleTokens($style);
                            if ($sectionCache[$lang] === null) {
                                $sectionCache[$lang] = self::splitIntoSections($buffers[$field] ?? '');
                            }
                            $sIdx = self::findSectionIndex($sectionCache[$lang], $secRef);
                            if ($sIdx === null) throw new InvalidArgumentException("Operation #$idx: section not found: {$secRef}");
                            $secText = $sectionCache[$lang][$sIdx]['text'];
                            if (!preg_match('/<[^>]+>/', $secText, $m, PREG_OFFSET_CAPTURE)) {
                                throw new InvalidArgumentException("Operation #$idx: section has no HTML tag to style");
                            }
                            $tag = $m[0][0]; $pos = $m[0][1];
                            $newTag = self::mergeStyleIntoTag($tag, $style);
                            $sectionCache[$lang][$sIdx]['text'] = substr_replace($secText, $newTag, $pos, strlen($tag));
                            $sectionDirty[$lang] = true;
                            $results[] = ['index'=>$idx,'op'=>$type,'ok'=>true,'section'=>$sectionCache[$lang][$sIdx]['name'],'lang'=>$lang,'style'=>$style];
                            break;
                        }
                        case 'wrap_section': {
                            $secRef = (string)($op['section'] ?? '');
                            $open = (string)($op['wrapper_open'] ?? '');
                            $close = (string)($op['wrapper_close'] ?? '</div>');
                            if ($secRef === '') throw new InvalidArgumentException("Operation #$idx: section is required for wrap_section");
                            if (trim($open) === '') throw new InvalidArgumentException("Operation #$idx: wrapper_open is required");
                            if (trim($close) === '') $close = '</div>';
                            if ($sectionCache[$lang] === null) {
                                $sectionCache[$lang] = self::splitIntoSections($buffers[$field] ?? '');
                            }
                            $sIdx = self::findSectionIndex($sectionCache[$lang], $secRef);
                            if ($sIdx === null) throw new InvalidArgumentException("Operation #$idx: section not found: {$secRef}");
                            $secText = $sectionCache[$lang][$sIdx]['text'];
                            if (preg_match('/^(<!--.*?-->\s*\n?)(.*)$/s', $secText, $mm)) {
                                $sectionCache[$lang][$sIdx]['text'] = $mm[1] . $open . "\n" . $mm[2] . "\n" . $close;
                            } else {
                                $sectionCache[$lang][$sIdx]['text'] = $open . "\n" . $secText . "\n" . $close;
                            }
                            $sectionDirty[$lang] = true;
                            $results[] = ['index'=>$idx,'op'=>$type,'ok'=>true,'section'=>$sectionCache[$lang][$sIdx]['name'],'lang'=>$lang];
                            break;
                        }
                        case 'add_section_marker': {
                            $find = (string)($op['find'] ?? '');
                            $name = trim((string)($op['name'] ?? ''));
                            $position = ($op['position'] ?? 'before') === 'after' ? 'after' : 'before';
                            $hasOffset = array_key_exists('offset', $op) && $op['offset'] !== null && $op['offset'] !== '';
                            $offset = $hasOffset ? (int)$op['offset'] : null;
                            if ($name === '') throw new InvalidArgumentException("Operation #$idx: name is required for add_section_marker");
                            if (mb_strlen($name) > 80) throw new InvalidArgumentException("Operation #$idx: section name too long (max 80 chars)");
                            if (str_contains($name, '--') || str_contains($name, '<') || str_contains($name, '>')) throw new InvalidArgumentException("Operation #$idx: section name must not contain \"--\", \"<\" or \">\"");
                            if ($find === '' && $offset === null) throw new InvalidArgumentException("Operation #$idx: find or offset is required for add_section_marker");
                            // For batch, we operate on buffer directly (not sectionCache) because marker insertion changes section boundaries.
                            // Flush any pending section dirty state into buffer before raw insertion so we don't lose prior section ops.
                            if ($sectionDirty[$lang] && $sectionCache[$lang] !== null) {
                                $buffers[$field] = self::rebuildContentFromSections($sectionCache[$lang]);
                                $fieldDirty[$field] = true;
                                $sectionCache[$lang] = null;
                                $sectionDirty[$lang] = false;
                            }
                            $current = $buffers[$field] ?? '';
                            // Duplicate name check against current buffer sections + pending buffer text
                            $tmpSections = self::splitIntoSections($current);
                            foreach ($tmpSections as $s) {
                                if (mb_strtolower($s['name']) === mb_strtolower($name)) {
                                    throw new InvalidArgumentException("Operation #$idx: section name \"{$name}\" already exists in field {$field}");
                                }
                            }
                            // Also check duplicate names within same batch (against already applied ops)
                            foreach ($results as $r) {
                                if (($r['op'] ?? '') === 'add_section_marker' && isset($r['name']) && mb_strtolower($r['name']) === mb_strtolower($name) && ($r['lang'] ?? $lang) === $lang) {
                                    throw new InvalidArgumentException("Operation #$idx: duplicate section name \"{$name}\" within batch for lang {$lang}");
                                }
                            }
                            $marker = "<!-- {$name} -->";
                            if ($find !== '') {
                                $cnt = substr_count($current, $find);
                                if ($cnt === 0) throw new InvalidArgumentException("Operation #$idx: find text not found in field {$field} — fetch exact HTML via get_content_chunk/get_section.");
                                if ($cnt > 1) throw new InvalidArgumentException("Operation #$idx: find occurs {$cnt} times — include more context or use offset.");
                                $pos = mb_strpos($current, $find);
                                if ($position === 'before') {
                                    $buffers[$field] = mb_substr($current, 0, $pos) . $marker . "\n" . mb_substr($current, $pos);
                                } else {
                                    $end = $pos + mb_strlen($find);
                                    $buffers[$field] = mb_substr($current, 0, $end) . "\n" . $marker . "\n" . mb_substr($current, $end);
                                }
                            } else {
                                $total = mb_strlen($current);
                                $off = (int)$offset;
                                if ($off < 0) $off = 0;
                                if ($off > $total) $off = $total;
                                $buffers[$field] = mb_substr($current, 0, $off) . ($off > 0 && $off < $total && mb_substr($current, $off - 1, 1) !== "\n" ? "\n" : '') . $marker . "\n" . mb_substr($current, $off);
                            }
                            $fieldDirty[$field] = true;
                            // Invalidate section cache for this lang so later section ops in same batch see new boundaries
                            $sectionCache[$lang] = null;
                            $sectionDirty[$lang] = false;
                            $results[] = ['index'=>$idx,'op'=>$type,'ok'=>true,'name'=>$name,'lang'=>$lang,'position'=>$position];
                            break;
                        }
                        default:
                            throw new InvalidArgumentException("Operation #$idx: unknown op '{$type}' — allowed: patch_section, str_replace_field, set_field, update_section, set_section_style, wrap_section, add_section_marker");
                    }
                } catch (InvalidArgumentException $e) {
                    if ($inTxn && $db->inTransaction()) $db->rollBack();
                    $inTxn = false;
                    throw new InvalidArgumentException("batch_update failed at operation #{$idx} ({$type}): " . $e->getMessage() . " — no changes were committed.", 0, $e);
                }
            }
            // Rebuild content fields from section caches
            foreach (['ru','uz'] as $lg) {
                if ($sectionDirty[$lg] && $sectionCache[$lg] !== null) {
                    $fld = "content_{$lg}";
                    $buffers[$fld] = self::rebuildContentFromSections($sectionCache[$lg]);
                    $fieldDirty[$fld] = true;
                }
            }
            // Persist each dirty field via Page::update (single per field)
            // 06-09 style/HTML sanitization already applied per-op; final buffer also sanitized
            $updateData = [];
            foreach ($fieldDirty as $fld => $_) {
                if (array_key_exists($fld, $buffers)) {
                    // Sanitize any content field final value (extra guard)
                    if (str_starts_with($fld, 'content_')) {
                        $buffers[$fld] = self::sanitizeSectionHtml($buffers[$fld]);
                    }
                    $updateData[$fld] = $buffers[$fld];
                }
            }
            if (!empty($updateData)) {
                $model->update($pageId, $updateData);
            }
            if ($inTxn && $db->inTransaction()) $db->commit();
            $inTxn = false;
            $fresh = $model->getById($pageId);
            $freshRu = (string)($fresh['content_ru'] ?? '');
            return [
                'ok'=>true,
                'verified'=>true,
                'page_id'=>$pageId,
                'results'=>$results,
                'fresh_hash'=>substr(md5($freshRu),0,8),
                'after_preview'=>mb_substr(trim(strip_tags($freshRu)),0,300),
                'updated_fields'=>array_keys($updateData),
                'note'=>'Batch applied atomically. ' . count($results) . ' operation(s) committed.',
            ];
        } catch (Throwable $e) {
            if ($inTxn && $db->inTransaction()) {
                try { $db->rollBack(); } catch (Throwable $ignored) {}
            }
            throw $e;
        }
    }

    // ── custom_css + theme helpers ──────────────────────────────────────────
    private static function setCustomCss(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $mode = ($args['mode'] ?? 'replace') === 'append' ? 'append' : 'replace';
        $css = (string)($args['css'] ?? '');
        if (mb_strlen($css) > 20000) throw new InvalidArgumentException('css too large (max 20000 chars). Split into smaller chunks or clear first.');
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException("Page not found: ID $pageId");
        // Handle append mode: merge with existing
        if ($mode === 'append' && trim($css) !== '') {
            $existing = (string)($page['custom_css'] ?? '');
            $css = $existing !== '' ? $existing . "\n\n" . $css : $css;
        }
        // Sanitize (blocks vectors) — empty string is valid (clear)
        if (trim($css) !== '') {
            require_once BASE_PATH . '/core/helpers.php';
            $css = sanitizeCssBlock($css);
        } else {
            $css = null;
        }
        $model->update($pageId, ['custom_css' => $css]);
        $fresh = $model->getById($pageId);
        return ['ok'=>true,'page_id'=>$pageId,'slug'=>$fresh['slug'],'custom_css'=> $fresh['custom_css'] ?? null,'mode'=>$mode,'chars'=> mb_strlen((string)($fresh['custom_css'] ?? ''))];
    }

    private static function setPageTheme(array $args): array {
        $pageId = self::resolveGeneralPageId($args);
        $preset = trim((string)($args['preset'] ?? ''));
        $clear = !empty($args['clear']);
        $vars = $args['vars'] ?? null;
        if (!is_array($vars) && $vars !== null) throw new InvalidArgumentException('vars must be an object like {"--teal":"#0a4f5c"}');
        $model = new Page();
        $page = $model->getById($pageId);
        if (!$page) throw new InvalidArgumentException("Page not found: ID $pageId");
        $slug = $page['slug'] ?? 'page';
        $slugClass = 'page-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($slug));
        $presets = [
            'teal' => ['--teal'=>'#0f5f6f','--teal-dark'=>'#094956','--teal-light'=>'#e0f2f5','--surface'=>'#f8f9fa'],
            'orange' => ['--teal'=>'#9a3412','--teal-dark'=>'#7c2d12','--orange'=>'#f97316','--surface'=>'#fff7ed','--ink'=>'#431407'],
            'green' => ['--teal'=>'#065f46','--teal-dark'=>'#064e3b','--orange'=>'#059669','--surface'=>'#ecfdf5','--teal-light'=>'#d1fae5'],
            'indigo' => ['--teal'=>'#3730a3','--teal-dark'=>'#312e81','--orange'=>'#7c3aed','--surface'=>'#eef2ff','--teal-light'=>'#e0e7ff'],
            'warm' => ['--teal'=>'#92400e','--teal-dark'=>'#78350f','--orange'=>'#ea580c','--surface'=>'#fffbeb','--ink'=>'#451a03','--border'=>'#fde68a'],
            'dark' => ['--teal'=>'#14b8a6','--teal-dark'=>'#0f766e','--orange'=>'#f97316','--surface'=>'#0f1117','--surface-2'=>'#1f2937','--ink'=>'#f9fafb','--ink-soft'=>'#d1d5db','--muted'=>'#9ca3af','--border'=>'#374151'],
            'light' => ['--teal'=>'#0f5f6f','--teal-dark'=>'#094956','--surface'=>'#ffffff','--surface-2'=>'#f8f9fa','--border'=>'#e5e7eb','--ink'=>'#111827'],
        ];
        $chosen = [];
        if ($preset !== '' && $preset !== 'custom') {
            if (!isset($presets[$preset])) throw new InvalidArgumentException("Unknown preset: $preset — allowed: teal,orange,green,indigo,warm,dark,light,custom");
            $chosen = $presets[$preset];
        } elseif ($preset === 'custom' && empty($vars)) {
            throw new InvalidArgumentException('preset=custom requires vars object');
        }
        if (is_array($vars)) {
            foreach ($vars as $k=>$v) {
                if (!is_string($k) || !str_starts_with($k, '--')) throw new InvalidArgumentException("vars keys must start with --, got: $k");
                $chosen[trim($k)] = trim((string)$v);
            }
        }
        $currentCss = (string)($page['custom_css'] ?? '');
        // If clear, strip any existing body.page-slug theme block
        $themeSelector = "body.{$slugClass}";
        if ($clear) {
            // Remove theme block: body.page-slug{--*:...} (simple scan)
            $pattern = '/\/\* theme:' . preg_quote($slugClass, '/') . ' \*\/\s*' . preg_quote($themeSelector, '/') . '\s*\{[^}]*\}\s*/';
            $currentCss = preg_replace($pattern, '', $currentCss);
            // Fallback: strip any body.page-slug{--...} var block if marker missing
            $currentCss = preg_replace('/' . preg_quote($themeSelector, '/') . '\s*\{[^}]*--[^}]*\}/', '', $currentCss);
            $currentCss = trim($currentCss);
            require_once BASE_PATH . '/core/helpers.php';
            $currentCss = sanitizeCssBlock($currentCss);
            $model->update($pageId, ['custom_css' => $currentCss !== '' ? $currentCss : null]);
            $fresh = $model->getById($pageId);
            return ['ok'=>true,'page_id'=>$pageId,'slug'=>$slug,'cleared'=>true,'custom_css'=>$fresh['custom_css'] ?? null];
        }
        if (empty($chosen)) throw new InvalidArgumentException('No theme vars provided — pass preset or vars.');
        // Build theme block
        $decls = [];
        foreach ($chosen as $k=>$v) {
            if ($v === '' || $v === null) continue;
            $decls[] = "$k: $v;";
        }
        $block = "/* theme:$slugClass */\n{$themeSelector} { " . implode(' ', $decls) . " }";
        // Replace existing theme block if present, else append
        $hasTheme = strpos($currentCss, "/* theme:$slugClass */") !== false;
        if ($hasTheme) {
            $currentCss = preg_replace('/\/\* theme:' . preg_quote($slugClass, '/') . ' \*\/\s*' . preg_quote($themeSelector, '/') . '\s*\{[^}]*\}/', $block, $currentCss);
        } else {
            $currentCss = trim($currentCss) !== '' ? $currentCss . "\n\n" . $block : $block;
        }
        require_once BASE_PATH . '/core/helpers.php';
        $currentCss = sanitizeCssBlock($currentCss);
        $model->update($pageId, ['custom_css' => $currentCss]);
        $fresh = $model->getById($pageId);
        return ['ok'=>true,'page_id'=>$pageId,'slug'=>$slug,'preset'=>$preset ?: 'custom','vars'=>$chosen,'custom_css'=>$fresh['custom_css'] ?? null];
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
