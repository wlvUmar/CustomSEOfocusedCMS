# Project 13 — Config, Env & Error Handling

> Whole-project audit — no fixes.

## 1. Manual `.env` parser duplicated with drift
- **Location:** `config/config.php:7-24` vs `config/database.php:4-18` vs `core/crons.php:3-9` — three variants, `trim`/`regex` differ, `=` in value beyond first split lost.

## 2. `.env` committed despite `.gitignore`
- **Location:** `.env:1-14` + `.gitignore:35` `*.env` — `DB_PASS=@iX?z~gukWg`, `BOT_API_SECRET`, `GITHUB_WEBHOOK_SECRET` exposed; `BASE_URL=http://192.168.100.7` leaks LAN IP.

## 3. `IS_PRODUCTION` hardcoded true
- **Location:** `config/security.php:3` `define('IS_PRODUCTION', true)` — cannot flip via env; hides errors even locally, forces HSTS on private IP.

## 4. Security headers duplicated + stale log path
- **Location:** `config/security.php:8-14` vs `public/.htaccess:23-27` — duplicate HSTS; `config/init.php:35` logs to `BASE_PATH/logs/php_errors.log` but `.htaccess:3` to `/home/kuplyuta/appliances/logs/...` mismatch.

## 5. Custom error handler double-logs
- **Location:** `config/init.php:39-58` `set_error_handler` logs then `return false` → falls through to internal handler duplicate.

## 6. Exception handler may re-enter fatally
- **Location:** `config/init.php:60-80` `require views/error.php` inside handler; if view throws (needs DB `SEO` at `views/error.php:15`), fatal loop; headers may already sent via `ob_start`.

## 7. `BASE_URL` empty fallback → host-header poisoning
- **Location:** `config/config.php:28` `BASE_URL` fallback `''` → `helpers.php:109-113` falls back to `$_SERVER['HTTP_HOST']` unsanitized.

## 8. `tracking_audit.log` PII not rotated, committed
- **Location:** `logs/tracking_audit.log:1-71` + `core/helpers.php:43-85` — full `HTTP_COOKIE`, `GA`, `PHPSESSID`, `trk_visitor_id`, IPs; `TRACKING_AUDIT_ENABLED=false` but file exists; `.gitignore:5` ignored but tracked.

## 9. `UPLOAD_PATH` mismatched users
- **Location:** `.env:10` `/home/umar/...` vs `.cpanel.yml:4` `/home/kuplyuta` vs `core/crons.php:2` `/home/kuplyuta/.env` → deploy writes to wrong path / permission denied.

## 10. `GSC_ENCRYPTION_KEY` fallback to `BOT_API_SECRET`
- **Location:** `config/config.php:60-63` `GSC_ENCRYPTION_KEY ?: BOT_API_SECRET` — key reuse across domains; empty default allows unauthenticated GSC flow.
