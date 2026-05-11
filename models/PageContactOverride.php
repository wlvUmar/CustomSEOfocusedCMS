<?php

class PageContactOverride {
    private $db;
    private static $tableAvailable = null;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getByPageId($pageId) {
        if (!$this->hasTable()) {
            return null;
        }

        $sql = "SELECT * FROM page_contact_overrides WHERE page_id = ? LIMIT 1";
        return $this->db->fetchOne($sql, [(int)$pageId]) ?: null;
    }

    private function hasTable() {
        if (self::$tableAvailable !== null) {
            return self::$tableAvailable;
        }

        $row = $this->db->fetchOne("SHOW TABLES LIKE 'page_contact_overrides'");
        self::$tableAvailable = (bool)$row;
        return self::$tableAvailable;
    }
}
