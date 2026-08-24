<?php
/**
 * Migration: Add automatic page revision history.
 * Keeps a JSON snapshot of the `pages` row before every UPDATE,
 * so any AI or admin edit can be rolled back even if fields were wiped.
 * Retains last 20 revisions per page (pruned in model).
 */
return [
    "CREATE TABLE IF NOT EXISTS page_revisions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        page_id INT NOT NULL,
        snapshot LONGTEXT NOT NULL COMMENT 'JSON snapshot of pages row before change',
        changed_fields VARCHAR(1000) DEFAULT NULL COMMENT 'Comma-separated list of fields that were in the update payload',
        source VARCHAR(20) NOT NULL DEFAULT 'unknown' COMMENT 'admin/ai/revision_restore/system',
        created_by INT DEFAULT NULL,
        created_by_name VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_page_id (page_id),
        INDEX idx_created_at (created_at),
        CONSTRAINT fk_page_revisions_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
];
