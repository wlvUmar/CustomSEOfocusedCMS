<?php
class Media {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll() {
        $sql = "SELECT * FROM media ORDER BY uploaded_at DESC";
        return $this->db->fetchAll($sql);
    }

    public function create($data) {
        $sql = "INSERT INTO media (filename, original_name, file_size, mime_type) 
                VALUES (?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $data['filename'],
            $data['original_name'],
            $data['file_size'],
            $data['mime_type']
        ]);
        
        return $this->db->lastInsertId();
    }

    public function delete($id) {
        $media = $this->db->fetchOne("SELECT * FROM media WHERE id = ?", [$id]);
        if ($media) {
            $filepath = UPLOAD_PATH . $media['filename'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            $sql = "DELETE FROM media WHERE id = ?";
            return $this->db->query($sql, [$id]);
        }
        return false;
    }
}