<?php
// models/LinkWidget.php
class LinkWidget {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get all links for a page
    public function getLinksForPage($pageId) {
        $sql = "SELECT lw.*, p.slug, p.title_ru, p.title_uz 
                FROM page_link_widgets lw
                JOIN pages p ON lw.link_to_page_id = p.id
                WHERE lw.page_id = ? AND lw.is_active = 1 AND p.is_published = 1
                ORDER BY lw.position ASC";
        return $this->db->fetchAll($sql, [$pageId]);
    }

    // Add link to page
    public function addLink($pageId, $linkToPageId) {
        // Get next position
        $maxPos = $this->db->fetchOne(
            "SELECT MAX(position) as max_pos FROM page_link_widgets WHERE page_id = ?", 
            [$pageId]
        );
        $position = ($maxPos['max_pos'] ?? -1) + 1;

        $sql = "INSERT INTO page_link_widgets (page_id, link_to_page_id, position) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE is_active = 1, position = ?";
        
        $this->db->query($sql, [$pageId, $linkToPageId, $position, $position]);
        return $this->db->lastInsertId();
    }

    // Remove link
    public function removeLink($pageId, $linkToPageId) {
        $sql = "DELETE FROM page_link_widgets WHERE page_id = ? AND link_to_page_id = ?";
        return $this->db->query($sql, [$pageId, $linkToPageId]);
    }

    // Reorder links
    public function updatePositions($pageId, $linkIds) {
        foreach ($linkIds as $position => $linkId) {
            $sql = "UPDATE page_link_widgets SET position = ? 
                    WHERE id = ? AND page_id = ?";
            $this->db->query($sql, [$position, $linkId, $pageId]);
        }
        return true;
    }

    // Toggle widget visibility
    public function toggleWidget($pageId, $show) {
        $sql = "UPDATE pages SET show_link_widget = ? WHERE id = ?";
        return $this->db->query($sql, [$show ? 1 : 0, $pageId]);
    }

    // Get available pages to link to (excluding current page and already linked)
    public function getAvailablePages($pageId) {
        $sql = "SELECT p.id, p.slug, p.title_ru, p.title_uz 
                FROM pages p
                WHERE p.is_published = 1 
                  AND p.id != ?
                  AND p.id NOT IN (
                    SELECT link_to_page_id FROM page_link_widgets 
                    WHERE page_id = ? AND is_active = 1
                  )
                ORDER BY p.title_ru ASC";
        return $this->db->fetchAll($sql, [$pageId, $pageId]);
    }
}