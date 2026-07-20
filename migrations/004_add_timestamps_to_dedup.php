<?php
/**
 * Migration: Add created_at timestamps to dedup tables for real-time charts.
 *
 * Records when each dedup row was first created, enabling hourly/daily
 * time-series queries without changing the dedup PK.
 */

return [
    "ALTER TABLE `analytics_dedup_visits`
     ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
     AFTER `utm_source`",

    "ALTER TABLE `analytics_dedup_clicks`
     ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
     AFTER `utm_source`",
];
