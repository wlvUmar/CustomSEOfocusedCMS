<?php
/**
 * Migration: Add cross-session DB for AI Studio (store_context persistence).
 */
return [
    "CREATE TABLE IF NOT EXISTS ai_sessions (
        id CHAR(36) PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(200) DEFAULT '',
        model VARCHAR(80) NOT NULL DEFAULT 'deepseek/deepseek-chat',
        mode ENUM('plan','build') NOT NULL DEFAULT 'plan',
        history JSON NULL,
        context JSON NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_updated (user_id, updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];
