<?php
/**
 * Migration: Add google_maps_embed_url column to seo_settings.
 *
 * Adds an optional text field for embedding a Google Maps iframe URL
 * in the page footer.  This replaces the old per-request DDL in
 * core/helpers.php (ensureSeoMapsSchema).
 */

return [
    "ALTER TABLE seo_settings ADD COLUMN IF NOT EXISTS google_maps_embed_url TEXT NULL AFTER google_review_url",
];
