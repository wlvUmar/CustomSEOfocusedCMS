<?php
require_once BASE_PATH . '/core/Database.php';

class BotUser {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function upsert($telegram_id, $username = '', $first_name = '', $last_name = '') {
        $existing = $this->db->fetchOne("SELECT id FROM bot_users WHERE telegram_id = ?", [$telegram_id]);
        if ($existing) {
            $this->db->query(
                "UPDATE bot_users SET username = ?, first_name = ?, last_name = ? WHERE telegram_id = ?",
                [$username, $first_name, $last_name, $telegram_id]
            );
            return $existing['id'];
        }

        $this->db->query(
            "INSERT INTO bot_users (telegram_id, username, first_name, last_name, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$telegram_id, $username, $first_name, $last_name]
        );
        return $this->db->lastInsertId();
    }

    public function findByTelegramId($telegram_id) {
        return $this->db->fetchOne("SELECT * FROM bot_users WHERE telegram_id = ?", [$telegram_id]);
    }

    public function savePhone($telegram_id, $phone) {
        $this->db->query(
            "UPDATE bot_users SET phone = ? WHERE telegram_id = ?",
            [$phone, $telegram_id]
        );
    }

    public function findPhoneByTelegramId($telegram_id) {
        $result = $this->db->fetchOne("SELECT phone FROM bot_users WHERE telegram_id = ?", [$telegram_id]);
        return $result ? $result['phone'] : null;
    }
}
