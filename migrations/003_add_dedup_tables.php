<?php
/**
 * Migration: Create atomic dedup tables for race-safe analytics tracking.
 *
 * Replaces the old cookie-based dedup (TOCTOU race, overflow-prone) with
 * database-backed PRIMARY KEY dedup via INSERT IGNORE.
 * Each visitor+page+day+utm combination gets exactly one row.
 */

return [
    // Visit dedup: one row per (visitor, page, language, day, utm_source)
    "CREATE TABLE IF NOT EXISTS `analytics_dedup_visits` (
      `visitor_id` varchar(64) NOT NULL,
      `page_slug` varchar(100) NOT NULL,
      `language` varchar(5) NOT NULL,
      `view_date` date NOT NULL,
      `utm_source` varchar(100) NOT NULL DEFAULT '',
      PRIMARY KEY (`visitor_id`, `page_slug`, `language`, `view_date`, `utm_source`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Click dedup: one row per (visitor, page, language, day, utm_source)
    "CREATE TABLE IF NOT EXISTS `analytics_dedup_clicks` (
      `visitor_id` varchar(64) NOT NULL,
      `page_slug` varchar(100) NOT NULL,
      `language` varchar(5) NOT NULL,
      `view_date` date NOT NULL,
      `utm_source` varchar(100) NOT NULL DEFAULT '',
      PRIMARY KEY (`visitor_id`, `page_slug`, `language`, `view_date`, `utm_source`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Phone call dedup: one row per (visitor, page, language, utm_source) ever
    "CREATE TABLE IF NOT EXISTS `analytics_dedup_phone_calls` (
      `visitor_id` varchar(64) NOT NULL,
      `page_slug` varchar(100) NOT NULL,
      `language` varchar(5) NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `utm_source` varchar(100) NOT NULL DEFAULT '',
      PRIMARY KEY (`visitor_id`, `page_slug`, `language`, `utm_source`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Internal link dedup: one row per (visitor, from, to, day)
    "CREATE TABLE IF NOT EXISTS `analytics_dedup_internal_links` (
      `visitor_id` varchar(64) NOT NULL,
      `from_slug` varchar(100) NOT NULL,
      `to_slug` varchar(100) NOT NULL,
      `view_date` date NOT NULL,
      PRIMARY KEY (`visitor_id`, `from_slug`, `to_slug`, `view_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Site visit dedup: one row per (visitor, day)
    "CREATE TABLE IF NOT EXISTS `analytics_dedup_site_visits` (
      `visitor_id` varchar(64) NOT NULL,
      `view_date` date NOT NULL,
      PRIMARY KEY (`visitor_id`, `view_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Rate limit fallback: IP-based throttle when session is unavailable
    "CREATE TABLE IF NOT EXISTS `analytics_throttle` (
      `throttle_key` varchar(64) NOT NULL,
      `hits` int unsigned NOT NULL DEFAULT 1,
      `expires_at` datetime NOT NULL,
      PRIMARY KEY (`throttle_key`),
      KEY `idx_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
