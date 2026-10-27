<?php
/**
 * Migration: image_generations — log of AI image generations from the admin (Media → Quick add).
 * Used for the daily cap (IMAGE_GEN_DAILY_LIMIT) and a cost trail. Safe to re-run (IF NOT EXISTS).
 */
return [
    "CREATE TABLE IF NOT EXISTS `image_generations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `page_id` int(11) DEFAULT NULL,
        `model` varchar(100) NOT NULL,
        `prompt` varchar(700) NOT NULL,
        `cost_usd` decimal(10,5) DEFAULT NULL,
        `status` enum('ok','fail') NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_created_status` (`created_at`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
