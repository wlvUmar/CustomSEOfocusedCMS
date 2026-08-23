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
                    'parameters' => ['type' => 'object', 'properties' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_template_variables',
                    'description' => 'The template variables available in page content fields and how they resolve ({{page.title}}, {{global.phone}}, {{global.email}}, {{global.address}}, {{global.working_hours}}, {{global.site_name}}, {{date.year}}, {{date.month}}, {{faqs}}...). You must preserve these exactly in any content you write.',
                    'parameters' => ['type' => 'object', 'properties' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_design_tokens',
                    'description' => 'The site\'s design tokens parsed from the real stylesheet (public/css/pages.css): colors (--teal, --orange, --green, --ink...), spacing, max width, section gaps, easing. Use these to keep new sections on-brand — do not invent your own color palette.',
                    'parameters' => ['type' => 'object', 'properties' => []],
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

        return [
            'note' => 'Design tokens from public/css/pages.css. Prefer these over new colors. The site\'s known section classes: content-section, info-card, process-step, faq-item, links-tile, btn/btn-primary, section-label, condition-item.',
            'tokens' => $summary,
            'all_tokens_count' => count($tokens),
        ];
    }

    private static function renderPreview(array $args): array {
        $html = (string)($args['html'] ?? '');
        if (trim($html) === '') throw new InvalidArgumentException('html is required');
        if (mb_strlen($html) > 200 * 1024) throw new InvalidArgumentException('html too large (max 200KB)');
        // Strip scripts and event handlers for preview sandbox XSS hardening (H9).
        $html = (string)preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = (string)preg_replace('/\bon\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = (string)preg_replace('/\b(href|src)\s*=\s*["\']\s*javascript:[^"\']*["\']/i', '', $html);
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
}
