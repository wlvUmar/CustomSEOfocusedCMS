<?php
// path: ./models/ai/tools/PageBodyTools.php
// rewrite_page_bodies: replace the full RU and UZ body (content_ru / content_uz) of up to
// 10 pages in one call. Everything is validated first; nothing is written unless every
// page passes. Each write goes through Page::update(), which snapshots the old row to
// page_revisions, so every rewrite can be restored.
//
// Why this exists: update_section needs a fresh section hash per write, and the hashes go
// stale after each edit, so rewriting one page took ~30 calls. This writes a whole page in one.

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/ai/tools/PageReader.php';

class PageBodyTools {

    private const MAX_PAGES = 10;
    private const MAX_CHARS = 60000;

    /**
     * Claims the owner has not confirmed yet. Blocked unless the caller sets
     * allow_flagged=true on that page (only after the owner confirms the claim).
     */
    private const FLAGGED = [
        '/\b5\s*мин/iu' => 'a "5 minutes" evaluation-time claim (not yet confirmed by the owner)',
        '/\b5\s*daqiqa/iu' => 'a "5 daqiqa" evaluation-time claim (not yet confirmed by the owner)',
    ];

    public static function definitions(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'rewrite_page_bodies',
                    'description' => 'Replace the full RU and UZ body (content_ru + content_uz) of up to 10 pages in one call, and optionally the RU+UZ meta title / meta description pairs. '
                        . 'Use for de-templating and full rewrites. Always send both languages for every page; they must have the same number of <!-- Section --> markers and the same {{placeholders}}. '
                        . 'Body only: no html/head/body/header/footer/script tags and no inline style="" attributes (use .c-* classes). '
                        . 'Everything is validated first; if any page fails, nothing is written. Each written page is snapshotted to page_revisions first, so it can be restored. '
                        . 'Blocks unconfirmed time claims ("5 минут") unless allow_flagged is true for that page.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'pages' => [
                                'type' => 'array',
                                'minItems' => 1,
                                'maxItems' => self::MAX_PAGES,
                                'description' => 'Pages to rewrite. Each needs slug or page_id, plus content_ru and content_uz.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'slug' => ['type' => 'string', 'description' => 'Page slug, e.g. "televizor".'],
                                        'page_id' => ['type' => 'integer', 'description' => 'Page id (alternative to slug).'],
                                        'content_ru' => ['type' => 'string', 'description' => 'Full RU body HTML (sections marked with <!-- Name --> comments).'],
                                        'content_uz' => ['type' => 'string', 'description' => 'Full UZ body HTML, same section structure as content_ru.'],
                                        'meta_title_ru' => ['type' => 'string', 'description' => 'Optional. RU meta title (send with meta_title_uz).'],
                                        'meta_title_uz' => ['type' => 'string', 'description' => 'Optional. UZ meta title (send with meta_title_ru).'],
                                        'meta_description_ru' => ['type' => 'string', 'description' => 'Optional. RU meta description (send with meta_description_uz).'],
                                        'meta_description_uz' => ['type' => 'string', 'description' => 'Optional. UZ meta description (send with meta_description_ru).'],
                                        'allow_flagged' => ['type' => 'boolean', 'description' => 'Only true after the owner confirmed a flagged claim for this page.'],
                                    ],
                                    'required' => ['content_ru', 'content_uz'],
                                    'additionalProperties' => false,
                                ],
                            ],
                        ],
                        'required' => ['pages'],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        if ($name !== 'rewrite_page_bodies') {
            throw new InvalidArgumentException("Unknown tool: {$name}");
        }
        $pages = $args['pages'] ?? null;
        if (!is_array($pages) || $pages === []) {
            throw new InvalidArgumentException('pages must be a non-empty array.');
        }
        if (count($pages) > self::MAX_PAGES) {
            throw new InvalidArgumentException('Too many pages (max ' . self::MAX_PAGES . ' per call).');
        }

        $model = new Page();
        $plan = [];
        $errors = [];
        $seen = [];

        // Phase 1: validate every page. Nothing is written in this phase.
        foreach (array_values($pages) as $i => $p) {
            if (!is_array($p)) {
                $errors[] = "pages[{$i}]: must be an object";
                continue;
            }
            try {
                $pageId = PageReader::resolveGeneralPageId($p);
            } catch (Throwable $e) {
                $errors[] = "pages[{$i}]: " . $e->getMessage();
                continue;
            }
            if (isset($seen[$pageId])) {
                $errors[] = "pages[{$i}]: page {$pageId} is listed twice";
                continue;
            }
            $seen[$pageId] = true;

            $page = $model->getById($pageId);
            if (!$page) {
                $errors[] = "pages[{$i}]: page {$pageId} not found";
                continue;
            }
            $ru = trim((string)($p['content_ru'] ?? ''));
            $uz = trim((string)($p['content_uz'] ?? ''));
            $problems = self::validate($ru, $uz, !empty($p['allow_flagged']));

            // Optional meta pairs: a language may be sent only together with its counterpart.
            $meta = [];
            foreach (['meta_title', 'meta_description'] as $base) {
                $hasRu = array_key_exists($base . '_ru', $p);
                $hasUz = array_key_exists($base . '_uz', $p);
                if (!$hasRu && !$hasUz) continue;
                if ($hasRu !== $hasUz) {
                    $problems[] = "{$base}: send both {$base}_ru and {$base}_uz";
                    continue;
                }
                $mRu = trim((string)$p[$base . '_ru']);
                $mUz = trim((string)$p[$base . '_uz']);
                if ($mRu === '' || $mUz === '') {
                    $problems[] = "{$base}: RU and UZ values must not be empty";
                    continue;
                }
                if (preg_match('/<|\sstyle\s*=/i', $mRu . $mUz)) {
                    $problems[] = "{$base}: plain text only, no HTML";
                    continue;
                }
                $meta[$base . '_ru'] = $mRu;
                $meta[$base . '_uz'] = $mUz;
            }

            if ($problems) {
                $errors[] = "pages[{$i}] ({$page['slug']}): " . implode('; ', $problems);
                continue;
            }
            $plan[] = [
                'id' => $pageId,
                'slug' => (string)$page['slug'],
                'ru' => $ru,
                'uz' => $uz,
                'meta' => $meta,
                'before_ru_chars' => mb_strlen((string)$page['content_ru']),
                'before_uz_chars' => mb_strlen((string)$page['content_uz']),
            ];
        }
        if ($errors) {
            throw new InvalidArgumentException('Nothing was written. ' . implode(' | ', $errors));
        }

        // Phase 2: write. Page::update() snapshots each page before changing it.
        $results = [];
        foreach ($plan as $w) {
            try {
                $model->update($w['id'], ['content_ru' => $w['ru'], 'content_uz' => $w['uz']] + $w['meta']);
            } catch (Throwable $e) {
                return [
                    'ok' => false,
                    'written' => count($results),
                    'pages' => $results,
                    'failed_page' => $w['slug'],
                    'error' => $e->getMessage(),
                    'note' => 'Stopped at the failed page; pages listed above were written and snapshotted.',
                ];
            }
            $fresh = $model->getById($w['id']);
            $metaOk = true;
            foreach ($w['meta'] as $k => $v) {
                if ((string)($fresh[$k] ?? '') !== $v) $metaOk = false;
            }
            $results[] = [
                'page_id' => $w['id'],
                'slug' => $w['slug'],
                'verified' => (string)$fresh['content_ru'] === $w['ru'] && (string)$fresh['content_uz'] === $w['uz'] && $metaOk,
                'before_ru_chars' => $w['before_ru_chars'],
                'after_ru_chars' => mb_strlen($w['ru']),
                'before_uz_chars' => $w['before_uz_chars'],
                'after_uz_chars' => mb_strlen($w['uz']),
                'sections' => self::countSections($w['ru']),
            ];
        }

        return [
            'ok' => true,
            'written' => count($results),
            'pages' => $results,
            'note' => 'Snapshots saved to page_revisions. Run get_seo_audit on these slugs to confirm the warnings are gone.',
        ];
    }

    /** Returns a list of problems (empty when the page can be written). */
    private static function validate(string $ru, string $uz, bool $allowFlagged): array {
        $problems = [];
        if ($ru === '') $problems[] = 'content_ru is empty';
        if ($uz === '') $problems[] = 'content_uz is empty';
        if ($problems) return $problems;

        foreach (['content_ru' => $ru, 'content_uz' => $uz] as $label => $html) {
            if (mb_strlen($html) > self::MAX_CHARS) {
                $problems[] = "{$label} is longer than " . self::MAX_CHARS . ' characters';
            }
            if (preg_match('/<\s*(html|head|body|header|footer)\b|<!doctype/i', $html)) {
                $problems[] = "{$label} contains page-shell tags; send body content only";
            }
            if (preg_match('/<\s*script\b/i', $html)) {
                $problems[] = "{$label} contains a <script> tag";
            }
            if (preg_match('/\sstyle\s*=/i', $html)) {
                $problems[] = "{$label} has an inline style attribute; use .c-* classes and var(--teal) instead";
            }
            if (!$allowFlagged) {
                foreach (self::FLAGGED as $pattern => $reason) {
                    if (preg_match($pattern, $html)) {
                        $problems[] = "{$label} contains {$reason}; remove it, or set allow_flagged after the owner confirms it";
                    }
                }
            }
        }

        $sectionsRu = self::countSections($ru);
        $sectionsUz = self::countSections($uz);
        if ($sectionsRu !== $sectionsUz) {
            $problems[] = "section count differs (RU {$sectionsRu}, UZ {$sectionsUz}); RU and UZ must have the same <!-- Section --> markers";
        }

        $phRu = self::placeholders($ru);
        $phUz = self::placeholders($uz);
        if ($phRu !== $phUz) {
            $problems[] = 'placeholders differ between RU and UZ (' . implode(', ', array_diff(array_merge($phRu, $phUz), array_intersect($phRu, $phUz))) . ')';
        }

        return $problems;
    }

    private static function countSections(string $html): int {
        return preg_match_all('/<!--\s*[^>]*?\s*-->/u', $html);
    }

    /** Sorted unique {{placeholder}} names, so RU and UZ can be compared. */
    private static function placeholders(string $html): array {
        preg_match_all('/\{\{\s*([^}]+?)\s*\}\}/u', $html, $m);
        $names = array_values(array_unique(array_map('trim', $m[1] ?? [])));
        sort($names);
        return $names;
    }
}
