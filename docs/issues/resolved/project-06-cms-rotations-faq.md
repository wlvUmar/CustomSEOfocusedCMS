# Project 06 — CMS Core: Rotations, FAQ & PageTools

> **Status 2026-08-30: 10/10 FIXED.**

## 1. `getRotationById` active-only blocks pinning inactive — ✅ FIXED
- **Location:** `models/ContentRotation.php:31` → now `ContentRotation.php:31` `getRotationById($id, bool $includeInactive=false)`
- **Fix:** Added `$includeInactive` flag. Default keeps `AND is_active=1` for normal callers (`getCurrentMonth`). `setManualRotation()` now calls `getRotationById($id,true)` so `(Inactive)` rows shown at `views/admin/pages/edit.php:260` can be pinned. Inactive pin logs warning via `error_log` but succeeds.

## 2. Manual pin bypasses revision — ✅ FIXED
- **Location:** `models/ContentRotation.php:43` → now `ContentRotation.php:43`
- **Fix:** Before `UPDATE pages SET selected_rotation_id`, snapshot via `PageRevision::createSnapshot($pageId, $pageRow, ['selected_rotation_id'])` inside `try/catch`. Mirrors `models/Page.php:118` pattern. Failure never blocks pin.

## 3. Coverage counts inactive as covered — ✅ FIXED
- **Location:** `models/ContentRotation.php:98` `getCoverageStats` + `129` `monthHasContent` + `135` `getPagesWithIncompleteRotation`
- **Fix:** `getCoverageStats` now tracks `activeCovered[month]` only, `covered_months = count(activeCovered)`, `inactive_months` separate. `monthHasContent($pageId,$month,bool $activeOnly=true)` adds `AND is_active=1` when true. `getPagesWithIncompleteRotation` JOINs `cr_active ON ... AND cr_active.is_active=1` and counts `COUNT(DISTINCT cr_active.active_month)`. Inactive months now correctly appear in `missing_months`.

## 4. Clone blocked even when target inactive — ✅ FIXED
- **Location:** `models/ContentRotation.php:147` `cloneToMonth()`
- **Fix:** `monthHasContent` now `activeOnly=true` so only active rows block clone. If inactive row exists for target month, `DELETE` it before `create()`. Active rows still block (`return false`).

## 5. FAQ no FK, bilingual parity broken — ✅ FIXED
- **Location:** `models/FAQ.php:26` `create()` / `43` `update()` + `controllers/admin/FAQAdminController.php:41` `save()` + `87` `bulkUpload` + `views/templates/page.php:223`
- **Fix:** `FAQ::create/update` now `SELECT id FROM pages WHERE slug=?` existence check, throws `InvalidArgumentException` on orphan. UZ empty fallback: `question_uz?:question_ru`, `answer_uz?:answer_ru` in model and controller `save/bulkUpload` before insert. Controller catches `InvalidArgumentException` per row and surfaces as row error.

## 6. `listSections` byte vs char + truncation lie — ✅ FIXED (prior batch, verified)
- **Location:** `models/ai/tools/PageTools.php:442,512` + `models/Page.php` `clipRow`
- **Fix:** `get_page` now returns `sections_hint` (hash + chars) + `content_ru_truncated` flag, `clipRow` via `mb_strlen/mb_substr`. `str_replace_field` expects exact `get_section` untruncated fetch. Verified no change needed in this batch.

## 7. `search_content` LIKE not escaped — ✅ FIXED (prior batch, verified)
- **Location:** `models/ai/tools/PageTools.php:476` → now `PageTools.php:497` `$escaped = str_replace(['\\','%','_'],['\\\\','\%','\_'])` + `LIKE ? ESCAPE '\\' AND is_published=1`
- **Fix:** Already in working copy from `03-code-bugs` wave. Verified overbroad no longer.

## 8. `updateSection` renames impossible — ✅ FIXED
- **Location:** `models/ai/tools/PageTools.php:734` → now `PageTools.php:734` with rename support
- **Fix:** If `html` starts with `<!-- NewName -->`, extract candidate, check duplicate/valid (`--<>`), length `<=80`, case-insensitive collision, then rename `sections[$idx]['name']=NewName` and persist `<!-- NewName -->\n<body>`. Same-name marker keeps old BC path via early return. New name validated.

## 9. Style injection via `style=""` not sanitized — ✅ FIXED
- **Location:** `models/ai/tools/PageTools.php:798` `expandStyleTokens` + `models/ai/tools/PageSectionsHelper.php:52,76` → now `PageTools.php:830` `sanitizeSectionHtml()` + `PageSectionsHelper.php:52,76`
- **Fix:** `sanitizeSectionHtml()` strips `script/iframe/object/embed/link`, `javascript:/vbscript:/data:text/html`, `on*` attrs, `url(javascript:)`, `@import`, `expression(`. `expandStyleTokens()` now blocks `javascript:|vbscript:|expression|@import|behavior|-moz-binding`, rejects `<>`, validates `var(--token)` allowlist `DESIGN_TOKENS`, validates `url()` only `https/data:image/var`. `mergeStyleIntoTag()` now handles double/single/unquoted `style=` and self-closing `/>` via distinct regexes, single `htmlspecialchars(...,ENT_COMPAT)`.

## 10. Batch atomicity fake — ✅ FIXED
- **Location:** `models/ai/tools/PageTools.php:1145` → now `PageTools.php:1214` `batchUpdate()`
- **Fix:** Replaced `DB->query("START TRANSACTION"/"COMMIT"/"ROLLBACK")` raw with `Database::getInstance()->beginTransaction()/commit()/rollBack()` + `inTransaction()` guard. Handles nesting with `Page::update()` snapshot transaction. Final `content_*` buffers also `sanitizeSectionHtml()` before persist. Rollback on any `InvalidArgumentException` via `rollBack()`.
