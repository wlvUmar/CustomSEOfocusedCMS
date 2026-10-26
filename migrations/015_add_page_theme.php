<?php
/**
 * Migration: per-page layout theme (pages.theme) + one-time keyword auto-assign.
 *  - theme: 'default' keeps the current look; other values are defined in core/PageTheme.php
 *    (screen, frost, wave, flame, breeze, rug, wood, fresh).
 *  - Auto-assign only touches pages still on 'default' and matches the slug or RU title
 *    against core/PageTheme.php::autoAssignRules(). The home/main pages are left alone.
 *    After this runs, change any page in Admin → Pages → Design → Page theme.
 * Safe to re-run (IF NOT EXISTS; assignment skips pages that already have a theme).
 */
require_once __DIR__ . '/../core/PageTheme.php';

$sql = [
    "ALTER TABLE pages ADD COLUMN IF NOT EXISTS theme VARCHAR(20) NOT NULL DEFAULT 'default'",
];

foreach (PageTheme::autoAssignRules() as $theme => $needles) {
    $conds = [];
    foreach ($needles as $n) {
        $like = "'%" . str_replace(["\\", "'", '%', '_'], ["\\\\", "''", '\\%', '\\_'], $n) . "%'";
        $conds[] = "LOWER(slug) LIKE $like";
        $conds[] = "LOWER(title_ru) LIKE $like";
    }
    $sql[] = "UPDATE pages SET theme = '" . $theme . "' WHERE theme = 'default' AND slug NOT IN ('home','main','about','o-nas','articles') AND (" . implode(' OR ', $conds) . ")";
}

return $sql;
