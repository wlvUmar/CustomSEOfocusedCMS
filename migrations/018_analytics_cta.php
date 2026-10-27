<?php
/**
 * Migration: per-CTA analytics (views + clicks per placement).
 *  - analytics_cta: daily counters keyed by (date, page, language, cta_id, event, utm_source)
 *  - analytics_dedup_cta: one row per (visitor, page, cta, event, day) so counts are unique visitors
 * Additive only: the legacy analytics / analytics_hourly / dedup tables and endpoints are untouched.
 * Safe to re-run (IF NOT EXISTS).
 */
return [
    "CREATE TABLE IF NOT EXISTS `analytics_cta` (
        `date` date NOT NULL,
        `page_slug` varchar(100) NOT NULL,
        `language` varchar(5) NOT NULL,
        `cta_id` varchar(32) NOT NULL,
        `event` enum('view','click') NOT NULL,
        `utm_source` varchar(100) NOT NULL DEFAULT '',
        `cnt` int unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`date`, `page_slug`, `language`, `cta_id`, `event`, `utm_source`),
        KEY `idx_cta_event` (`cta_id`, `event`, `date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `analytics_dedup_cta` (
        `visitor_id` varchar(64) NOT NULL,
        `page_slug` varchar(100) NOT NULL,
        `cta_id` varchar(32) NOT NULL,
        `event` enum('view','click') NOT NULL,
        `view_date` date NOT NULL,
        PRIMARY KEY (`visitor_id`, `page_slug`, `cta_id`, `event`, `view_date`),
        KEY `idx_view_date` (`view_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
