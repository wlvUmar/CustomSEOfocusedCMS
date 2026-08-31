# Project 02 — Security: Auth, CSRF & Rate Limit

> Whole-project audit — no fixes.

## 1. Controller `$$key` variable-variable overwrite
- **Location:** `core/Controller.php:9-16` `$$key = $value` in `view()`
- **What:** Attacker-controlled `$data` keys (`db`, `router`, `_SESSION`) can overwrite locals if controller passes `$_POST`.

## 2. `redirect()` CRLF injection
- **Location:** `core/Controller.php:19-22` `header("Location: ".BASE_URL.$url)` without sanitize; `redirect_after_login` at `Controller.php:39` stores `REQUEST_URI` unsanitized.

## 3. `requireAuth()` only checks `user_id`
- **Location:** `core/Controller.php:31-42`
- **What:** No role, no IP binding, no fingerprint; `HTTP_X_REQUESTED_WITH` spoofable; `$_SESSION['redirect_after_login']` open-redirect via `//evil.com`.

## 4. CSP neutralized by `unsafe-inline`
- **Location:** `config/security.php:15-29`
- **What:** `script-src`+`style-src` contain `'unsafe-inline'` + `https:` wildcard + `blob:/data:` — XSS protection effectively disabled.

## 5. CSRF single per-session token never rotated
- **Location:** `config/security.php:36-45` `generateCSRFToken()` stored once per session, never per-form, never expires, never bound to action.

## 6. RateLimiter is per-session, not per-IP
- **Location:** `config/security.php:55-82` `RateLimiter::check()` stores `$_SESSION["ratelimit_..."]`
- **What:** Clearing cookies resets bucket; race on concurrent requests; `die()` emits 200 with HTML body despite 429.

## 7. Session switch via REQUEST_URI
- **Location:** `config/init.php:86,90-95` `$isAdmin = preg_match('#^/admin#', REQUEST_URI)` missing `(/|$)` anchor; matches `/admin-malicious`, `?x=/admin`; `session_save_path` + `session_name` switched per-request not per-session → fixation.

## 8. `secure` cookie ignores proxy headers
- **Location:** `config/init.php:99-105` `secure=>!empty($_SERVER['HTTPS'])` ignores `X-Forwarded-Proto`/`CF_VISITOR` → cookie sent over HTTP behind Cloudflare.

## 9. Session regeneration every 24h only
- **Location:** `config/init.php:122-127` `last_regeneration >86400`
- **What:** Fixation window 24h; not on privilege escalation.

## 10. Idle timeout uses current request's `isAdmin` not session origin
- **Location:** `config/init.php:130-139` `timeout = $isAdmin ? 14days : 1440` — admin visiting `/` times out in 24m, visitor visiting `/admin` gets 14 days.
