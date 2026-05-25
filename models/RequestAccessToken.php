<?php
require_once BASE_PATH . '/core/Database.php';

class RequestAccessToken {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new access token for a request (3-day expiration)
     */
    public function create($request_id, $token) {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+3 days'));
        
        $sql = "
            INSERT INTO request_access_tokens (request_id, token, expires_at)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)
        ";
        
        $this->db->query($sql, [$request_id, $token, $expiresAt]);
        return $this->db->lastInsertId();
    }

    /**
     * Verify and get request ID from token
     * Returns request_id if valid, null if expired or not found
     */
    public function validateToken($token) {
        error_log("[TokenValidation] Checking token: " . substr($token, 0, 8) . "...");
        
        $sql = "
            SELECT request_id 
            FROM request_access_tokens
            WHERE token = ? AND expires_at > NOW()
            LIMIT 1
        ";
        
        $result = $this->db->fetchOne($sql, [$token]);
        
        if ($result) {
            $requestId = (int)$result['request_id'];
            error_log("[TokenValidation] Token valid for request_id: " . $requestId);
            // Increment usage count
            $updateSql = "
                UPDATE request_access_tokens 
                SET used_count = used_count + 1 
                WHERE token = ?
            ";
            $this->db->query($updateSql, [$token]);
            
            return $requestId;
        }
        
        error_log("[TokenValidation] Token invalid or expired: " . substr($token, 0, 8) . "...");
        return null;
    }

    /**
     * Get token for a request
     */
    public function getTokenByRequestId($request_id) {
        $sql = "
            SELECT token 
            FROM request_access_tokens
            WHERE request_id = ? AND expires_at > NOW()
            ORDER BY created_at DESC
            LIMIT 1
        ";
        
        $result = $this->db->fetchOne($sql, [$request_id]);
        return $result ? $result['token'] : null;
    }

    /**
     * Delete expired tokens (cleanup)
     */
    public function deleteExpired() {
        $sql = "
            DELETE FROM request_access_tokens 
            WHERE expires_at < NOW()
        ";
        
        return $this->db->query($sql);
    }
}
