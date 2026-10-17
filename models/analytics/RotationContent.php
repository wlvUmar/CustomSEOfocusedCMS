<?php
// path: ./models/analytics/RotationContent.php
// Current-month rotation content read extracted from core/helpers.php.
// Owns SQL - returns plain string, no HTML.

class RotationContent {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getCurrentMonthContent($pageId, $lang) {
        $currentMonth = date('n');

        $sql = "SELECT content_ru, content_uz FROM content_rotations
                WHERE page_id = ? AND active_month = ? AND is_active = 1
                LIMIT 1";

        $rotation = $this->db->fetchOne($sql, [$pageId, $currentMonth]);

        return $rotation ? $rotation["content_$lang"] : null;
    }
}
