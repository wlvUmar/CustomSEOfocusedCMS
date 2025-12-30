<?php
// path: ./models/ContentRotation.php

class ContentRotation {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getByPageId($pageId) {
        $sql = "SELECT * FROM content_rotations WHERE page_id = ? ORDER BY active_month ASC";
        return $this->db->fetchAll($sql, [$pageId]);
    }

    public function getById($id) {
        $sql = "SELECT * FROM content_rotations WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    public function getCurrentMonth($pageId) {
        $month = date('n');
        $sql = "SELECT * FROM content_rotations WHERE page_id = ? AND active_month = ? AND is_active = 1 LIMIT 1";
        return $this->db->fetchOne($sql, [$pageId, $month]);
    }

    /**
     * Get rotation coverage stats for a page
     * Returns which months have content and which are missing
     */
    public function getCoverageStats($pageId) {
        $rotations = $this->getByPageId($pageId);
        $coverage = array_fill(1, 12, false);
        $stats = [
            'total_months' => 12,
            'covered_months' => 0,
            'missing_months' => [],
            'active_months' => [],
            'inactive_months' => []
        ];
        
        foreach ($rotations as $r) {
            $coverage[$r['active_month']] = true;
            if ($r['is_active']) {
                $stats['active_months'][] = $r['active_month'];
            } else {
                $stats['inactive_months'][] = $r['active_month'];
            }
        }
        
        for ($i = 1; $i <= 12; $i++) {
            if ($coverage[$i]) {
                $stats['covered_months']++;
            } else {
                $stats['missing_months'][] = $i;
            }
        }
        
        return $stats;
    }

    /**
     * Check if a month already has content for this page
     */
    public function monthHasContent($pageId, $month) {
        $sql = "SELECT COUNT(*) as count FROM content_rotations WHERE page_id = ? AND active_month = ?";
        $result = $this->db->fetchOne($sql, [$pageId, $month]);
        return $result['count'] > 0;
    }

    /**
     * Get all pages with rotation enabled that are missing content for specific months
     */
    public function getPagesWithIncompleteRotation() {
        $sql = "SELECT p.id, p.slug, p.title_ru, p.title_uz, 
                COUNT(DISTINCT cr.active_month) as covered_months
                FROM pages p
                LEFT JOIN content_rotations cr ON p.id = cr.page_id
                WHERE p.enable_rotation = 1 AND p.is_published = 1
                GROUP BY p.id
                HAVING covered_months < 12
                ORDER BY covered_months ASC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Clone content from one month to another for the same page
     */
    public function cloneToMonth($sourceId, $targetMonth) {
        $source = $this->getById($sourceId);
        if (!$source) {
            return false;
        }

        // Check if target month already exists
        if ($this->monthHasContent($source['page_id'], $targetMonth)) {
            return false;
        }

        $data = [
            'page_id' => $source['page_id'],
            'content_ru' => $source['content_ru'],
            'content_uz' => $source['content_uz'],
            'active_month' => $targetMonth,
            'is_active' => 1
        ];

        return $this->create($data);
    }

    /**
     * Bulk activate/deactivate rotations
     */
    public function bulkUpdateStatus($ids, $isActive) {
        if (empty($ids)) return false;
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE content_rotations SET is_active = ? WHERE id IN ($placeholders)";
        
        $params = array_merge([$isActive], $ids);
        return $this->db->query($sql, $params);
    }

    public function create($data) {
        $sql = "INSERT INTO content_rotations (page_id, content_ru, content_uz, active_month, is_active) 
                VALUES (?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $data['page_id'],
            $data['content_ru'],
            $data['content_uz'],
            $data['active_month'],
            $data['is_active'] ?? 1
        ]);
        
        return $this->db->lastInsertId();
    }

    public function update($id, $data) {
        $sql = "UPDATE content_rotations SET content_ru = ?, content_uz = ?, 
                active_month = ?, is_active = ? WHERE id = ?";
        
        return $this->db->query($sql, [
            $data['content_ru'],
            $data['content_uz'],
            $data['active_month'],
            $data['is_active'] ?? 1,
            $id
        ]);
    }

    public function delete($id) {
        $sql = "DELETE FROM content_rotations WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }

    /**
     * Delete multiple rotations at once
     */
    public function bulkDelete($ids) {
        if (empty($ids)) return false;
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM content_rotations WHERE id IN ($placeholders)";
        return $this->db->query($sql, $ids);
    }

    public function getMonths() {
        return [
            1 => 'Январь / Yanvar',
            2 => 'Февраль / Fevral',
            3 => 'Март / Mart',
            4 => 'Апрель / Aprel',
            5 => 'Май / May',
            6 => 'Июнь / Iyun',
            7 => 'Июль / Iyul',
            8 => 'Август / Avgust',
            9 => 'Сентябрь / Sentyabr',
            10 => 'Октябрь / Oktyabr',
            11 => 'Ноябрь / Noyabr',
            12 => 'Декабрь / Dekabr'
        ];
    }

    /**
     * Get month name in Russian
     */
    public function getMonthNameRu($monthNum) {
        $months = [
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
            5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
            9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
        ];
        return $months[$monthNum] ?? '';
    }
}