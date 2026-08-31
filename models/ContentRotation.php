<?php
// path: ./models/ContentRotation.php 
// Replace the entire file with this

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
     * Get rotation by ID (for manual mode)
     * 06-01: $includeInactive allows pinning inactive variants shown in UI (views/admin/pages/edit.php:260).
     */
    public function getRotationById($rotationId, bool $includeInactive = false) {
        $sql = "SELECT * FROM content_rotations WHERE id = ?";
        if (!$includeInactive) $sql .= " AND is_active = 1";
        $sql .= " LIMIT 1";
        return $this->db->fetchOne($sql, [$rotationId]);
    }

    /**
     * Set manual rotation for a page
     * 06-02: snapshot revision before UPDATE so pin is undoable; 06-01: allow inactive pin.
     */
    public function setManualRotation($pageId, $rotationId) {
        require_once BASE_PATH . '/models/Page.php';
        $pageModel = new Page();
        
        // Verify rotation belongs to this page — allow inactive (06-01)
        $rotation = $this->getRotationById($rotationId, true);
        if (!$rotation || $rotation['page_id'] != $pageId) {
            return false;
        }
        // Warn but allow inactive pin — UI shows (Inactive) explicitly
        if (empty($rotation['is_active'])) {
            error_log("[ContentRotation] pinning inactive rotation {$rotationId} to page {$pageId}");
        }
        // 06-02: revision snapshot before mutation (never block the pin)
        try {
            if (!class_exists('PageRevision', false)) {
                require_once BASE_PATH . '/models/PageRevision.php';
            }
            $pageRow = $pageModel->getById($pageId);
            if ($pageRow && class_exists('PageRevision', true)) {
                PageRevision::createSnapshot((int)$pageId, $pageRow, ['selected_rotation_id']);
            }
        } catch (Throwable $e) {
            error_log('[ContentRotation] revision snapshot failed for setManualRotation page ' . $pageId . ': ' . $e->getMessage());
        }
        
        $sql = "UPDATE pages SET selected_rotation_id = ? WHERE id = ?";
        return $this->db->query($sql, [$rotationId, $pageId]);
    }

    /**
     * Clear manual rotation (back to auto)
     */
    public function clearManualRotation($pageId) {
        $sql = "UPDATE pages SET selected_rotation_id = NULL WHERE id = ?";
        return $this->db->query($sql, [$pageId]);
    }

    public function createDefaultFromPage($pageId, $month) {
        require_once BASE_PATH . '/models/Page.php';
        $pageModel = new Page();
        $page = $pageModel->getById($pageId);
        
        if (!$page) {
            return false;
        }

        $data = [
            'page_id' => $pageId,
            'title_ru' => $page['title_ru'],
            'title_uz' => $page['title_uz'],
            'content_ru' => $page['content_ru'],
            'content_uz' => $page['content_uz'],
            'description_ru' => $page['meta_description_ru'],
            'description_uz' => $page['meta_description_uz'],
            'active_month' => $month,
            'is_active' => 1,
            'meta_title_ru' => $page['meta_title_ru'],
            'meta_title_uz' => $page['meta_title_uz'],
            'meta_description_ru' => $page['meta_description_ru'],
            'meta_description_uz' => $page['meta_description_uz'],
            'meta_keywords_ru' => $page['meta_keywords_ru'],
            'meta_keywords_uz' => $page['meta_keywords_uz'],
            'og_title_ru' => $page['og_title_ru'],
            'og_title_uz' => $page['og_title_uz'],
            'og_description_ru' => $page['og_description_ru'],
            'og_description_uz' => $page['og_description_uz'],
            'og_image' => $page['og_image'],
            'jsonld_ru' => $page['jsonld_ru'],
            'jsonld_uz' => $page['jsonld_uz']
        ];

        return $this->create($data);
    }

    public function getCoverageStats($pageId) {
        // 06-03: covered = active months only; inactive rows no longer count as covered
        $rotations = $this->getByPageId($pageId);
        $activeCovered = [];
        $stats = [
            'total_months' => 12,
            'covered_months' => 0,
            'missing_months' => [],
            'active_months' => [],
            'inactive_months' => []
        ];
        
        foreach ($rotations as $r) {
            if ($r['is_active']) {
                $activeCovered[(int)$r['active_month']] = true;
                $stats['active_months'][] = (int)$r['active_month'];
            } else {
                $stats['inactive_months'][] = (int)$r['active_month'];
            }
        }
        
        for ($i = 1; $i <= 12; $i++) {
            if (isset($activeCovered[$i])) {
                $stats['covered_months']++;
            } else {
                $stats['missing_months'][] = $i;
            }
        }
        
        return $stats;
    }

    public function monthHasContent($pageId, $month, bool $activeOnly = true) {
        $sql = "SELECT COUNT(*) as count FROM content_rotations WHERE page_id = ? AND active_month = ?";
        if ($activeOnly) $sql .= " AND is_active = 1";
        $result = $this->db->fetchOne($sql, [$pageId, $month]);
        return $result['count'] > 0;
    }

    public function getPagesWithIncompleteRotation() {
        // 06-03: only active rotations count toward coverage
        $sql = "SELECT p.id, p.slug, p.title_ru, p.title_uz, 
                COUNT(DISTINCT cr_active.active_month) as covered_months
                FROM pages p
                LEFT JOIN content_rotations cr_active ON p.id = cr_active.page_id AND cr_active.is_active = 1
                WHERE p.enable_rotation = 1 AND p.is_published = 1
                GROUP BY p.id
                HAVING covered_months < 12
                ORDER BY covered_months ASC";
        return $this->db->fetchAll($sql);
    }

    public function cloneToMonth($sourceId, $targetMonth) {
        $source = $this->getById($sourceId);
        if (!$source) {
            return false;
        }

        // 06-04: active-only check; if target has only inactive row, overwrite it instead of blocking
        if ($this->monthHasContent($source['page_id'], $targetMonth, true)) {
            return false;
        }
        // If an inactive row exists for target month, replace it atomically
        $inactive = $this->db->fetchOne(
            "SELECT id FROM content_rotations WHERE page_id = ? AND active_month = ? AND is_active = 0 LIMIT 1",
            [$source['page_id'], $targetMonth]
        );
        if ($inactive) {
            $this->db->query("DELETE FROM content_rotations WHERE id = ?", [$inactive['id']]);
        }

        $data = [
            'page_id' => $source['page_id'],
            'title_ru' => $source['title_ru'],
            'title_uz' => $source['title_uz'],
            'content_ru' => $source['content_ru'],
            'content_uz' => $source['content_uz'],
            'description_ru' => $source['description_ru'],
            'description_uz' => $source['description_uz'],
            'active_month' => $targetMonth,
            'is_active' => 1,
            'meta_title_ru' => $source['meta_title_ru'],
            'meta_title_uz' => $source['meta_title_uz'],
            'meta_description_ru' => $source['meta_description_ru'],
            'meta_description_uz' => $source['meta_description_uz'],
            'meta_keywords_ru' => $source['meta_keywords_ru'],
            'meta_keywords_uz' => $source['meta_keywords_uz'],
            'og_title_ru' => $source['og_title_ru'],
            'og_title_uz' => $source['og_title_uz'],
            'og_description_ru' => $source['og_description_ru'],
            'og_description_uz' => $source['og_description_uz'],
            'og_image' => $source['og_image'],
            'jsonld_ru' => $source['jsonld_ru'],
            'jsonld_uz' => $source['jsonld_uz']
        ];

        return $this->create($data);
    }

    public function bulkUpdateStatus($ids, $isActive) {
        if (empty($ids)) return false;
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE content_rotations SET is_active = ? WHERE id IN ($placeholders)";
        
        $params = array_merge([$isActive], $ids);
        return $this->db->query($sql, $params);
    }

    public function create($data) {
        $sql = "INSERT INTO content_rotations (
                    page_id, title_ru, title_uz, content_ru, content_uz, 
                    description_ru, description_uz, active_month, is_active,
                    meta_title_ru, meta_title_uz, meta_description_ru, meta_description_uz,
                    meta_keywords_ru, meta_keywords_uz, og_title_ru, og_title_uz,
                    og_description_ru, og_description_uz, og_image, jsonld_ru, jsonld_uz
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $data['page_id'],
            $data['title_ru'] ?? null,
            $data['title_uz'] ?? null,
            $data['content_ru'] ?? '',
            $data['content_uz'] ?? '',
            $data['description_ru'] ?? null,
            $data['description_uz'] ?? null,
            $data['active_month'],
            $data['is_active'] ?? 1,
            $data['meta_title_ru'] ?? null,
            $data['meta_title_uz'] ?? null,
            $data['meta_description_ru'] ?? null,
            $data['meta_description_uz'] ?? null,
            $data['meta_keywords_ru'] ?? null,
            $data['meta_keywords_uz'] ?? null,
            $data['og_title_ru'] ?? null,
            $data['og_title_uz'] ?? null,
            $data['og_description_ru'] ?? null,
            $data['og_description_uz'] ?? null,
            $data['og_image'] ?? null,
            $data['jsonld_ru'] ?? null,
            $data['jsonld_uz'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }

    public function update($id, $data) {
        $sql = "UPDATE content_rotations SET 
                title_ru = ?, title_uz = ?,
                content_ru = ?, content_uz = ?, 
                description_ru = ?, description_uz = ?,
                active_month = ?, is_active = ?,
                meta_title_ru = ?, meta_title_uz = ?,
                meta_description_ru = ?, meta_description_uz = ?,
                meta_keywords_ru = ?, meta_keywords_uz = ?,
                og_title_ru = ?, og_title_uz = ?,
                og_description_ru = ?, og_description_uz = ?,
                og_image = ?, jsonld_ru = ?, jsonld_uz = ?
                WHERE id = ?";
        
        return $this->db->query($sql, [
            $data['title_ru'] ?? null,
            $data['title_uz'] ?? null,
            $data['content_ru'] ?? '',
            $data['content_uz'] ?? '',
            $data['description_ru'] ?? null,
            $data['description_uz'] ?? null,
            $data['active_month'],
            $data['is_active'] ?? 1,
            $data['meta_title_ru'] ?? null,
            $data['meta_title_uz'] ?? null,
            $data['meta_description_ru'] ?? null,
            $data['meta_description_uz'] ?? null,
            $data['meta_keywords_ru'] ?? null,
            $data['meta_keywords_uz'] ?? null,
            $data['og_title_ru'] ?? null,
            $data['og_title_uz'] ?? null,
            $data['og_description_ru'] ?? null,
            $data['og_description_uz'] ?? null,
            $data['og_image'] ?? null,
            $data['jsonld_ru'] ?? null,
            $data['jsonld_uz'] ?? null,
            $id
        ]);
    }

    public function delete($id) {
        $sql = "DELETE FROM content_rotations WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }

    public function bulkDelete($ids) {
        if (empty($ids)) return false;
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM content_rotations WHERE id IN ($placeholders)";
        return $this->db->query($sql, $ids);
    }

    public function getMonths() {
        return [
            1 => 'Yan',
            2 => 'Fev',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'May',
            6 => 'Iyn',
            7 => 'Iyl',
            8 => 'Avg',
            9 => 'Sen',
            10 => 'Okt',
            11 => 'Noy',
            12 => 'Dek'
        ];
    }

    public function getMonthNameRu($monthNum) {
        $months = [
            1 => 'Yan', 2 => 'Fev', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Iyn', 7 => 'Iyl', 8 => 'Avg',
            9 => 'Sen', 10 => 'Okt', 11 => 'Noy', 12 => 'Dek'
        ];
        return $months[$monthNum] ?? '';
    }
}