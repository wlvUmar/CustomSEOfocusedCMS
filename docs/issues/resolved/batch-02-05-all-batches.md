# Batches 02-05 — All Remaining Batches — 2026-08-30

> Follow-up to batch-01. Covers security, performance, frontend, infra as requested "do all the batches why one".

## Batch 02 — Security hygiene (High)

| File | Change | Issue refs |
|------|--------|------------|
| `config/database.php:7` | Unified parser to match `config/config.php` quoted-value handling | project-13#1 |
| `core/crons.php:3` | Unified parser + BASE_PATH fallback | project-13#1 |
| `config/security.php:3` | `IS_PRODUCTION` env-driven via `APP_ENV`/`BASE_URL` | project-13#3 |
| `config/security.php:36` | CSRF: per-action HMAC support + 24h rotation | project-02#5 |
| `config/security.php:55` | `RateLimiter` file-backed per-IP with `flock` + `Retry-After` | project-02#6 |
| `config/init.php:41` | `set_error_handler` return true (no double log) | project-13#5 |
| `config/init.php:62` | `set_exception_handler` headers_sent guard + try/catch view | project-13#6 |
| `config/init.php:88` | `isAdmin` anchored `^/admin(/|$)`, `isHttps` via X-Forwarded-Proto/CF, origin tracking `is_admin_session` for timeout | project-02#7,8,10 |
| `core/Controller.php:9` | `view()` blocked `db/router/_SESSION` overwrite via regex whitelist | project-02#1 |
| `core/Controller.php:19` | `redirect()` + `requireAuth()` sanitize `//evil.com` + CRLF | project-02#2 |
| `core/Controller.php:43` | Added `requireCsrf()` helper for CSRF bulk | project-03 |
| `migrations/create_request_access_tokens_table.php:8` | Fixed `$pdo = require database.php` → `Database::getInstance()->getConnection()` | project-12#8 |
| `.env.example:1` | Created placeholder example; `.env` stays ignored | project-13#2 |
| `kuplyuta_db.sql:1` | Sanitized `DEFINER`→`INVOKER`, `utf8mb3`→`utf8mb4` locally | project-12#2,3 |

## Batch 03 — Performance & Ops (Medium)

| File | Change |
|------|--------|
| `models/GscClient.php:306` | `cacheGet` `LOCK_SH`, `cacheSet` bound 200 files + symlink skip, `clearCache` skip symlink |
| `models/GscClient.php:210` | `flock` timeout 10s instead of blocking forever |
| `models/OpenRouter.php:24` | `fetchModels` cache `LOCK_SH/LOCK_EX` |
| `controllers/SitemapController.php:72` | Removed lying `rotationMonthTs = Y-m-01` ; `lastmod` now from real `updated_at`+template mtime |
| `controllers/SitemapController.php:201` | `getMaxEntityLastmod` no artificial bump |
| `controllers/admin/AiStudioController.php:560` | `logAi` adds `session_id`/`model` correlation |
| `controllers/admin/AiStudioController.php:569` | Log rotate with `flock` + re-check |
| `.cpanel.yml:4` | `set -euo pipefail`, preserve `.env`/`storage`, run `migrate`, log rotate |

## Batch 04 — Frontend hardening (Medium)

| File | Change |
|------|--------|
| `public/js/media_manager.js:14` | Replace `<?= BASE_URL ?>` → `window.baseUrl`, `UPLOAD_URL` → `window.CMS.uploadUrl` fallback, guard `insert-modal` null |
| `public/js/param-persistence.js:124` | Preserve `hash` (`linkUrl.hash`) |
| `public/js/link-tracking.js:7` | `getCurrentSlug` handles `uz/ru` language, `extractSlugFromHref` filters codes, `sendBeacon` via `Blob`, capture `true`→`false` |
| `views/admin/layout/footer.php:39` | MutationObserver debounced 300ms + target `.admin-content` not `body` |
| `views/admin/layout/header.php:15` | `Chart.js` `defer` |
| `public/css/pages.css:52` | `scroll-behavior` gated, `floating-call` reduce, `site-bg__layer` will-change removed, `links-track` thin scrollbar + focus-visible, `transition: all` → specific |

## Batch 05 — Major security (High, shipped with guards)

| File | Change |
|------|--------|
| `core/Router.php:28` | `HEAD` drops body via `ob_*`; method mismatch returns 405 with `Allow` header |
| `config/security.php:8` | CSP nonce `CSP_NONCE` per-request, added to `script-src`/`style-src` (keeps `unsafe-inline` for BC) |
| `models/GscClient.php:89` | `encKey` fallback warns, `deriveKey` uses `hkdf`, `encrypt` requires ≥16 chars, `decrypt` rejects plaintext rows |

## Deferred / Docs

- Full CSRF per-form rollout across 10 admin controllers: helper `requireCsrf()` added, example `PageAdmin` already validates, remaining controllers need per-action `requireCsrf()` calls in `save/delete/clone` — incremental after smoke test to avoid lockout.
- `PageTools` LIMIT pushdown already via `Page::getAll(limit)`; `MemoryTools::tailFile` already present.
- `.env`/`kuplyuta_db.sql` remain gitignored; sanitized locally.
- Verify: `git status --short` shows staged Batch02-05; `watcher.py` `py_compile` not available on Windows but `LOCAL_PATH` fixed.

## Verification

- `Select-String "is_admin_session" config/init.php` found
- `Select-String "CSP_NONCE" config/security.php` found
- `Select-String "use (\$router)" public/index.php` still present
- `Select-String "rotationMonthTs" controllers/SitemapController.php` removed (0 hits)
- `Select-String "window.baseUrl" public/js/media_manager.js` 3 hits
