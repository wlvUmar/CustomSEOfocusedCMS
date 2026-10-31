<?php
// path: ./models/ai/tools/PageCreateTools.php
// AI Studio page-creation tool: wraps Page::create with AGENTS.md guardrails.
// BUILD-only + guarded approval. RU/UZ parity enforced, body-only content.

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/core/PageTheme.php';

class PageCreateTools {

    public static function definitions(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_page',
                    'description' => 'Create a NEW CMS page (new slug). Requires slug + RU/UZ titles + RU/UZ body content (body only — hero/FAQ/widgets auto-render outside content_*). Meta titles/descriptions optional but must be paired RU+UZ. Parent via parent_slug or parent_id (omit for root). Theme auto-normalized; defaults to default. Draft by default (is_published=false) — set true only when user asked to publish. BUILD-only, auto-executes.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'slug' => ['type' => 'string', 'description' => 'New URL slug, lowercase a-z0-9-hyphens (e.g. "samsung-xolodilnik"). Must be unique; reserved: home,main,admin,api,articles.'],
                            'title_ru' => ['type' => 'string', 'description' => 'Page title RU (required).'],
                            'title_uz' => ['type' => 'string', 'description' => 'Page title UZ (required, same meaning as RU).'],
                            'content_ru' => ['type' => 'string', 'description' => 'Body HTML RU (required, body only — no <html>/<head>/<body>, no hero/header/footer shell). Use .c-* classes, no inline style="".'],
                            'content_uz' => ['type' => 'string', 'description' => 'Body HTML UZ (required, same structure/meaning as RU).'],
                            'meta_title_ru' => ['type' => 'string', 'description' => 'Meta title RU (optional, must pair with meta_title_uz).'],
                            'meta_title_uz' => ['type' => 'string', 'description' => 'Meta title UZ (optional, must pair with meta_title_ru).'],
                            'meta_description_ru' => ['type' => 'string', 'description' => 'Meta description RU (optional, must pair with meta_description_uz).'],
                            'meta_description_uz' => ['type' => 'string', 'description' => 'Meta description UZ (optional, must pair with meta_description_ru).'],
                            'parent_slug' => ['type' => 'string', 'description' => 'Parent page slug (alternative to parent_id). Omit for root.'],
                            'parent_id' => ['type' => 'integer', 'description' => 'Parent page id (alternative to parent_slug).'],
                            'theme' => ['type' => 'string', 'description' => 'Page theme key (default, screen, frost, wave, flame, breeze, rug, wood, fresh). Normalized automatically.'],
                            'is_published' => ['type' => 'boolean', 'description' => 'Publish immediately (default false = draft).'],
                        ],
                        'required' => ['slug', 'title_ru', 'title_uz', 'content_ru', 'content_uz'],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        if ($name === 'create_page') return self::createPage($args);
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    private static function sanitizeSlug($slug): string {
        $slug = strtolower(trim((string)$slug));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug === '') throw new InvalidArgumentException('Invalid slug: cannot be empty (use a-z0-9-hyphens, e.g. "samsung-xolodilnik").');
        if (in_array($slug, ['home', 'main', 'admin', 'api', 'articles'], true)) {
            throw new InvalidArgumentException("Slug '{$slug}' is reserved.");
        }
        if (mb_strlen($slug) > 120) throw new InvalidArgumentException('Slug too long (max 120 chars).');
        return $slug;
    }

    private static function sanitizeBody(string $html): string {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html);
        $html = preg_replace('/<object\b[^>]*>.*?<\/object>/is', '', $html);
        $html = preg_replace('/<embed\b[^>]*>/i', '', $html);
        $html = preg_replace('/<link\b[^>]*>/i', '', $html);
        $html = preg_replace('/\b(javascript|vbscript|data\s*:\s*text\/html)\s*:/i', '', $html);
        $html = preg_replace('/@import\s+/i', '', $html);
        $html = preg_replace('/expression\s*\(/i', '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $html);
        $html = preg_replace('/url\s*\(\s*["\']?\s*javascript:[^)]*\)/i', 'url(#)', $html);
        return $html;
    }

    private static function createPage(array $args): array {
        $slug = self::sanitizeSlug($args['slug'] ?? '');
        $titleRu = trim((string)($args['title_ru'] ?? ''));
        $titleUz = trim((string)($args['title_uz'] ?? ''));
        $contentRu = (string)($args['content_ru'] ?? '');
        $contentUz = (string)($args['content_uz'] ?? '');
        if ($titleRu === '' || $titleUz === '') throw new InvalidArgumentException('title_ru and title_uz are both required (RU/UZ parity).');
        if (trim($contentRu) === '' || trim($contentUz) === '') throw new InvalidArgumentException('content_ru and content_uz are both required (RU/UZ parity).');

        // Body-only: reject full-document shells.
        foreach (['content_ru' => $contentRu, 'content_uz' => $contentUz] as $field => $html) {
            if (preg_match('/<(html|head|body)[\s>]/i', $html)) {
                throw new InvalidArgumentException("{$field} must be body only — remove <html>/<head>/<body> shell (hero/header/footer auto-render outside content_*).");
            }
        }
        if (mb_strlen($contentRu) > 200000 || mb_strlen($contentUz) > 200000) {
            throw new InvalidArgumentException('content too large (max 200000 chars per language) — split or shorten.');
        }

        // Meta parity: optional, but paired.
        $metaTitleRu = trim((string)($args['meta_title_ru'] ?? ''));
        $metaTitleUz = trim((string)($args['meta_title_uz'] ?? ''));
        $metaDescRu = trim((string)($args['meta_description_ru'] ?? ''));
        $metaDescUz = trim((string)($args['meta_description_uz'] ?? ''));
        if (($metaTitleRu !== '') !== ($metaTitleUz !== '')) throw new InvalidArgumentException('meta_title_ru and meta_title_uz must be provided together (RU/UZ parity).');
        if (($metaDescRu !== '') !== ($metaDescUz !== '')) throw new InvalidArgumentException('meta_description_ru and meta_description_uz must be provided together (RU/UZ parity).');

        $pageModel = new Page();

        // Parent resolution (optional).
        $parentId = null;
        if (!empty($args['parent_id'])) {
            $parent = $pageModel->getById((int)$args['parent_id']);
            if (!$parent) throw new InvalidArgumentException("Parent page id {$args['parent_id']} not found. Call list_pages to discover.");
            $parentId = (int)$parent['id'];
        } elseif (!empty($args['parent_slug'])) {
            $parent = $pageModel->getBySlug(trim((string)$args['parent_slug']), true);
            if (!$parent) throw new InvalidArgumentException("Parent slug '{$args['parent_slug']}' not found. Call list_pages to discover.");
            $parentId = (int)$parent['id'];
        }

        $theme = PageTheme::normalize(trim((string)($args['theme'] ?? PageTheme::BASE)));
        $isPublished = filter_var($args['is_published'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $contentRu = self::sanitizeBody($contentRu);
        $contentUz = self::sanitizeBody($contentUz);

        $data = [
            'slug' => $slug,
            'title_ru' => $titleRu,
            'title_uz' => $titleUz,
            'content_ru' => $contentRu,
            'content_uz' => $contentUz,
            'meta_title_ru' => $metaTitleRu !== '' ? $metaTitleRu : null,
            'meta_title_uz' => $metaTitleUz !== '' ? $metaTitleUz : null,
            'meta_description_ru' => $metaDescRu !== '' ? $metaDescRu : null,
            'meta_description_uz' => $metaDescUz !== '' ? $metaDescUz : null,
            'is_published' => $isPublished ? 1 : 0,
            'parent_id' => $parentId,
            'theme' => $theme,
        ];

        // Slug uniqueness fails closed with a clear message (Page::assertSlugUnique).
        try {
            $id = $pageModel->create($data);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage() . ' Call list_pages to discover taken slugs.');
        }

        // Best-effort initial revision snapshot for undoability (Page::create does not snapshot; update() does).
        try {
            if (!class_exists('PageRevision', false)) require_once BASE_PATH . '/models/PageRevision.php';
            if (class_exists('PageRevision', true)) {
                $created = $pageModel->getById($id);
                if ($created) PageRevision::createSnapshot((int)$id, $created, ['create'], 'ai');
            }
        } catch (Throwable $e) {}

        $warnings = [];
        if (stripos($contentRu, 'style="') !== false || stripos($contentUz, 'style="') !== false) {
            $warnings[] = 'Inline style="" detected — prefer .c-* classes + var(--teal) per AGENTS.md.';
        }
        $h1 = 0;
        if (preg_match_all('/<h1[^>]*>/i', $contentRu . $contentUz, $m)) $h1 = count($m[0]);
        if ($h1 > 2) $warnings[] = 'Multiple <h1> in body content — the H1 renders from title_*; keep body headings h2→h3 sequential.';

        $ret = [
            'ok' => true,
            'page_id' => (int)$id,
            'slug' => $slug,
            'is_published' => $isPublished ? 1 : 0,
            'theme' => $theme,
            'parent_id' => $parentId,
            'title_chars_ru' => mb_strlen($titleRu),
            'title_chars_uz' => mb_strlen($titleUz),
            'content_chars_ru' => mb_strlen($contentRu),
            'content_chars_uz' => mb_strlen($contentUz),
            'note' => $isPublished ? 'Page created and published.' : 'Page created as draft (is_published=0). Publish from /admin/pages or set is_published=true if the user asked.',
        ];
        if (!empty($warnings)) $ret['warnings'] = $warnings;
        return $ret;
    }
}
