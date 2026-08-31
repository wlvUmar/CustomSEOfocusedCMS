# Project 05 — CMS Core: Pages & Revisions

> **Status 2026-08-30: 10/10 FIXED.**

## 1. `getBySlug` published-only vs `getById` bypass — ✅ FIXED
- **Location:** `models/Page.php:11` vs `25` → now `Page.php:11` `getBySlug($slug, bool $includeUnpublished=false)`
- **Fix:** Added optional `$includeUnpublished` param. Public front keeps `is_published=1` default (SEO safe); admin preview can call `getBySlug($slug, true)` to fetch drafts. No breaking change.

## 2. No slug uniqueness (DB KEY not UNIQUE) — ✅ FIXED (app-level, 09-03)
- **Location:** `models/Page.php:30` `create()` + `kuplyuta_db.sql:820`
- **Fix:** `assertSlugUnique()` `Page.php:30` checks empty, reserved `home/main/admin/api/articles`, and `SELECT id WHERE slug=?` collision. `create()` and `update()` now call it. DB UNIQUE deferred to `project-12` migration to avoid breaking existing dup rows.

## 3. Rotation mode creation ignores input — ✅ FIXED
- **Location:** `models/Page.php:56-69` → now `Page.php:62` respects explicit `rotation_mode` if in `['auto','manual','disabled']`, else falls back to `enable_rotation` flag.
- **Fix:** Caller `PageAdminController.php:708` `manual` no longer overwritten to `auto`. Preserve BC for legacy calls.

## 4. Empty `parent_id` stored as `''` vs `NULL` — ✅ FIXED
- **Location:** `models/Page.php:57,105` + `getRootPages()` `228`
- **Fix:** Normalize `''/0/'0'` → `null` in both `create()` and `update()` before insert, and in comparison. `getRootPages()` now `WHERE (parent_id IS NULL OR parent_id='' OR parent_id=0)` to catch legacy rows. All new writes are NULL-consistent.

## 5. Revision snapshot silently skipped when file missing — ✅ FIXED
- **Location:** `models/Page.php:107` `require_once PageRevision` with `@` + `class_exists(...,false)`
- **Fix:** Changed `@require_once` to `require_once` and `class_exists(...,true)` so missing file throws and is logged with `[Page] revision snapshot failed`. No longer silent.

## 6. Snapshot source detection brittle — ✅ FIXED
- **Location:** `models/PageRevision.php:24-35` `debug_backtrace` string match `PageTools|AiToolRegistry|AiStudioController` + `strpos($fn,'ai')`
- **Fix:** Expanded to 10 frames, explicit `$aiClasses` allowlist `PageTools,AiToolRegistry,AiStudioController,RotationTools,SiteTools,MemoryTools,AnalyticsTools,AnalyticsQueryTools`, no substring `ai` fallback. `source` now `ai` only for known AI classes, else `admin`.

## 7. Restore allowlist vs update column mismatch — ✅ FIXED
- **Location:** `models/PageRevision.php:118` allow `show_link_widget, widget_title_*` but `models/Page.php:136` update SQL didn't list them
- **Fix:** Extended `Page::update()` SET to include `show_link_widget, widget_title_ru, widget_title_uz` `Page.php:136,149` and params `Page.php:172`. `PageRevision::restore()` now actually persists those columns.

## 8. Byte vs multibyte truncation — ✅ FIXED
- **Location:** `models/PageRevision.php:79` `CHAR_LENGTH(snapshot)` vs `models/Page.php` clipRow
- **Fix:** `getByPageId()` now returns both `LENGTH(snapshot) AS snapshot_bytes` and `CHAR_LENGTH` for `snapshot_chars` `PageRevision.php:79`, allowing correct char vs byte distinction. Callers can pick appropriate metric.

## 9. `updateDepth` N+1 without transaction — ✅ FIXED
- **Location:** `models/Page.php:352-376` per-level `getById` + recursive children, no txn
- **Fix:** Wrapped in transaction `Page.php:352` `updateDepth()` now `beginTransaction()` if not already in one, calls `updateDepthInner($id,0)` recursive, commits/rollBacks safely. Prevents partial depth on error mid-recursion.

## 10. Hierarchy recursion unbounded — ✅ FIXED
- **Location:** `models/Page.php:283` `getChildrenRecursive` no depth guard → stack overflow on cyclic FK
- **Fix:** Added `int $depth` param capped at 10 `Page.php:283` and `getBreadcrumbs` already capped at 10. `updateDepthInner` also capped at 20. Cyclic references now truncated safely.
