<?php
require_once BASE_PATH . '/core/Database.php';

class ProductRequest {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($data) {
        $sql = "INSERT INTO product_requests
            (image_path, description, status, price, reviewer_notes, reviewer_id, created_at)
            VALUES (?, ?, 'pending', ?, ?, ?, NOW())";
        $this->db->query($sql, [
            $data['image_path'] ?? '',
            $data['description'] ?? '',
            $data['price'] ?? null,
            $data['reviewer_notes'] ?? null,
            $data['reviewer_id'] ?? null,
        ]);
        return $this->db->lastInsertId();
    }

    public function getById($id) {
        return $this->db->fetchOne("SELECT * FROM product_requests WHERE id = ?", [$id]);
    }

    public function getPending($limit = 100) {
        return $this->db->fetchAll("SELECT * FROM product_requests WHERE status IN ('pending','in_review') ORDER BY created_at DESC LIMIT ?", [$limit]);
    }

    public function updateStatus($id, $status, $price = null, $notes = null, $reviewer_id = null) {
        $fields = ['status' => $status];
        $sql = "UPDATE product_requests SET status = ?, reviewed_at = NOW()";
        $params = [$status];
        if ($price !== null) {
            $sql .= ", price = ?";
            $params[] = $price;
        }
        if ($notes !== null) {
            $sql .= ", reviewer_notes = ?";
            $params[] = $notes;
        }
        if ($reviewer_id !== null) {
            $sql .= ", reviewer_id = ?";
            $params[] = $reviewer_id;
        }
        $sql .= " WHERE id = ?";
        $params[] = $id;
        $this->db->query($sql, $params);
    }
    public function deleteById($id) {
        $this->db->query('DELETE FROM product_requests WHERE id = ?', [$id]);
    }
    public function getAll($limit = 200) {
    return $this->db->fetchAll(
        "SELECT * FROM product_requests ORDER BY created_at DESC LIMIT ?",
        [$limit]
    );
}
}
