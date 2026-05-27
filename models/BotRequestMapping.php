<?php
require_once BASE_PATH . '/core/Database.php';

class BotRequestMapping {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($request_id, $telegram_id) {
        $this->db->query(
            "INSERT INTO bot_request_mappings (request_id, telegram_id, notification_sent, created_at) VALUES (?, ?, 0, NOW())",
            [$request_id, $telegram_id]
        );
        return $this->db->lastInsertId();
    }

    public function findByRequestId($request_id) {
        return $this->db->fetchOne("SELECT * FROM bot_request_mappings WHERE request_id = ?", [$request_id]);
    }

    public function markNotified($request_id) {
        $this->db->query("UPDATE bot_request_mappings SET notification_sent = 1 WHERE request_id = ?", [$request_id]);
    }

    public function claimNotification($request_id) {
        $mapping = $this->findByRequestId($request_id);
        if (!$mapping) {
            return null;
        }

        if ((int)$mapping['notification_sent'] === 0) {
            $stmt = $this->db->query("UPDATE bot_request_mappings SET notification_sent = 2 WHERE request_id = ? AND notification_sent = 0", [$request_id]);
            if ($stmt->rowCount() > 0) {
                return $this->findByRequestId($request_id);
            }
        }

        return $mapping;
    }

    public function releaseNotification($request_id) {
        $this->db->query(
            "UPDATE bot_request_mappings SET notification_sent = 0 WHERE request_id = ? AND notification_sent = 2",
            [$request_id]
        );
        return $this->findByRequestId($request_id);
    }

    public function findPending() {
        return $this->db->fetchAll("SELECT * FROM bot_request_mappings WHERE notification_sent = 0 ORDER BY created_at DESC");
    }
}
