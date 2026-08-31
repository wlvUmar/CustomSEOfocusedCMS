# Project 14 — Infra, Deploy & Ops

> Whole-project audit — no fixes.

## 1. `watcher.py` plaintext credentials + syncs secrets
- **Location:** `watcher.py:14-18` `REMOTE_IP=192.168.100.7`, `USERNAME=umar`, `PASSWORD=getout04` committed; `IGNORE_FILES` omits `.env, *.sql, logs/*` → auto-uploads secrets (`watcher.log:602`).

## 2. `watcher.py` crashes — undefined `LOCAL_PATH` + list vs set
- **Location:** `watcher.py:205` `cwd=LOCAL_PATH` undefined → `NameError`; `130 pending_changes=[]` but `259 .add()` + `278 .clear()` vs `312 append` → `AttributeError`.

## 3. `.htaccess` root only 3 lines, never applied when docroot is `public/`
- **Location:** `.htaccess:1-3` — no Rewrite, no blocking of `config/`, `core/`, `.env` if `DocumentRoot` is project root; path `appliances` outdated.

## 4. `public/.htaccess` `ErrorDocument` points to non-existent `public/error.php`
- **Location:** `public/.htaccess:89-93` `ErrorDocument 404 /error.php` but real is `views/error.php` → 404 on error.

## 5. `.cpanel.yml` deploy overwrites secrets, no atomicity
- **Location:** `.cpanel.yml:4-15` `cp -R public/. $PUBLICPATH` overwrites `public_html` without preserving `.env/storage/logs`; no `composer install/npm build/php migrate`, no `set -e`, no rollback, `deploy.log` unbounded.

## 6. `config/init.php` session storage on disk unencrypted, GC 1%
- **Location:** `config/init.php:90-98,118` `storage/admin_sessions` `0750` but `0640` logs, `gc_probability 1/divisor 100` on 14-day files → stale accumulate; no external cron pruning; `mkdir` race.

## 7. Sitemap `lastmod` lies + host poisoning
- **Location:** `controllers/SitemapController.php:30-36,72,260-283` `rotationMonthTs = strtotime(date('Y-m-01'))` always first of month, not update time; `siteBaseUrl()` fallback `HTTP_HOST` when `BASE_URL` empty → internal IP URLs, poisoned cache.

## 8. No backup / cron documented
- **Location:** `core/crons.php:2-42` hardcoded `/home/kuplyuta/.env`, `core/cache/` not in repo, auto `checkAndCacheBotHealth()` on include side-effect; `cleanupDedupTables()` runs ~0.01% of requests (`random 1/100 + 3600` double-gate) no fallback cron; no backup for DB/`uploads`.

## 9. `package.json` bloat + dead tooling
- **Location:** `package.json:6-14` `php-parser` in `dependencies` not `devDeps`, `terser` never used (no `build:js`), `private:true` but repo public, no `browserslist/autoprefixer`, `?v=2` only on admin.

## 10. `.gitignore` rules violated but files present
- **Location:** `.gitignore:5-50` `/logs/*.log` but `logs/tracking_audit.log` tracked; `*.sql` but `kuplyuta_db.sql` present; `*.env` but `.env` present; `/beta` but `beta/` on disk stale artifact.
