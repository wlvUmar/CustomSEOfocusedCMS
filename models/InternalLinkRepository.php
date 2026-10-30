<?php
// models/InternalLinkRepository.php
class InternalLinkRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAllPagesWithLinkStats() {
        $sql = "SELECT p.id, p.slug, p.title_ru, p.title_uz, p.show_link_widget, p.parent_id,
                       (SELECT COUNT(*) FROM page_link_widgets WHERE page_id = p.id) as outgoing_links,
                       (SELECT COUNT(*) FROM page_link_widgets WHERE link_to_page_id = p.id) as incoming_links
                FROM pages p
                WHERE p.is_published = 1
                ORDER BY p.title_ru ASC";
        return $this->db->fetchAll($sql);
    }

    public function getLinkMatrix() {
        $sql = "SELECT lw.page_id, lw.link_to_page_id,
                       p1.slug as from_slug, p2.slug as to_slug
                FROM page_link_widgets lw
                JOIN pages p1 ON lw.page_id = p1.id
                JOIN pages p2 ON lw.link_to_page_id = p2.id
                WHERE lw.is_active = 1";
        return $this->db->fetchAll($sql);
    }

    public function getNetworkStats() {
        $totalPages = $this->db->fetchOne("SELECT COUNT(*) as count FROM pages WHERE is_published = 1")['count'];
        $totalLinks = $this->db->fetchOne("SELECT COUNT(*) as count FROM page_link_widgets WHERE is_active = 1")['count'];
        $maxPossibleLinks = $totalPages * ($totalPages - 1);
        $density = $maxPossibleLinks > 0 ? ($totalLinks / $maxPossibleLinks) * 100 : 0;

        $orphanPages = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM pages p
             WHERE p.is_published = 1
             AND NOT EXISTS (SELECT 1 FROM page_link_widgets WHERE page_id = p.id OR link_to_page_id = p.id)"
        )['count'];

        return [
            'total_pages' => $totalPages,
            'total_links' => $totalLinks,
            'max_possible_links' => $maxPossibleLinks,
            'density_percentage' => round($density, 2),
            'orphan_pages' => $orphanPages,
            'avg_links_per_page' => $totalPages > 0 ? round($totalLinks / $totalPages, 1) : 0
        ];
    }

    public function getIncomingLinks($pageId) {
        $sql = "SELECT lw.*, p.slug, p.title_ru, p.title_uz
                FROM page_link_widgets lw
                JOIN pages p ON lw.page_id = p.id
                WHERE lw.link_to_page_id = ? AND lw.is_active = 1
                ORDER BY p.title_ru ASC";
        return $this->db->fetchAll($sql, [$pageId]);
    }

    public function findRelatedPages($excludeId, $searchTerm, $limit = 5) {
        $limit = max(1, min(50, (int)$limit));
        $sql = "SELECT id FROM pages
                WHERE is_published = 1
                AND id != ?
                AND (title_ru LIKE ? OR content_ru LIKE ?)
                LIMIT ?";

        $result = $this->db->fetchAll($sql, [$excludeId, $searchTerm, $searchTerm, $limit]);

        return array_column($result, 'id');
    }

    /**
     * Keyword-OR related search: each keyword is an independent LIKE, joined by OR.
     * Replaces the old single '%kw1%kw2%kw3%' LIKE which required keywords in order and almost never matched.
     */
    public function findRelatedByKeywords($excludeId, array $keywords, $limit = 5, array $excludeIds = []) {
        $keywords = array_values(array_unique(array_filter(array_map(function ($k) {
            $k = trim((string)$k);
            return mb_strlen($k) >= 3 ? $k : '';
        }, $keywords))));
        $keywords = array_slice($keywords, 0, 6);
        $limit = max(1, min(50, (int)$limit));
        if (empty($keywords)) return [];

        $excludeIds = array_values(array_unique(array_map('intval', $excludeIds)));
        $conds = [];
        $params = [];
        foreach ($keywords as $kw) {
            $like = '%' . $kw . '%';
            $conds[] = "(title_ru LIKE ? OR content_ru LIKE ? OR meta_keywords_ru LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sql = "SELECT id FROM pages WHERE is_published = 1 AND id != ?";
        $params = array_merge([$excludeId], $params);
        if (!empty($excludeIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $sql .= " AND id NOT IN ($placeholders)";
            $params = array_merge($params, $excludeIds);
        }
        $sql .= " AND (" . implode(' OR ', $conds) . ") LIMIT " . $limit;

        $result = $this->db->fetchAll($sql, $params);
        return array_column($result, 'id');
    }

    public function getPopularPages($limit = 5) {
        $limit = max(1, min(100, (int)$limit));
        $sql = "SELECT p.id, p.slug, p.title_ru, COALESCE(SUM(a.visits), 0) as view_count
                FROM pages p
                LEFT JOIN analytics a ON p.slug = a.page_slug
                WHERE p.is_published = 1
                GROUP BY p.id
                ORDER BY view_count DESC
                LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }

    public function getHierarchyStats() {
        $totalPages = $this->db->fetchOne("SELECT COUNT(*) as count FROM pages WHERE is_published = 1")['count'];
        $rootPages = $this->db->fetchOne("SELECT COUNT(*) as count FROM pages WHERE is_published = 1 AND (parent_id IS NULL OR parent_id = '' OR parent_id = 0)")['count'];
        $pagesWithChildren = $this->db->fetchOne("SELECT COUNT(DISTINCT parent_id) as count FROM pages WHERE parent_id IS NOT NULL AND parent_id != '' AND parent_id != 0")['count'];

        return [
            'total_root_pages' => $rootPages,
            'total_parent_pages' => $pagesWithChildren,
            'total_child_pages' => $totalPages - $rootPages,
            'hierarchy_depth' => $this->getMaxDepth()
        ];
    }

    public function getMaxDepth() {
        $result = $this->db->fetchOne("SELECT MAX(depth) as max_depth FROM pages");
        return $result['max_depth'] ?? 0;
    }
}
