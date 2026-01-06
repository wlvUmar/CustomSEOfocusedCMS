<?php

class PageMedia {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Attach media to a page
     */
    public function attachMedia($pageId, $mediaId, $data = []) {
        $sql = "INSERT INTO page_media 
                (page_id, media_id, section, position, alt_text_ru, alt_text_uz, 
                 caption_ru, caption_uz, width, alignment, css_class, lazy_load) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                position = VALUES(position),
                alt_text_ru = VALUES(alt_text_ru),
                alt_text_uz = VALUES(alt_text_uz),
                caption_ru = VALUES(caption_ru),
                caption_uz = VALUES(caption_uz),
                width = VALUES(width),
                alignment = VALUES(alignment),
                css_class = VALUES(css_class),
                lazy_load = VALUES(lazy_load)";
        
        $this->db->query($sql, [
            $pageId,
            $mediaId,
            $data['section'] ?? 'content',
            $data['position'] ?? 0,
            $data['alt_text_ru'] ?? null,
            $data['alt_text_uz'] ?? null,
            $data['caption_ru'] ?? null,
            $data['caption_uz'] ?? null,
            $data['width'] ?? null,
            $data['alignment'] ?? 'center',
            $data['css_class'] ?? null,
            $data['lazy_load'] ?? 1
        ]);

        // Update usage count
        $this->updateMediaUsageCount($mediaId);
        
        return $this->db->lastInsertId();
    }

    /**
     * Detach media from a page
     */
    public function detachMedia($pageId, $mediaId, $section = null) {
        if ($section) {
            $sql = "DELETE FROM page_media WHERE page_id = ? AND media_id = ? AND section = ?";
            $result = $this->db->query($sql, [$pageId, $mediaId, $section]);
        } else {
            $sql = "DELETE FROM page_media WHERE page_id = ? AND media_id = ?";
            $result = $this->db->query($sql, [$pageId, $mediaId]);
        }
        
        $this->updateMediaUsageCount($mediaId);
        return $result;
    }

    /**
     * Get all media for a page
     */
    public function getPageMedia($pageId, $section = null) {
        if ($section) {
            $sql = "SELECT pm.*, m.filename, m.original_name, m.file_size, m.mime_type
                    FROM page_media pm
                    JOIN media m ON pm.media_id = m.id
                    WHERE pm.page_id = ? AND pm.section = ?
                    ORDER BY pm.position ASC, pm.id ASC";
            return $this->db->fetchAll($sql, [$pageId, $section]);
        } else {
            $sql = "SELECT pm.*, m.filename, m.original_name, m.file_size, m.mime_type
                    FROM page_media pm
                    JOIN media m ON pm.media_id = m.id
                    WHERE pm.page_id = ?
                    ORDER BY pm.section ASC, pm.position ASC, pm.id ASC";
            return $this->db->fetchAll($sql, [$pageId]);
        }
    }

    /**
     * Get all pages using a media
     */
    public function getMediaPages($mediaId) {
        $sql = "SELECT p.*, pm.section, pm.position
                FROM page_media pm
                JOIN pages p ON pm.page_id = p.id
                WHERE pm.media_id = ?
                ORDER BY p.title_ru ASC";
        return $this->db->fetchAll($sql, [$mediaId]);
    }

    /**
     * Check if media is used on a page
     */
    public function isMediaUsed($mediaId, $pageId = null) {
        if ($pageId) {
            $sql = "SELECT COUNT(*) as count FROM page_media WHERE media_id = ? AND page_id = ?";
            $result = $this->db->fetchOne($sql, [$mediaId, $pageId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM page_media WHERE media_id = ?";
            $result = $this->db->fetchOne($sql, [$mediaId]);
        }
        return $result['count'] > 0;
    }

    /**
     * Update media positions within a section
     */
    public function updatePositions($pageId, $section, $orderedMediaIds) {
        foreach ($orderedMediaIds as $position => $mediaId) {
            $sql = "UPDATE page_media SET position = ? 
                    WHERE page_id = ? AND media_id = ? AND section = ?";
            $this->db->query($sql, [$position, $pageId, $mediaId, $section]);
        }
    }

    /**
     * Update media usage count
     */
    private function updateMediaUsageCount($mediaId) {
        $sql = "UPDATE media 
                SET usage_count = (SELECT COUNT(DISTINCT page_id) FROM page_media WHERE media_id = ?),
                    last_used = NOW()
                WHERE id = ?";
        $this->db->query($sql, [$mediaId, $mediaId]);
    }

    /**
     * Get media usage statistics
     */
    public function getUsageStats($mediaId) {
        $sql = "SELECT * FROM v_media_usage WHERE id = ?";
        return $this->db->fetchOne($sql, [$mediaId]);
    }

    /**
     * Get all unused media
     */
    public function getUnusedMedia() {
        $sql = "SELECT m.* FROM media m
                LEFT JOIN page_media pm ON m.id = pm.media_id
                WHERE pm.id IS NULL
                ORDER BY m.uploaded_at DESC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Bulk attach media to multiple pages
     */
    public function bulkAttach($mediaId, $pageIds, $section = 'content') {
        $this->db->getConnection()->beginTransaction();
        try {
            foreach ($pageIds as $pageId) {
                $this->attachMedia($pageId, $mediaId, ['section' => $section]);
            }
            $this->db->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            $this->db->getConnection()->rollBack();
            error_log("Bulk attach error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get media by section for a page
     */
    public function getMediaBySection($pageId) {
        $sql = "SELECT pm.section, COUNT(*) as count
                FROM page_media pm
                WHERE pm.page_id = ?
                GROUP BY pm.section";
        $results = $this->db->fetchAll($sql, [$pageId]);
        
        $sections = [];
        foreach ($results as $row) {
            $sections[$row['section']] = $row['count'];
        }
        return $sections;
    }
}
