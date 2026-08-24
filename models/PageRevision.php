<?php
// path: ./models/PageRevision.php
// Lightweight revision history for `pages` — one JSON snapshot per update.
// Never throws to the caller; failures are logged and ignored so page saves keep working.

class PageRevision {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Snapshot the row BEFORE it is mutated. Call this at the top of Page::update().
     * $changedFields = array_keys($data) — what the caller attempted to change.
     */
    public static function createSnapshot(int $pageId, array $row, array $changedFields = [], string $source = 'unknown'): int {
        try {
            $db = Database::getInstance();
            $createdBy = $_SESSION['user_id'] ?? null;
            $createdByName = $_SESSION['username'] ?? null;

            // Auto-detect source if caller passed 'unknown'
            if ($source === 'unknown') {
                $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
                $isAi = false;
                foreach ($bt as $frame) {
                    $cls = $frame['class'] ?? '';
                    $fn  = $frame['function'] ?? '';
                    if ($cls === 'PageTools' || $cls === 'AiToolRegistry' || $cls === 'AiStudioController') {
                        $isAi = true; break;
                    }
                    if (strpos($fn, 'ai') !== false) { /* fallback */ }
                }
                $source = $isAi ? 'ai' : 'admin';
            }

            // created_by may be null for CLI / unauthenticated — fine.
            $snapshotJson = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($snapshotJson === false) {
                $snapshotJson = json_encode(['error' => 'snapshot encode failed', 'id' => $row['id'] ?? $pageId]);
            }
            $changedCsv = $changedFields ? implode(',', array_slice($changedFields, 0, 40)) : null;

            $db->query(
                "INSERT INTO page_revisions (page_id, snapshot, changed_fields, source, created_by, created_by_name) VALUES (?, ?, ?, ?, ?, ?)",
                [$pageId, $snapshotJson, $changedCsv, $source, $createdBy, $createdByName]
            );
            $id = (int)$db->lastInsertId();
            // Prune asynchronously — never block the save on prune failure.
            try { self::prune($pageId, 20); } catch (Throwable $e) {}
            return $id;
        } catch (Throwable $e) {
            error_log('[PageRevision] snapshot failed for page ' . $pageId . ': ' . $e->getMessage());
            return 0;
        }
    }

    public static function prune(int $pageId, int $keep = 20): void {
        $db = Database::getInstance();
        $keep = max(5, min(100, $keep));
        // Keep the newest $keep rows per page, delete older ones.
        $db->query(
            "DELETE FROM page_revisions
             WHERE page_id = ?
               AND id NOT IN (
                 SELECT id FROM (
                   SELECT id FROM page_revisions WHERE page_id = ? ORDER BY id DESC LIMIT $keep
                 ) t
               )",
            [$pageId, $pageId]
        );
    }

    public function getByPageId(int $pageId, int $limit = 20): array {
        $limit = max(1, min(50, $limit));
        return $this->db->fetchAll(
            "SELECT id, page_id, changed_fields, source, created_by, created_by_name, created_at,
                    CHAR_LENGTH(snapshot) AS snapshot_chars
             FROM page_revisions WHERE page_id = ? ORDER BY id DESC LIMIT $limit",
            [$pageId]
        );
    }

    public function getById(int $id): ?array {
        $row = $this->db->fetchOne("SELECT * FROM page_revisions WHERE id = ?", [$id]);
        return $row ?: null;
    }

    public function getSnapshotData(int $id): ?array {
        $row = $this->getById($id);
        if (!$row) return null;
        $data = json_decode($row['snapshot'], true);
        return is_array($data) ? $data : null;
    }

    /**
     * Restore page $pageId to the state captured in revision $revisionId.
     * Creates a new revision of the CURRENT state before overwriting, so this is undoable.
     * Returns the page row after restore.
     */
    public function restore(int $revisionId): array {
        $rev = $this->getById($revisionId);
        if (!$rev) throw new InvalidArgumentException('Revision not found: ' . $revisionId);
        $snapshot = json_decode($rev['snapshot'], true);
        if (!is_array($snapshot) || empty($snapshot['id'])) {
            throw new RuntimeException('Revision snapshot is corrupt');
        }
        $pageId = (int)$snapshot['id'];
        // Snapshot current state before clobbering it — source = revision_restore
        $pageModel = new Page();
        $current = $pageModel->getById($pageId);
        if (!$current) throw new InvalidArgumentException('Page not found for revision: ' . $pageId);
        self::createSnapshot($pageId, $current, ['_restore_from_' . $revisionId], 'revision_restore');

        // Build a full restore payload — only columns that belong to `pages`.
        // Keep id/created_at out of the SET, but restore everything else.
        $allow = ['slug','title_ru','title_uz','content_ru','content_uz','meta_title_ru','meta_title_uz','meta_keywords_ru','meta_keywords_uz','meta_description_ru','meta_description_uz','og_title_ru','og_title_uz','og_description_ru','og_description_uz','og_image','canonical_url','jsonld_ru','jsonld_uz','is_published','enable_rotation','rotation_mode','selected_rotation_id','sort_order','parent_id','show_link_widget','widget_title_ru','widget_title_uz'];
        $data = [];
        foreach ($allow as $k) {
            if (array_key_exists($k, $snapshot)) $data[$k] = $snapshot[$k];
        }
        // Page::update now does merge-safe semantics, so passing a full snapshot is fine.
        $pageModel->update($pageId, $data);
        $fresh = $pageModel->getById($pageId);
        return $fresh ?: $snapshot;
    }

    /** Diff two revision snapshots (or a snapshot vs current) — for AI transparency. */
    public static function diff(array $a, array $b): array {
        $keys = array_unique(array_merge(array_keys($a), array_keys($b)));
        $out = [];
        foreach ($keys as $k) {
            $av = $a[$k] ?? null;
            $bv = $b[$k] ?? null;
            if ($av !== $bv) $out[] = ['field' => $k, 'before' => $av, 'after' => $bv];
        }
        return $out;
    }
}
