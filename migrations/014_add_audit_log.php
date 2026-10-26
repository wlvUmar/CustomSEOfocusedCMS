<?php
/**
 * Migration: audit log of content/config changes (who changed what, from what to what).
 *  - changes: JSON {field: {from, to}}; long text fields store only the changed region
 *    (full page snapshots remain in page_revisions).
 *  - source: admin / ai / system.
 * Safe to re-run (IF NOT EXISTS).
 */
return [
    "CREATE TABLE IF NOT EXISTS `audit_log` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `user_id` INT DEFAULT NULL,
        `username` VARCHAR(100) DEFAULT NULL,
        `source` VARCHAR(20) NOT NULL DEFAULT 'admin',
        `action` VARCHAR(20) NOT NULL,
        `entity_type` VARCHAR(30) NOT NULL,
        `entity_id` INT DEFAULT NULL,
        `entity_label` VARCHAR(255) DEFAULT NULL,
        `summary` VARCHAR(500) DEFAULT NULL,
        `changes` LONGTEXT DEFAULT NULL,
        `ip` VARCHAR(45) DEFAULT NULL,
        `request_uri` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_entity` (`entity_type`, `entity_id`),
        KEY `idx_action` (`action`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
