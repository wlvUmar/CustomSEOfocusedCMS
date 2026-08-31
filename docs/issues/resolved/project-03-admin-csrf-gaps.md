# Project 03 — Security: Missing CSRF Across Admin

> **Status 2026-08-30: 10/10 FIXED — all admin state-changing POSTs now require `validateCSRFToken()`; views send `csrfField()`/`generateCSRFToken()`; Request token path hardened (POST-prefer, no logged-in bypass).**
> Previous: Whole-project audit — no fixes.

## 1. Page delete has no CSRF — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/PageAdminController.php:744-754` `delete()` no `validateCSRFToken`, vs `save` at `673` which does.
- **Fix:** Added `validateCSRFToken($_POST['csrf_token'])` guard → 403 JSON if missing; view `views/admin/pages/list.php:121` already had `csrfField()` (kept).

## 2. Article save / delete / toggle all no CSRF — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/ArticleAdminController.php:91-218` `save` at 91, `delete` at 197, `togglePublish` at 223 — zero CSRF checks.
- **Fix:** `save()` → `validateCSRFToken` + redirect 403; `delete()`/`togglePublish()` → 403 JSON; `views/admin/articles/edit.php:34` added `csrfField()`; `list.php:136/155` JS fetch now sends `csrf_token=generateCSRFToken()`.

## 3. Media upload/bulk no CSRF — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/MediaController.php:80-144` `upload()` + `bulkUpload` at 286 — `requireAuth` only, no CSRF → CSRF upload to arbitrary `page_id`.
- **Fix:** `upload()` → `validateCSRFToken` 403 JSON; `bulkUpload()` → 403 JSON or redirect; JS in `views/admin/media/index.php` already appends token (kept).

## 4. SEO save no CSRF — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/SEOController.php:22-86` `save()` at 22 — `requireAuth` only.
- **Fix:** Added guard + redirect; `views/admin/seo/settings.php:7` added `csrfField()`.

## 5. Schema save/delete/bulkImport no CSRF — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/SchemaController.php:33-87` — all three POSTs lack CSRF.
- **Fix:** All three now `validateCSRFToken` → redirect 403; `views/admin/schemas/index.php:13/43` added `csrfField()` to both forms.

## 6. LinkWidget add/remove/toggle/reorder no CSRF — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/LinkWidgetController.php:39-51`
- **What:** Link spam / SEO sabotage via forged request.
- **Fix:** `addLink`/`removeLink`/`toggleWidget` → 403 redirect; `reorder` → 403 JSON; `views/admin/link_widget/manage.php:20/63/99` + `internal_links/manage_page.php:33/64/102` added `csrfField()`.

## 7. InternalLinks autoConnect/bulkAction no CSRF — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/InternalLinksController.php:91-284` — can create thousands of links (DoS).
- **Fix:** Both `autoConnect`/`bulkAction` now guard + redirect; `views/admin/internal_links/index.php:222/266` added `csrfField()`.

## 8. Rotation clone/delete/bulkAction/preview no CSRF — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/RotationAdminController.php:290-400` — only `save` at 109 checks; rest don't.
- **Fix:** Added guards to `setRotationMode`, `selectRotation`, `clearManualSelection`, `clone`, `bulkAction`, `delete`, `preview` (403), `bulkUpload`; `views/admin/rotations/manage.php:60/168/198/230` added `csrfField()`; `edit.php:9` already had.

## 9. FAQ delete/bulkUpload no CSRF — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/FAQAdminController.php:70-152` — partial coverage.
- **Fix:** `delete`/`bulkUpload` now guard; `views/admin/faqs/list.php:45/67` added `csrfField()`; `edit.php:9` already had.

## 10. Request approve/reject bypass CSRF when token-authenticated — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/RequestAdminController.php:96-152` `if ($isLoggedIn && !validateCSRF())` — token path `?token=` bypasses CSRF entirely; GET token logged in proxy/Referer.
- **Fix:** Token now read `$_POST['token'] ?? $_GET['token']` (POST-preferred) to reduce GET logging; logged-in path **always** requires `validateCSRFToken` even when token also present (no bypass); `delete` now branch: logged-in→CSRF required, token-only→skip CSRF but still validate token; added `return` after `requireAuth()` to prevent fall-through.
