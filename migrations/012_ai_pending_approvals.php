<?php
/**
 * Migration: AI Studio pending approvals table.
 * Canonical DDL for AiSessionStore::ensurePendingTable (now a no-op).
 */
return [
    "CREATE TABLE IF NOT EXISTS ai_pending_approvals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id CHAR(36) NOT NULL,
        user_id INT NOT NULL,
        call_id VARCHAR(64) NOT NULL,
        tool VARCHAR(64) NOT NULL,
        plan TEXT,
        reason TEXT,
        mode ENUM('plan','build') NOT NULL DEFAULT 'plan',
        pending_json JSON NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_session_call (session_id, user_id, call_id),
        INDEX idx_session_user (session_id, user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];
