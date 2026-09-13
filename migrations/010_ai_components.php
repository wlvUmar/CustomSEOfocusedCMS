<?php
/**
 * Migration: Component library — DB canonical for components.css
 *  - ai_components: one row per /* === C-NAME === * / block (95 rows, per-block).
 *  - component_revisions: undo history, last 20 per component (like page_revisions).
 * Safe to re-run (IF NOT EXISTS). File stays source of truth for public rendering
 * until first rebuild — this migration only creates empty tables.
 */
return [
    "CREATE TABLE IF NOT EXISTS ai_components (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(100) NOT NULL,
        category VARCHAR(40) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        html_demo MEDIUMTEXT NULL,
        css_body MEDIUMTEXT NOT NULL,
        variants JSON NULL,
        tokens_used JSON NULL,
        status ENUM('active','deprecated') NOT NULL DEFAULT 'active',
        sort INT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_slug (slug),
        INDEX idx_category (category),
        INDEX idx_status (status),
        INDEX idx_sort (sort)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

    "CREATE TABLE IF NOT EXISTS component_revisions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        component_id INT UNSIGNED NOT NULL,
        slug VARCHAR(100) NOT NULL,
        snapshot LONGTEXT NOT NULL COMMENT 'JSON snapshot of ai_components row before change',
        changed_fields VARCHAR(1000) DEFAULT NULL,
        source VARCHAR(20) NOT NULL DEFAULT 'admin',
        created_by INT DEFAULT NULL,
        created_by_name VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_comp (component_id),
        INDEX idx_slug (slug),
        INDEX idx_created_at (created_at),
        CONSTRAINT fk_component_revisions_comp FOREIGN KEY (component_id) REFERENCES ai_components(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
];
