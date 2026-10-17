<?php
// path: ./models/analytics/MediaPlaceholderStore.php
// Media placeholder reads extracted from core/helpers.php processMediaPlaceholders.
// Owns SQL - returns raw DB rows only, no HTML.

class MediaPlaceholderStore {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function fetchSingle($pageId, $mediaId) {
        $sql = "SELECT m.*, pm.alt_text_ru, pm.alt_text_uz, pm.caption_ru, pm.caption_uz,
                       pm.width, pm.alignment, pm.css_class, pm.lazy_load
                FROM media m
                LEFT JOIN page_media pm ON m.id = pm.media_id AND pm.page_id = ?
                WHERE m.id = ?
                LIMIT 1";

        return $this->db->fetchOne($sql, [$pageId, $mediaId]);
    }

    public function fetchSection($pageId, $section) {
        $sql = "SELECT m.*, pm.alt_text_ru, pm.alt_text_uz, pm.caption_ru, pm.caption_uz,
                       pm.width, pm.alignment, pm.css_class, pm.lazy_load, pm.position
                FROM page_media pm
                JOIN media m ON pm.media_id = m.id
                WHERE pm.page_id = ? AND pm.section = ?
                ORDER BY pm.position ASC, pm.id ASC";

        return $this->db->fetchAll($sql, [$pageId, $section]);
    }
}
