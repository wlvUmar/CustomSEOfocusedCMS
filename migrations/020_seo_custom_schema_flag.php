<?php
/**
 * Migration: store the "custom Organization JSON-LD" toggle and its raw text
 * in their own columns.
 *
 * Before this, the toggle and the textarea were read from organization_schema,
 * which holds the auto-generated JSON after every save. That made the custom
 * mode look enabled and froze the generated values.
 */

return [
    "ALTER TABLE seo_settings ADD COLUMN IF NOT EXISTS org_schema_custom TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE seo_settings ADD COLUMN IF NOT EXISTS organization_schema_raw LONGTEXT NULL",
];
