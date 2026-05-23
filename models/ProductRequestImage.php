<?php
require_once BASE_PATH . '/core/Database.php';

class ProductRequestImage {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($request_id, $image_path, $sort_order = 0) {
        $this->db->query(
            "INSERT INTO product_request_images (request_id, image_path, sort_order, created_at) VALUES (?, ?, ?, NOW())",
            [$request_id, $image_path, $sort_order]
        );
        return $this->db->lastInsertId();
    }

    public function getByRequestId($request_id) {
        return $this->db->fetchAll(
            "SELECT * FROM product_request_images WHERE request_id = ? ORDER BY sort_order ASC, id ASC",
            [$request_id]
        );
    }

    public function countByRequestId($request_id) {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM product_request_images WHERE request_id = ?",
            [$request_id]
        );
        return (int)($row['total'] ?? 0);
    }
    // In ProductRequestImage.php
    public function deleteByRequestId($id) {
        $this->db->query('DELETE FROM product_request_images WHERE request_id = ?', [$id]);
    }

}
