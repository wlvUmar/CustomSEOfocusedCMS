<?php
/**
 * Migration: Ensure pages hierarchy + widget columns exist and legacy data normalized.
 * Covers project-05 #2, #4, #7: parent_id, depth, show_link_widget/widget_title.
 */
return [
    "ALTER TABLE pages ADD COLUMN IF NOT EXISTS parent_id INT NULL",
    "ALTER TABLE pages ADD COLUMN IF NOT EXISTS depth INT NOT NULL DEFAULT 0",
    "ALTER TABLE pages ADD COLUMN IF NOT EXISTS show_link_widget TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE pages ADD COLUMN IF NOT EXISTS widget_title_ru VARCHAR(255) NULL",
    "ALTER TABLE pages ADD COLUMN IF NOT EXISTS widget_title_uz VARCHAR(255) NULL",
    "ALTER TABLE pages ADD COLUMN IF NOT EXISTS rotation_mode ENUM('auto','manual','disabled') NOT NULL DEFAULT 'auto'",
    "ALTER TABLE pages ADD COLUMN IF NOT EXISTS selected_rotation_id INT NULL",
    // Normalize legacy empty/0 parent_id to NULL for consistent queries
    "UPDATE pages SET parent_id = NULL WHERE parent_id = '' OR parent_id = 0",
    // Backfill widget columns where missing (no-op if already filled)
    "CREATE INDEX IF NOT EXISTS idx_pages_parent ON pages(parent_id)",
    "CREATE INDEX IF NOT EXISTS idx_pages_slug ON pages(slug)",
];
