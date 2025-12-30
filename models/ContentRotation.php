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
}