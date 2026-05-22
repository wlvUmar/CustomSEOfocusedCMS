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
}
