<?php
/**
 * Migration: read-only Bearer tokens for the internal SEO API (/api/seo/v1/*).
 *  - Only sha256(token) is stored; the plaintext is shown once by scripts/issue-seo-token.php.
 *  - scopes: subset of pages,stats,gsc.
 * Safe to re-run (IF NOT EXISTS).
 */
return [
    "CREATE TABLE IF NOT EXISTS `seo_api_tokens` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `label` VARCHAR(100) NOT NULL,
        `token_hash` CHAR(64) NOT NULL,
        `scopes` SET('pages','stats','gsc') NOT NULL DEFAULT 'pages,stats,gsc',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `expires_at` DATETIME NOT NULL,
        `last_used_at` DATETIME NULL DEFAULT NULL,
        `revoked_at` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_token_hash` (`token_hash`),
        KEY `idx_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
