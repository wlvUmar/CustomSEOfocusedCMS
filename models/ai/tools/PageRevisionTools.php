<?php
// path: ./models/ai/tools/PageRevisionTools.php
// Page revision list/get/restore. Split from PageTools (hygiene P1).

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/PageRevision.php';
require_once BASE_PATH . '/models/ai/tools/PageReader.php';

class PageRevisionTools {

    public static function definitions(): array {
        return [
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
        ];
    }

    public static function handle(string $name, array $args): array {
        switch ($name) {
            case 'list_page_revisions':
                return self::listRevisions($args);
            case 'get_page_revision':
                return self::getRevision($args);
            case 'restore_page_revision':
                return self::restoreRevision($args);
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
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

    private static function clipRow(array $row, array $limits): array {
        return PageReader::clipRow($row, $limits);
    }
}
