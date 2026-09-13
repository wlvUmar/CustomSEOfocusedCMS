<?php
// path: ./models/ComponentRevision.php
// Mirrors PageRevision pattern for ai_components.

class ComponentRevision {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public static function createSnapshot(int $componentId, array $row, array $changedFields = [], string $source = 'admin'): int {
        try {
            $db = Database::getInstance();
            $createdBy = $_SESSION['user_id'] ?? null;
            $createdByName = $_SESSION['username'] ?? null;
            $snapshotJson = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($snapshotJson === false) $snapshotJson = json_encode(['error'=>'encode failed','id'=>$row['id'] ?? $componentId]);
            $changedCsv = $changedFields ? implode(',', array_slice($changedFields, 0, 40)) : null;
            $slug = $row['slug'] ?? '';
            $db->query(
                "INSERT INTO component_revisions (component_id, slug, snapshot, changed_fields, source, created_by, created_by_name) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$componentId, $slug, $snapshotJson, $changedCsv, $source, $createdBy, $createdByName]
            );
            $id = (int)$db->lastInsertId();
            try { self::prune($componentId, 20); } catch (Throwable $e) {}
            return $id;
        } catch (Throwable $e) {
            error_log('[ComponentRevision] snapshot failed for ' . $componentId . ': ' . $e->getMessage());
            return 0;
        }
    }

    public static function prune(int $componentId, int $keep = 20): void {
        $db = Database::getInstance();
        $keep = max(5, min(100, $keep));
        $db->query(
            "DELETE FROM component_revisions WHERE component_id=? AND id NOT IN (SELECT id FROM (SELECT id FROM component_revisions WHERE component_id=? ORDER BY id DESC LIMIT $keep) t)",
            [$componentId, $componentId]
        );
    }

    public function getByComponentId(int $componentId, int $limit = 20): array {
        $limit = max(1, min(50, $limit));
        return $this->db->fetchAll(
            "SELECT id, component_id, slug, changed_fields, source, created_by, created_by_name, created_at, LENGTH(snapshot) AS snapshot_bytes FROM component_revisions WHERE component_id=? ORDER BY id DESC LIMIT $limit",
            [$componentId]
        );
    }

    public function getBySlug(string $slug, int $limit = 20): array {
        $limit = max(1, min(50, $limit));
        return $this->db->fetchAll(
            "SELECT id, component_id, slug, changed_fields, source, created_by, created_by_name, created_at FROM component_revisions WHERE slug=? ORDER BY id DESC LIMIT $limit",
            [strtolower(trim($slug))]
        );
    }

    public function getById(int $id): ?array {
        $row = $this->db->fetchOne("SELECT * FROM component_revisions WHERE id=?", [$id]);
        return $row ?: null;
    }

    public function restore(int $revisionId): array {
        $rev = $this->getById($revisionId);
        if (!$rev) throw new InvalidArgumentException('Revision not found: ' . $revisionId);
        $snapshot = json_decode($rev['snapshot'], true);
        if (!is_array($snapshot) || empty($snapshot['id'])) throw new RuntimeException('Revision snapshot corrupt');
        $componentId = (int)$snapshot['id'];
        $compModel = new Component();
        $current = $compModel->getById($componentId);
        if (!$current) throw new InvalidArgumentException('Component not found for revision: ' . $componentId);
        self::createSnapshot($componentId, $current, ['_restore_from_' . $revisionId], 'revision_restore');
        $allow = ['slug','category','title','description','html_demo','css_body','variants','tokens_used','status','sort'];
        $data = [];
        foreach ($allow as $k) if (array_key_exists($k, $snapshot)) $data[$k] = $snapshot[$k];
        // variants/tokens may be JSON strings — pass through
        $compModel->update($componentId, $data);
        $fresh = $compModel->getById($componentId);
        return $fresh ?: $snapshot;
    }
}
