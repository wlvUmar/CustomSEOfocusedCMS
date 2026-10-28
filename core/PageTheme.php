<?php
// path: ./core/PageTheme.php
// Registry of per-page layout themes (pages.theme). Each non-default theme has a
// stylesheet in public/css/themes/{key}.css (scoped to body.theme-{key}), optional
// hero art in views/partials/hero-art/{key}.php and its own Google Fonts families.
// Not to be confused with the AI tool set_page_theme, which only writes colour
// tokens into custom_css.

class PageTheme {
    public const BASE = 'default';

    /** key => [label, fonts (Google Fonts css2 "family=" params, no &display)] */
    private const THEMES = [
        'default' => ['Стандартная (teal)', []],
        'screen'  => ['Телевизоры — cinema',          ['Unbounded:wght@500;600;700', 'Manrope:wght@400;500;600;700;800']],
        'frost'   => ['Холодильники — frost',         ['Jost:wght@500;600;700', 'Manrope:wght@400;500;600;700;800']],
        'wave'    => ['Стиральные машины — wave',     ['Nunito:wght@600;700;800', 'Onest:wght@400;500;600;700']],
        'flame'   => ['Газовые плиты — flame',        ['Oswald:wght@500;600;700', 'Inter:wght@400;500;600']],
        'breeze'  => ['Кондиционеры — breeze',        ['Raleway:wght@500;600;700;800', 'Onest:wght@400;500;600;700']],
        'rug'     => ['Ковры — rug',                  ['Playfair+Display:wght@600;700;800', 'Onest:wght@400;500;600;700']],
        'wood'    => ['Мебель — wood',                ['Lora:wght@500;600;700', 'Onest:wght@400;500;600;700']],
        'fresh'   => ['Прочая бытовая техника — fresh', ['Rubik:wght@500;600;700', 'Golos+Text:wght@400;500;600;700']],
    ];

    /** One-line English description of what the theme is for and how it looks — shown to the AI agent with page reads. */
    private const AI_HINTS = [
        'default' => 'standard teal look (home, about, generic pages)',
        'screen'  => 'TVs/monitors: dark cinema look, letterbox hero, poster cards, film-strip steps',
        'frost'   => 'refrigerators/freezers: fridge hero, shelf cards, thermometer steps',
        'wave'    => 'washing machines: washer hero, bubble cards, wash-cycle steps, chat-style FAQ',
        'flame'   => 'gas stoves/ovens/hobs: dark, stove hero, menu-board cards, heat-level steps',
        'breeze'  => 'air conditioners: A/C unit hero, cloud cards, temperature-scale steps',
        'rug'     => 'carpets/rugs: framed-rug hero, woven ribbon cards, diamond steps',
        'wood'    => 'furniture: magazine hero, catalogue cards',
        'fresh'   => 'other home appliances: bento cards',
    ];

    public static function aiHint(string $key): string {
        return self::AI_HINTS[$key] ?? self::AI_HINTS[self::BASE];
    }

    public static function all(): array {
        $out = [];
        foreach (self::THEMES as $key => $def) $out[$key] = $def[0];
        return $out;
    }

    public static function keys(): array {
        return array_keys(self::THEMES);
    }

    public static function isValid($key): bool {
        return is_string($key) && isset(self::THEMES[$key]);
    }

    /** Unknown / empty values fall back to the default theme. */
    public static function normalize($key): string {
        $key = is_string($key) ? strtolower(trim($key)) : '';
        return isset(self::THEMES[$key]) ? $key : self::BASE;
    }

    public static function bodyClass(string $key): string {
        return $key === self::BASE ? '' : 'theme-' . $key;
    }

    /** URL of the theme stylesheet (cache-busted by mtime), or '' for the default theme. */
    public static function cssUrl(string $key): string {
        if ($key === self::BASE || !isset(self::THEMES[$key])) return '';
        $file = BASE_PATH . '/public/css/themes/' . $key . '.css';
        $v = is_file($file) ? filemtime($file) : time();
        return BASE_URL . '/css/themes/' . $key . '.css?v=' . $v;
    }

    /** Google Fonts stylesheet URL for the theme's families, or '' when it has none. */
    public static function fontsUrl(string $key): string {
        $families = self::THEMES[$key][1] ?? [];
        if (!$families) return '';
        return 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $families) . '&display=swap';
    }

    /** Path of the hero-art partial for this theme, or null if there is none. */
    public static function heroArtPartial(string $key): ?string {
        if ($key === self::BASE || !isset(self::THEMES[$key])) return null;
        $path = BASE_PATH . '/views/partials/hero-art/' . $key . '.php';
        return is_file($path) ? $path : null;
    }

    /**
     * Keyword rules used by migration 015 to pre-assign themes to existing pages.
     * Order matters: first match wins. Matched against lower-cased slug + RU title.
     */
    public static function autoAssignRules(): array {
        return [
            'screen' => ['televizor', 'телевизор', 'monitor', 'монитор'],
            'frost'  => ['xolodil', 'holodil', 'morozil', 'холодил', 'морозил', 'refrigerator'],
            'wave'   => ['stiral', 'стирал'],
            'flame'  => ['gaz', 'plit', 'газов', 'плит', 'духов', 'варочн'],
            'breeze' => ['kondits', 'konditsion', 'кондиц', 'split', 'сплит'],
            'rug'    => ['kovr', 'ковр', 'palas', 'палас'],
            'wood'   => ['mebel', 'мебел', 'divan', 'диван', 'krovat', 'кроват', 'shkaf', 'шкаф', 'komod', 'комод', 'stol', 'стол', 'stul', 'стул'],
            'fresh'  => ['mikrovoln', 'микроволн', 'posudom', 'посудом', 'pylesos', 'пылесос', 'vodonagr', 'водонагр', 'utyug', 'утюг'],
        ];
    }
}
