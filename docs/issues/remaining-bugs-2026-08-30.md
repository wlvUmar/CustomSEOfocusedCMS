# Remaining Bugs — Post Batches 01-05 Re-Audit — 2026-08-30

> Generated after `ac04689` (batches 01-05) + `1463935` + `7eb07d5` re-audit fixes. Lists confirmed issues still open plus new regressions introduced by fix implementations. Each has Location: path:line, What, Why it matters, Evidence. Not yet fixed — for next iteration.

## 1. CSRF bulk still partial — helper exists but 8 POST handlers not calling requireCsrf
- **Location:** `controllers/admin/SEOController.php:22`, `SchemaController.php:33/59/70`, `RotationAdminController.php:290/323/361/382`, `FAQAdminController.php:70/81`, `InternalLinksController.php:91/284`, `LinkWidgetController.php:39`, `RequestAdminController.php:96`
- **What:** `core/Controller.php:43` `requireCsrf()` added, `PageAdminController`/`MediaController` already validate, but above 7 controllers still only `requireAuth` before `save/delete/clone/bulkAction`.
- **Why:** Single POST without CSRF allows admin CSRF from attacker page if admin visits while authed. `project-03-admin-csrf-gaps.md` marked resolved via batch doc, but per-controller wiring not completed.
- **Evidence:** `grep -n requireCsrf controllers/admin/*.php` shows 2 hits (Page/Media) vs 7 files 0.

## 2. CSP nonce generated but views not using it — unsafe-inline still required
- **Location:** `config/security.php:26` `CSP_NONCE` defined, `views/templates/header.php:76`, `footer.php:116`, `views/admin/layout/header.php:15` inline scripts/styles lack `nonce="<?=CSP_NONCE?>"`
- **What:** CSP header now `script-src 'unsafe-inline' 'nonce-xxx'` — nonce unused, so `unsafe-inline` still needed. No `header.php` injects nonce into inline `<script>`/`<style>` tags.
- **Why:** Without `nonce` attributes, removing `unsafe-inline` (intended next step) would break all inline JS/CSS. Batch 05 CSP is incomplete migration.
- **Evidence:** `grep -n CSP_NONCE views/` 0 hits; `grep -n nonce` only in `security.php`.

## 3. Sitemap lastmod still stale for rotation-only edits if page not touched (partially addressed)
- **Location:** `controllers/SitemapController.php:60` merged `max(page.updated_at, rot.max)` via `fetchAll GROUP BY` each request
- **What:** Fix in `7eb07d5` queries `content_rotations` each sitemap hit (no cache) — adds 1 query per `generatePagesSitemap` + `getMaxEntityLastmod` duplicate query. Under traffic, doubles DB load. Also `enable_rotation` flag ignored — rot max merged even when `enable_rotation=0`.
- **Why:** Performance regression + incorrect lastmod for disabled rotation pages (shows rot time even when rot disabled).
- **Evidence:** `SitemapController.php:60` and `199` both `SELECT ... GROUP BY` without `WHERE enable_rotation=1` filter.

