<?php
/**
 * Migration: AI Studio hardening — concurrency, cancellation, queue, summarization.
 */
return [
    "ALTER TABLE ai_sessions ADD COLUMN IF NOT EXISTS run_state ENUM('idle','running','queued') NOT NULL DEFAULT 'idle' AFTER mode",
    "ALTER TABLE ai_sessions ADD COLUMN IF NOT EXISTS run_token CHAR(36) NULL AFTER run_state",
    "ALTER TABLE ai_sessions ADD COLUMN IF NOT EXISTS cancel_requested TINYINT(1) NOT NULL DEFAULT 0 AFTER run_token",
    "ALTER TABLE ai_sessions ADD COLUMN IF NOT EXISTS version INT NOT NULL DEFAULT 1 AFTER cancel_requested",
    "ALTER TABLE ai_sessions ADD COLUMN IF NOT EXISTS summary MEDIUMTEXT NULL AFTER context",
    "ALTER TABLE ai_sessions ADD COLUMN IF NOT EXISTS summary_updated_at DATETIME NULL AFTER summary",
    "CREATE TABLE IF NOT EXISTS ai_run_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id CHAR(36) NOT NULL,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        history JSON NULL,
        model VARCHAR(80) NOT NULL DEFAULT 'opencode-go/muse-spark-1.2-contributor',
        mode ENUM('plan','build') NOT NULL DEFAULT 'plan',
        status ENUM('queued','claimed','done','cancelled') NOT NULL DEFAULT 'queued',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session_status (session_id, status),
        INDEX idx_user_created (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
