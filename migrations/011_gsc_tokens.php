<?php
/**
 * Migration: GSC OAuth token store for live Search Console API.
 *  - gsc_tokens: single row (id=1), encrypted refresh/access tokens.
 * Safe to re-run (IF NOT EXISTS).
 *
 * NOTE: supersedes migrations/2026_08_23_add_gsc_tokens.sql (same DDL).
 * migrate.php runs both *.php and *.sql; the .sql is kept for history,
 * this wrapper is the canonical numbered migration going forward.
 */
return [
    "CREATE TABLE IF NOT EXISTS `gsc_tokens` (
        `id` int(11) NOT NULL DEFAULT 1,
        `refresh_token` text NOT NULL,
        `access_token` text DEFAULT NULL,
        `expires_at` datetime DEFAULT NULL,
        `site_url` varchar(500) DEFAULT NULL,
        `email` varchar(255) DEFAULT NULL,
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
