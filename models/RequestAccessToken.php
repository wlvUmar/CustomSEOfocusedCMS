<?php

class RequestAccessToken {
    private $pdo;

    public function __construct() {
        $this->pdo = require BASE_PATH . '/config/database.php';
    }

    /**
     * Create a new access token for a request (3-day expiration)
     */
    public function create($request_id, $token) {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+3 days'));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO request_access_tokens (request_id, token, expires_at)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)
        ");
        
        $stmt->execute([$request_id, $token, $expiresAt]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Verify and get request ID from token
     * Returns request_id if valid, null if expired or not found
     */
    public function validateToken($token) {
        $stmt = $this->pdo->prepare("
            SELECT request_id 
            FROM request_access_tokens
            WHERE token = ? AND expires_at > NOW()
            LIMIT 1
        ");
        
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Increment usage count
            $this->pdo->prepare("
                UPDATE request_access_tokens 
                SET used_count = used_count + 1 
                WHERE token = ?
            ")->execute([$token]);
            
            return $result['request_id'];
        }
        
        return null;
    }

    /**
     * Get token for a request
     */
    public function getTokenByRequestId($request_id) {
        $stmt = $this->pdo->prepare("
            SELECT token 
            FROM request_access_tokens
            WHERE request_id = ? AND expires_at > NOW()
            ORDER BY created_at DESC
            LIMIT 1
        ");
        
        $stmt->execute([$request_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['token'] : null;
    }

    /**
     * Delete expired tokens (cleanup)
     */
    public function deleteExpired() {
        $stmt = $this->pdo->prepare("
            DELETE FROM request_access_tokens 
            WHERE expires_at < NOW()
        ");
        
        return $stmt->execute();
    }
}
