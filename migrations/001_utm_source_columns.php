<?php
/**
 * Migration: Add utm_source columns to analytics tables.
 *
 * Prior to this migration, analytics tables tracked visits/clicks/calls
 * per (page_slug, language, date) but did not differentiate by UTM
 * campaign.  Each UTM source now gets its own row so campaign-level
 * attribution data is never silently overwritten.
 *
 * This replaces the old per-request DDL in core/helpers.php
 * (ensureUtmSourceSchema) that ran ALTER TABLE on every page load.
 */

return [

    // ── analytics ──────────────────────────────────────────
    "ALTER TABLE analytics ADD COLUMN IF NOT EXISTS utm_source VARCHAR(100) NOT NULL DEFAULT '' AFTER phone_calls",
    "ALTER TABLE analytics DROP INDEX IF EXISTS idx_utm_source",
    "ALTER TABLE analytics DROP INDEX IF EXISTS unique_daily",
    "ALTER TABLE analytics ADD UNIQUE KEY unique_daily (`page_slug`, `language`, `date`, `utm_source`)",

    // ── analytics_hourly ───────────────────────────────────
    "ALTER TABLE analytics_hourly ADD COLUMN IF NOT EXISTS utm_source VARCHAR(100) NOT NULL DEFAULT '' AFTER phone_calls",
    "ALTER TABLE analytics_hourly DROP INDEX IF EXISTS idx_utm_source",
    "ALTER TABLE analytics_hourly DROP INDEX IF EXISTS unique_hourly",
    "ALTER TABLE analytics_hourly ADD UNIQUE KEY unique_hourly (`page_slug`, `language`, `date`, `hour`, `utm_source`)",

    // ── analytics_monthly ──────────────────────────────────
    "ALTER TABLE analytics_monthly ADD COLUMN IF NOT EXISTS utm_source VARCHAR(100) NOT NULL DEFAULT '' AFTER total_phone_calls",
    "ALTER TABLE analytics_monthly DROP INDEX IF EXISTS unique_monthly",
    "ALTER TABLE analytics_monthly ADD UNIQUE KEY unique_monthly (`page_slug`, `language`, `year`, `month`, `utm_source`)",

    // ── analytics_internal_links ───────────────────────────
    "ALTER TABLE analytics_internal_links DROP INDEX IF EXISTS idx_utm_source",

];
