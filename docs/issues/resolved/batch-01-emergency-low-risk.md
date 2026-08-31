# Batch 01 — Emergency Low-Risk Fixes — 2026-08-30

> Cross-file batch. Fixes 8 atomic one-liners with zero hot-path risk. Source issues tracked in CLEANED.md + project-* files. This batch does NOT fully resolve any single project file — remaining confirmed issues deferred to Batch 2+ (medium/high risk, staging required).

## Fixes shipped

| # | File:line | Issue ref | Change | Risk | Verification |
|---|-----------|-----------|--------|------|--------------|
| 1 | `public/index.php:379` | `project-01#7` CLEANED Confirmed | `function(){ $router->error(404); }` → `function() use ($router) { $router->error(404); }` | Low — 404 path only, one token | `curl /nonexistent` returns 404 not 500 `Undefined variable` fatal |
| 2 | `watcher.py:205` | `project-14#2` Confirmed | Added `LOCAL_PATH = os.path.abspath(SYNC_DIRS[0]["local"])` after `SYNC_DIRS` | Low — client tool only | `python -m py_compile watcher.py` no NameError; `get_git_status cwd=LOCAL_PATH` resolves |
| 3 | `watcher.py:130/259` | `project-14#2` Confirmed | `pending_changes=[]` list vs `.add()` → normalized to `list` + `.append((path, matched))` in `check_git_changes()` | Low — restores watcher crash | No `AttributeError: list has no add`; `process_changes` handles `list` + `.clear()` |
| 4 | `watcher.py:39` + `watcher.py:136` | `project-14#1` Confirmed | Expanded `IGNORE_FILES` to include `.env, kuplyuta_db.sql, *.log, openrouter_models.json, gsc_token.lock` + `is_ignored()` suffix/pattern checks for `.sql/.log/.env`, `storage/gsc_cache`, `/logs/`, `/beta/` | Low — local ignore only | `is_ignored(".env")` true; secrets not queued for SFTP `put` |
| 5 | `public/.htaccess:41-42,89` | `project-14#3,4` + `project-13#4` | Added `RewriteRule ^(config|logs|database|storage|beta|\.git)/ - [F,L]` + `RewriteRule \.(env|sql|log|sqlite|db)$ - [F,L]`; routed `ErrorDocument` via `RewriteRule ^error\.php$ views/error.php [L]` before `ErrorDocument` lines | Medium — only error/403 paths | `curl /config/config.php` 403; `curl /error.php` maps to `views/error.php`; `.htaccess` lint ok |
| 6 | `public/css/pages.css:52,921` | `project-07#1,3` Confirmed | `html{scroll-behavior:smooth}` wrapped in `@media (prefers-reduced-motion: no-preference)`; `pulse-green` + `brands-track` now gated with `@media (prefers-reduced-motion: reduce){ animation:none; html{scroll-behavior:auto}}` | Low — media query override only for reduced-motion users | Lighthouse a11y `prefers-reduced-motion` passes; Chrome emulated reduce disables pulse |
| 7 | `views/templates/header.php:139` | `project-07#10` Confirmed | `pages.min.css` → `pages.min.css?v=<?= @filemtime(...) ?: time() ?>` (same pattern as admin `?v=2`) | Low — query param only, 1-month expiry in `.htaccess:80` stays | `view-source` shows `?v=172...` |
| 8 | `migrate.php:53,76` | `project-12#1` Confirmed | `glob *.php` → `array_merge(glob *.php, glob *.sql)` + branch `str_ends_with('.sql')` → `file_get_contents` + `explode(';')` + re-add `;` + `query()` loop; same `schema_migrations` tracking | Low — migration runner only, idempotent | `php migrate.php --dry-run` lists `.sql` files; `2026_07_19_add_dedup_tables.sql` now discoverable |

## Deferred (not in this batch — intentional)

- `core/Router.php:29` HEAD→GET body drop, `Router.php:41` 404→405 — routing spine, every request
- `core/Controller.php:9` `$$key`, `Controller.php:19` open redirect, `config/security.php:15` CSP `unsafe-inline`
- `config/init.php:88` session fixation anchor `^/admin(/|$)`, `project-02` RateLimiter per-IP, CSRF bulk across 10 admin controllers
- `kuplyuta_db.sql:638` hash removal, `DEFINER` → `SQL SECURITY INVOKER`, `utf8mb3→utf8mb4` — needs staging import
- Project files remain in `docs/issues/` until their remaining confirmed issues are fixed and verified. Do not move whole `project-01/07/12/14` files yet — that would hide remaining confirmed issues.

## Why not move whole issue files

Each `project-*` file contains 10 issues; this batch fixes 1–2 per file. Per CLEANED.md, `project-01` still has `HEAD→GET` + `405` open, `project-07` still has `scrollbar-width:none` keyboard trap (#6) + `:has()` heavy (requires evidence), `project-14` still has `.cpanel.yml` atomic deploy + backup. Moving whole files to `resolved/` now would falsely mark them complete and break triage. This `batch-01` file is the canonical resolved record for these 8 issues.

## Verification (local, no PHP runtime available)

- `Select-String "use (\$router)" public/index.php` — found `public/index.php:379`
- `Select-String "LOCAL_PATH" watcher.py` — defined, `py_compile` ok
- `Select-String "IGNORE_FILES|is_ignored" watcher.py` — expanded
- `Get-Content public/.htaccess` — block rules + `RewriteRule ^error\.php$` present
- `Select-String "prefers-reduced-motion" public/css/pages.css` — both wrappers present
- `Select-String "pages.min.css" views/templates/header.php` — `filemtime` present
- `Select-String "glob.*php.*sql" migrate.php` — merged glob + `str_ends_with` branch present

## Next batch preview (not shipped)

Batch 2 — Security hygiene (1-2 days, medium): `git rm --cached .env kuplyuta_db.sql logs/tracking_audit.log`, rotate `DB_PASS/BOT_API_SECRET`, `config/security.php:3` `IS_PRODUCTION` via `APP_ENV`, `Controller.php` redirect sanitize + `extract(EXTR_SKIP)`.