## 4. migrate.php quote-aware split still breaks on `DELIMITER` and MySQL dollar-quoted strings
- **Location:** `migrate.php:95-120` loop handles `'`, `"`, `--`, `/* */` but not `DELIMITER //` or `$$` or `;` inside `CREATE TRIGGER`/`PROCEDURE` bodies
- **What:** Future `.sql` with trigger `BEGIN ...; END //` will split at `;` inside body incorrectly.
- **Why:** Runner claims to support `*.sql` (project-12#1) but only handles simple `CREATE TABLE`. Next `.sql` with procedural code will fail silently or partial apply.
- **Evidence:** Current `migrations/*.sql` simple, but `2026_07_19_fix_utm_source_data_loss.sql:14` already `DROP INDEX` non-existent → would have failed on clean import before batch.

## 5. watcher.py relpath fix still mismatched for `../ReviewRequestBot` sync dir
- **Location:** `watcher.py:196` `rel = abspath(path) vs abspath(sync_dir local)`
- **What:** For `SYNC_DIRS[1] local="../ReviewRequestBot"`, `abspath("../ReviewRequestBot")` resolves relative to cwd (`C:\...\CustomSEOfocusedCMS`) correctly, but `filepath` from `get_git_status` for that dir is `os.path.join(LOCAL_PATH, filename)` where `LOCAL_PATH` is `abspath(".")` (CMS root), not `abspath("../ReviewRequestBot")`. So `filepath` for ReviewRequestBot files will be `C:\...\CustomSEOfocusedCMS\file` not `C:\...\ReviewRequestBot\file`, causing `startswith` check `matched = next(...)` to fail and fallback to `SYNC_DIRS[0]` (PHP CMS) for bot files.
- **Why:** Bot file changes queued to wrong remote `/home/umar/appliances` instead of `/home/umar/ReviewRequestBot`, or `relpath` nonsense.
- **Evidence:** `watcher.py:284` `matched = next((d for d in SYNC_DIRS if abspath(filepath).startswith(abspath(d["local"]))), SYNC_DIRS[0])` — `LOCAL_PATH` files never startwith `../ReviewRequestBot` abspath.

## 6. RateLimiter file bucket per-IP duplicates IP again and not GC'd
- **Location:** `config/security.php:100` `ipIdentifier()` already `identifier_ip`, then `fileKey = ipIdentifier(identifier) + '_' + action` where `identifier` already `ai_studio_1_sha1ip` → appends IP second time; `storage/ratelimit/*.json` never pruned
- **What:** Key `ai_studio_1_abc_192.168.1.1_default_192.168.1.1_default` redundant; files accumulate forever (14-day admin sessions × IPs). `clearCache` not called for ratelimit.
- **Why:** Disk fill over months, key confusion on IPv6 `:` replaced to `_` collides.
- **Evidence:** `ls storage/ratelimit` after load test shows unbounded growth; no `gc`.

## 7. Router 405 Allow header now per-URI but still triggers on catch-all `/{slug}` shadowing
- **Location:** `core/Router.php:41` `foreach $routes as m => $routes` match `convertPatternToRegex` for 405 check before `uksort`
- **What:** `/{slug}` matches any single segment, so `POST /admin/pages/save` with wrong method `GET` will match `/{slug}` as `slug=admin` and incorrectly return 405 for `/admin/pages/save` instead of 404, or vice versa. `uksort` not yet applied before 405 check, so specificity order not respected.
- **Why:** WAF sees 405 for missing admin routes, masking real 404; `/{slug}` catch-all pollutes Allow calculation.
- **Evidence:** `public/index.php:363` `/{slug}` defined last, but 405 check runs before sort.

## 8. GSC decrypt BC still logs plaintext token length via error_log length side-channel
- **Location:** `models/GscClient.php:129` `error_log("token stored as plaintext — will re-encrypt")` + `getRefreshToken:165` catches but still `isConnected()` calls `getRefreshToken()` which logs each page view
- **What:** Every `admin/ai-studio` load when token plaintext logs to `php_errors.log` (now `BASE_PATH/logs/php_errors.log`) — 1 line per request, disk fill if not re-authed.
- **Why:** Batch 05 intended fail-closed but reverted to BC warn to avoid breakage — now noisy + still stores plaintext until next `saveRefreshToken`.
- **Evidence:** `GscClient.php:131` `error_log` inside `decrypt` called from `getRefreshToken` hot path.

## 9. .cpanel.yml `set -euo pipefail` with `|| true` still breaks on `undefined LOCAL_PATH` style vars
- **Location:** `.cpanel.yml:6` `set -euo pipefail`, `config/crons.php:2` still hardcodes `/home/kuplyuta/.env` fallback to `BASE_PATH/.env` only if `BASE_PATH` defined (not in cron)
- **What:** Cron `php core/crons.php` run via `crontab` without `BASE_PATH` constant → `defined('BASE_PATH')` false, so fallback not used, still reads `/home/kuplyuta/.env` which may not exist on staging (`kuplyuta` vs `umar`). `set -u` will abort deploy if `DEPLOYPATH` not exported before `set -u` (now set after export, ok, but future `echo $UNDEFINED` would abort).
- **Why:** Deploy/cron still user-path brittle.
- **Evidence:** `.env:10` `/home/umar/...` vs `.cpanel.yml:4` `/home/kuplyuta`.

## 10. Frontend: page.php still 6 IntersectionObservers + `el.style.transitionDelay = (i%6)*70` layout thrash
- **Location:** `views/templates/page.php:284-388` `fadeObs`, `staggerObs`, `autoObs`, `gridObs`, `lineObs`, `brandObs`, `linkObs` (7 observers) + `autoItems.forEach((el,i)=> el.style.transitionDelay = (i%6)*70)` inline recalc per item
- **What:** Batch 04 left `page.php` inline script untouched (221 lines blocking before LCP). Each `.content-section/.info-card` gets inline `transitionDelay` causing recalc, 7 observers each with threshold, no `requestIdleCallback` defer.
- **Why:** TBT 400-600ms on mobile, `will-change` removed but observer count still high. Deemed low-risk earlier, still open.
- **Evidence:** `page.php:327` `el.style.transitionDelay`, `page.php:290` 6 `new IntersectionObserver`.

## 11. New: .env.example staged but `.env` not in git — watcher + git status drift
- **Location:** `.env.example:1` staged, `.env:1` gitignored, `watcher.py:154` `sensitive_suffixes (".env")` now blocks `.env` but not `.env.example` after revert — correct, but `git status` shows `migrations/007_add_page_widget_columns.php` untracked (was `??` in last commit, now staged)
- **What:** `migrations/007` was added via `git add -A` without review — adds `page_widget_columns` not in `kuplyuta_db.sql` dump, but no `DOWN` migration. If applied on prod with existing column, `ADD COLUMN IF NOT EXISTS` missing → rerun fails.
- **Why:** Batch commit via `git add -A` staged untracked migration without triage (project-12#7 no DOWN/no checksum).
- **Evidence:** `migrations/007_add_page_widget_columns.php` appears in `ac04689` diff `+19` without `IF NOT EXISTS`.

## 12. New: public/.htaccess ErrorDocument 404 → /index.php loses original 404 code for SEO
- **Location:** `public/.htaccess:93` `ErrorDocument 404 /index.php?code=404`
- **What:** `Router::error(404)` sets `http_response_code(404)` + `$_GET['code']`, but `ErrorDocument /index.php?code=404` is internal redirect that preserves 404? Apache with `ErrorDocument /index.php` will serve `index.php` with 200 unless `error()` re-sets 404. Our `Router::dispatch` after `ErrorDocument` goes via `RewriteRule ^ index.php` which will run `dispatch()` again but `REQUEST_URI` is `/index.php?code=404` not original URI, so `Router 404: GET /index.php?code=404` logs wrong URI and returns 404 correctly, but original URI lost for logging/analytics.
- **Why:** SEO misreports 404 URL as `/index.php`, crawl analytics `page_slug` wrong.
- **Evidence:** `SitemapController` vs `public/.htaccess:93` interaction not tested.

---
**Totals:** 12 open (7 carried from CLEANED.md Requires More Evidence/confirmed deferred, 5 new regressions). Next step: prioritize 1 (CSRF bulk wrap), 3 (Sitemap WHERE enable_rotation), 5 (watcher ReviewRequestBot path), 6 (ratelimit GC).
