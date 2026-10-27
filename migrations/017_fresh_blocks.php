<?php
/**
 * Migration: fresh_blocks — small site-wide "freshness" block written by scripts/freshness-run.php.
 *  - one 'live' row per (block_key, lang); older rows become 'previous' (rollback = flip status)
 *  - body is JSON {"demand": "...", "season": "..."}; data_json keeps the facts the text was built from
 * Safe to re-run (IF NOT EXISTS).
 */
return [
    "CREATE TABLE IF NOT EXISTS `fresh_blocks` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `block_key` varchar(40) NOT NULL DEFAULT 'site',
        `lang` char(2) NOT NULL,
        `body` text NOT NULL,
        `data_json` text DEFAULT NULL,
        `status` enum('live','previous') NOT NULL DEFAULT 'live',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_live` (`block_key`, `lang`, `status`, `id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
