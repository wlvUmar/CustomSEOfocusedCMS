<?php
// path: ./models/ai/tools/SiteTools.php
// Site-wide context tools: global settings, template variables, design
// tokens, and the live preview renderer used by the studio's preview pane.

require_once BASE_PATH . '/models/SEO.php';
require_once BASE_PATH . '/models/FAQ.php';

class SiteTools {

    public static function definitions(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_global_settings',
                    'description' => 'Global site settings (seo_settings): site names, phone, email, address, working hours, city, social links. Read these before writing anything that references the business.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_template_variables',
                    'description' => 'The template variables available in page content fields and how they resolve ({{page.title}}, {{global.phone}}, {{global.email}}, {{global.address}}, {{global.working_hours}}, {{global.site_name}}, {{date.year}}, {{date.month}}, {{faqs}}...). You must preserve these exactly in any content you write.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_design_tokens',
                    'description' => 'Design tokens + component library: parses public/css/pages.css :root (--teal,--orange,--green,--ink, spacing etc.) + public/css/components.css 178 .c-* plugin classes (c-hero-*,c-stats, c-feature-*, c-process/timeline, c-card/testimonial, c-cta/callout, c-gallery/carousel, c-pricing etc.). Returns live catalog, plus per-page theming note (pages.custom_css injected AFTER both sheets as <style id="page-custom-css"> scoped via body.page-{slug}; tools set_custom_css/set_page_theme write it). Call before writing any section; prefer tokens var(--teal) — custom hex only on explicit request.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'render_preview',
                    'description' => 'Render an HTML fragment (page content, a single section, or a whole page body) the way the live site would render it — template variables resolved with real global settings, site stylesheet applied. The result appears in the live preview pane so you can judge it visually. Call this after creating or editing any section. Optional faqs_for_slug fills the {{faqs}} loop.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'html' => ['type' => 'string', 'description' => 'The HTML fragment to render (content sections, not a full document).'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz'], 'description' => 'Language used for global settings and FAQs (default ru).'],
                            'page_title' => ['type' => 'string', 'description' => 'Optional value for {{page.title}}.'],
                            'page_slug' => ['type' => 'string', 'description' => 'Optional page slug for {{page.slug}} and FAQ lookups.'],
                            'faqs_for_slug' => ['type' => 'string', 'description' => 'Page slug whose FAQs should populate {{faqs}} (uses real FAQ data).'],
                        ],
                        'required' => ['html'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'render_full_page',
                    'description' => 'Render a full page document with header and footer, using real page data and rotation (if any). Prefer render_preview for per-section iteration; call render_full_page for final header/footer check. Returns HTML with header + content + footer.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id (alternative to slug).'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'lang' => ['type' => 'string', 'enum' => ['ru', 'uz'], 'description' => 'Language (default ru).'],
                            'html_override' => ['type' => 'string', 'description' => 'Optional HTML to use instead of stored page content (for previewing unsaved edits).'],
                            'month' => ['type' => 'integer', 'description' => 'Optional rotation month 1-12 (default current month).'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        switch ($name) {
            case 'get_global_settings':
                return self::globalSettings();
            case 'get_template_variables':
                return self::templateVariables();
            case 'get_design_tokens':
                return self::designTokens();
            case 'render_preview':
                return self::renderPreview($args);
            case 'render_full_page':
                return self::renderFullPage($args);
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    /** Build the same template data shape the preview controller uses. */
    public static function buildTemplateData(string $lang, string $pageTitle = '', string $pageSlug = '', string $faqsForSlug = ''): array {
        $lang = $lang === 'uz' ? 'uz' : 'ru';
        $seo = new SEO();
        $settings = $seo->getSettings() ?: [];

        $now = new DateTime();
        $faqs = [];
        if ($faqsForSlug !== '') {
            $faqModel = new FAQ();
            $faqs = $faqModel->getBySlug($faqsForSlug) ?: [];
        }

        return [
            'page' => [
                'title' => $pageTitle,
                'slug' => $pageSlug,
            ],
            'global' => [
                'phone' => $settings['phone'] ?? '',
                'email' => $settings['email'] ?? '',
                'address' => $settings["address_{$lang}"] ?? '',
                'working_hours' => $settings["working_hours_{$lang}"] ?? '',
                'site_name' => $settings["site_name_{$lang}"] ?? '',
            ],
            'seo' => $settings,
            'faqs' => $faqs,
            'lang' => $lang,
            'date' => [
                'year' => $now->format('Y'),
                'month' => (int)$now->format('n'),
                'month_name' => $now->format('F'),
                'day' => (int)$now->format('j'),
            ],
        ];
    }

    private static function globalSettings(): array {
        $seo = new SEO();
        $s = $seo->getSettings() ?: [];
        $keys = [
            'site_name_ru', 'site_name_uz', 'meta_description_ru', 'meta_description_uz',
            'phone', 'email', 'address_ru', 'address_uz',
            'working_hours_ru', 'working_hours_uz',
            'city', 'region', 'postal_code', 'country',
            'org_name_ru', 'org_name_uz', 'org_type', 'price_range',
            'social_facebook', 'social_instagram', 'social_twitter', 'social_youtube',
            'google_review_url',
        ];
        $out = [];
        foreach ($keys as $k) {
            if (isset($s[$k]) && $s[$k] !== '' && $s[$k] !== null) {
                $out[$k] = mb_substr((string)$s[$k], 0, 500);
            }
        }
        return $out;
    }

    private static function templateVariables(): array {
        return [
            'available_variables' => [
                '{{page.title}}' => 'The page title in the current language.',
                '{{page.slug}}' => 'The page slug.',
                '{{global.phone}}' => 'Site phone number (from seo_settings).',
                '{{global.email}}' => 'Site email.',
                '{{global.address}}' => 'Address in the current language.',
                '{{global.working_hours}}' => 'Working hours in the current language.',
                '{{global.site_name}}' => 'Site name in the current language.',
                '{{date.year}}' => 'Current year.',
                '{{date.month}}' => 'Current month number (1-12).',
                '{{date.month_name}}' => 'Current month name.',
                '{{date.day}}' => 'Current day of month.',
                '{{faqs}}' => 'FAQ list for the page (rendered via {% for faq in faqs %} loops).',
            ],
            'rules' => [
                'Preserve every {{...}} variable exactly as-is in content you write; never invent new ones.',
                'You may use {% for faq in faqs %}...{% endfor %} and {% if ... %} conditionals — the template engine supports them.',
            ],
        ];
    }

    private static function designTokens(): array {
        $candidates = [
            defined('PUBLIC_PATH') ? PUBLIC_PATH . '/css/pages.css' : null,
            BASE_PATH . '/public/css/pages.css',
        ];
        $css = '';
        foreach (array_filter($candidates) as $path) {
            if (is_file($path)) {
                $css = (string)file_get_contents($path);
                break;
            }
        }
        if ($css === '') {
            return ['note' => 'Stylesheet not found on disk — ask the developer for the token list.', 'tokens' => []];
        }

        $tokens = [];
        if (preg_match('/:root\s*\{([^}]*)\}/s', $css, $m)) {
            if (preg_match_all('/(--[\w-]+)\s*:\s*([^;}]+)/', $m[1], $pairs, PREG_SET_ORDER)) {
                foreach ($pairs as $p) {
                    $tokens[$p[1]] = trim($p[2]);
                }
            }
        }

        $key = ['--teal', '--teal-dark', '--orange', '--green', '--ink', '--ink-soft', '--muted', '--surface', '--surface-2', '--border', '--max-w', '--px', '--section-gap', '--ease', '--dur'];
        $summary = [];
        foreach ($key as $k) {
            if (isset($tokens[$k])) $summary[$k] = $tokens[$k];
        }

        // Plugin component library — parsed live from components.css so AI always sees current catalog
        $componentsCss = '';
        $compCandidates = [
            defined('PUBLIC_PATH') ? PUBLIC_PATH . '/css/components.css' : null,
            BASE_PATH . '/public/css/components.css',
        ];
        foreach (array_filter($compCandidates) as $p) {
            if (is_file($p)) { $componentsCss = (string)file_get_contents($p); break; }
        }
        $componentClasses = [];
        if ($componentsCss !== '' && preg_match_all('/\.(c-[a-z0-9_-]+)/', $componentsCss, $cm)) {
            $componentClasses = array_values(array_unique($cm[1]));
            sort($componentClasses);
        }
        $perPageNote = 'Per-page overrides: pages.custom_css (and content_rotations.custom_css) is injected AFTER pages.min.css+components.min.css as <style id="page-custom-css">, so it can fully override header/footer/any component. Author can also put <style> inside content_ru/uz — it is extracted to head. Scope with body.page-{slug} (e.g. body.page-televizor header{background:var(--teal)}). Tokens via body.page-slug{--teal:#...;--surface:#...}. Tools: set_custom_css (replace/append, 20k) + set_page_theme (presets teal|orange|green|indigo|warm|dark|light|custom vars). Empty custom_css = inherits defaults.';

        return [
            'note' => 'Design tokens from public/css/pages.css + plugin library public/css/components.css. Prefer tokens var(--teal) over new hex. ' . $perPageNote,
            'tokens' => $summary,
            'all_tokens_count' => count($tokens),
            'component_library' => '178 .c-* classes in components.css — use any via HTML without writing CSS. Categories: heroes(c-hero-split/centered/mesh/compact/cards/stats-overlap/video/minimal/gradient-border/testimonial), stats(c-stats/c-stats-bar/dark/bordered/kpi/metrics-row/icon), features(c-feature-grid/list/split/icon-grid/checklist/cards/highlight/comparison/tabs), process(c-process/timeline/timeline-h/steps-bar/zigzag/roadmap), cards(c-card/testimonial/quote/rating/avatar/team/logo-strip/trust-badges), CTAs(c-cta/centered/split/gradient/ghost/callout/banner/notice/promo-bar), media(c-gallery-masonry/grid/media-split/video-embed/before-after/carousel/image-card/map-embed), prose(c-prose/quote/alert/accordion/tabs/table/badge/tags/code), pricing(c-pricing/comparison/feature-table/plan/toggle/discount/guarantee), utilities(c-grid/flex/stack/center/surface/bg-mesh/shape-blob/pattern-dots).',
            'component_classes' => array_slice($componentClasses, 0, 80),
            'component_classes_total' => count($componentClasses),
            'legacy_classes' => ['content-section','info-grid','info-card','process-step','brands-list','faq-item','condition-item','section-label','links-tile','btn','btn-primary'],
        ];
    }

    private static function sanitizeForPreview(string $html): string {
        // Layer 1: regex strips
        $html = (string)preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = (string)preg_replace('/<style\b[^>]*>.*?@import[^<]*<\/style>/is', '', $html);
        $html = (string)preg_replace('/\bon\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = (string)preg_replace('/\b(href|src|action|formaction|xlink:href|srcdoc)\s*=\s*["\']\s*(javascript|data\s*:\s*text\/html|vbscript):[^"\']*["\']/i', '', $html);
        $html = (string)preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html);
        $html = (string)preg_replace('/<(object|embed|link|meta|base)\b[^>]*>/i', '', $html);
        // Layer 2: DOM-based allowlist cleanup for svg/onload, details ontoggle, style expression, etc.
        if (class_exists('DOMDocument')) {
            $prev = libxml_use_internal_errors(true);
            $doc = new DOMDocument();
            // Wrap fragment to parse
            $wrapped = '<div id="__purify_root">' . $html . '</div>';
            $loaded = $doc->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            if ($loaded) {
                $xpath = new DOMXPath($doc);
                // Remove dangerous elements regardless of case
                foreach ($xpath->query('//script|//iframe|//object|//embed|//link|//meta|//base|//form') as $el) {
                    if ($el->parentNode) $el->parentNode->removeChild($el);
                }
                // Scrub attributes per element
                foreach ($xpath->query('//*') as $el) {
                    if (!($el instanceof DOMElement)) continue;
                    $toRemove = [];
                    if ($el->attributes) {
                        foreach ($el->attributes as $attr) {
                            $name = strtolower($attr->nodeName);
                            $val = (string)$attr->nodeValue;
                            if (str_starts_with($name, 'on')) $toRemove[] = $attr->nodeName;
                            elseif (in_array($name, ['href','src','action','formaction','xlink:href','srcdoc','cite','data','background'], true)) {
                                if (preg_match('/^\s*(javascript|data\s*:\s*text\/html|vbscript)\s*:/i', $val)) $toRemove[] = $attr->nodeName;
                            } elseif ($name === 'style') {
                                if (preg_match('/expression\s*\(|javascript\s*:|vbscript\s*:|@import/i', $val)) $toRemove[] = $attr->nodeName;
                            }
                        }
                    }
                    foreach ($toRemove as $n) $el->removeAttribute($n);
                }
                $root = $doc->getElementById('__purify_root');
                if ($root) {
                    $inner = '';
                    foreach ($root->childNodes as $child) $inner .= $doc->saveHTML($child);
                    $html = $inner;
                }
            }
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
        return $html;
    }

    private static function renderPreview(array $args): array {
        $html = (string)($args['html'] ?? '');
        if (trim($html) === '') throw new InvalidArgumentException('html is required — pass the HTML fragment you want to preview (e.g. from get_section html).');
        if (mb_strlen($html) > 200 * 1024) throw new InvalidArgumentException('html too large (max 200KB) — trim content or preview one section at a time via get_section.');
        $html = self::sanitizeForPreview($html);
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $pageTitle = (string)($args['page_title'] ?? '');
        $pageSlug = (string)($args['page_slug'] ?? '');
        $faqsForSlug = (string)($args['faqs_for_slug'] ?? '');

        $data = self::buildTemplateData($lang, $pageTitle, $pageSlug, $faqsForSlug !== '' ? $faqsForSlug : $pageSlug);
        $rendered = renderTemplate($html, $data);

        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        $doc = '<!DOCTYPE html>' . "\n"
            . '<html lang="' . $lang . '">' . "\n"
            . '<head>' . "\n"
            . '<meta charset="utf-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<link rel="stylesheet" href="' . $baseUrl . '/css/pages.css">' . "\n"
            . '<link rel="stylesheet" href="' . $baseUrl . '/css/components.min.css">' . "\n"
            . '<style>html,body{background:var(--surface)}'
            . '*{opacity:1!important;transform:none!important;transition:none!important;animation:none!important}'
            . '</style>' . "\n"
            . '</head>' . "\n"
            . '<body>' . "\n"
            . '<div class="content-body">' . "\n"
            . $rendered . "\n"
            . '</div>' . "\n"
            . '</body>' . "\n"
            . '</html>';

        return ['html' => $doc, 'chars' => mb_strlen($doc), 'lang' => $lang];
    }

    private static function renderFullPage(array $args): array {
        $lang = ($args['lang'] ?? 'ru') === 'uz' ? 'uz' : 'ru';
        $htmlOverride = isset($args['html_override']) ? (string)$args['html_override'] : null;
        $month = isset($args['month']) ? max(1, min(12, (int)$args['month'])) : (int)date('n');
        $page = null;
        if (!empty($args['page_id'])) {
            require_once BASE_PATH . '/models/Page.php';
            $page = (new Page())->getById((int)$args['page_id']);
        } elseif (!empty($args['slug'])) {
            require_once BASE_PATH . '/models/Page.php';
            $page = Database::getInstance()->fetchOne("SELECT * FROM pages WHERE slug = ?", [(string)$args['slug']]);
            if (!$page) {
                $page = (new Page())->getBySlug((string)$args['slug']);
            }
        } else {
            throw new InvalidArgumentException('Provide page_id or slug for render_full_page');
        }
        if (!$page) throw new InvalidArgumentException('Page not found — call list_pages to discover slugs.');
        $pageId = (int)$page['id'];
        $pageSlug = $page['slug'] ?? '';
        $pageTitle = $page["title_{$lang}"] ?? '';
        // Rotation override
        $hasRotation = false;
        $rawContent = $htmlOverride !== null && trim($htmlOverride) !== '' ? $htmlOverride : (string)($page["content_{$lang}"] ?? '');
        if (empty($htmlOverride) && !empty($page['enable_rotation'])) {
            try {
                $rot = Database::getInstance()->fetchOne("SELECT * FROM content_rotations WHERE page_id = ? AND active_month = ? LIMIT 1", [$pageId, $month]);
                if ($rot) {
                    $hasRotation = true;
                    if (!empty($rot["content_{$lang}"])) $rawContent = (string)$rot["content_{$lang}"];
                    if (!empty($rot["title_{$lang}"])) $pageTitle = (string)$rot["title_{$lang}"];
                }
            } catch (Throwable $e) { /* ignore */ }
        }
        if (mb_strlen($rawContent) > 200 * 1024) throw new InvalidArgumentException('html_override too large (max 200KB)');
        $sanitized = self::sanitizeForPreview($rawContent);
        $data = self::buildTemplateData($lang, $pageTitle, $pageSlug, $pageSlug);
        $rendered = renderTemplate($sanitized, $data);
        // Build full document with header/footer shell
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        $seoSettings = $data['seo'] ?? [];
        $siteName = $seoSettings["site_name_{$lang}"] ?? 'Site';
        $headerHtml = '<header><div class="container"><nav><a href="' . $baseUrl . '/" class="logo-link"><span class="site-name">' . htmlspecialchars($siteName, ENT_QUOTES) . '</span></a></div></nav></header>';
        $footerHtml = '<footer><div class="container"><p>&copy; ' . date('Y') . ' ' . htmlspecialchars($siteName, ENT_QUOTES) . '</p></div></footer>';
        // Header/footer captured via synthetic shell only — real templates are not included in agent context (02-architecture #7).
        // Previously @include header.php with globals swallowed errors and triggered side-effects; now we use deterministic synthetic header/footer.
        // If fidelity to real header is needed, render via public preview route instead of direct include.
        $doc = '<!DOCTYPE html>' . "\n"
            . '<html lang="' . $lang . '">' . "\n"
            . '<head>' . "\n"
            . '<meta charset="utf-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<link rel="stylesheet" href="' . $baseUrl . '/css/pages.css">' . "\n"
            . '<link rel="stylesheet" href="' . $baseUrl . '/css/components.min.css">' . "\n"
            . '<style>html,body{background:var(--surface)}*{opacity:1!important;transform:none!important;transition:none!important;animation:none!important}</style>' . "\n"
            . '</head>' . "\n"
            . '<body>' . "\n"
            . $headerHtml . "\n"
            . '<main><div class="content-body">' . "\n"
            . $rendered . "\n"
            . '</div></main>' . "\n"
            . $footerHtml . "\n"
            . '</body>' . "\n"
            . '</html>';
        if (mb_strlen($doc) > 200 * 1024) $doc = mb_substr($doc,0,200*1024);
        return ['html'=>$doc,'chars'=>mb_strlen($doc),'lang'=>$lang,'hasRotation'=>$hasRotation,'page_id'=>$pageId,'slug'=>$pageSlug];
    }
}
