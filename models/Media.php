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
            $filename = $media['filename'];
            // Directory traversal guard: filename must be basename only, no path separators
            if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false || strpos($filename, '..') !== false) {
                error_log("Media delete blocked - traversal in filename: $filename");
                return false;
            }
            $filepath = UPLOAD_PATH . $filename;
            $realBase = realpath(UPLOAD_PATH);
            $realFile = realpath($filepath);
            // Only unlink if realpath is inside UPLOAD_PATH (or file does not exist yet -> still allow DB delete)
            if ($realFile !== false && $realBase !== false) {
                if (strpos($realFile, $realBase) !== 0) {
                    error_log("Media delete blocked - outside upload dir: $realFile");
                    return false;
                }
                $filepath = $realFile;
            } elseif ($realBase !== false) {
                // file may not exist (already deleted) - ensure candidate is inside base before unlink attempt
                $candidate = $realBase . DIRECTORY_SEPARATOR . $filename;
                if (strpos($candidate, $realBase) !== 0) {
                    error_log("Media delete blocked - candidate outside base: $candidate");
                    return false;
                }
            }
            
            // Delete the actual file
            if (file_exists($filepath)) {
                if (!unlink($filepath)) {
                    error_log("Failed to delete file: $filepath");
                    return false;
                }
            }
            
            // Delete database record
            $sql = "DELETE FROM media WHERE id = ?";
            return $this->db->query($sql, [$id]);
        }
        return false;
    }
}