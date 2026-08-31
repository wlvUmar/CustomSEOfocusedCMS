<?php
/**
 * Migration: Per-page custom CSS + rotation custom CSS
 * Allows pages.css to be overridden per page, including header/footer
 * Safe to re-run (IF NOT EXISTS)
 */
return [
    "ALTER TABLE pages ADD COLUMN IF NOT EXISTS custom_css TEXT NULL AFTER content_uz",
    "ALTER TABLE content_rotations ADD COLUMN IF NOT EXISTS custom_css TEXT NULL AFTER content_uz",
];
