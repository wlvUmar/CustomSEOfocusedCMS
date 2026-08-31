# Project 12 — Migrations & Schema

> Whole-project audit — no fixes.

## 1. `migrate.php` ignores `.sql` migrations
- **Location:** `migrate.php:53` `glob($dir.'/*.php')` — 6 `*.sql` files (`2026_05_18_add_utm_source.sql` etc.) never executed.

## 2. Mixed charsets
- **Location:** `kuplyuta_db.sql:45,82` `utf8mb4_general_ci` vs `utf8mb3_unicode_ci` vs `utf8mb4_unicode_ci` — `utf8mb3` deprecated, collation fragmentation breaks joins.

## 3. Views with DEFINER break on other hosts
- **Location:** `kuplyuta_db.sql:1096-1116` `DEFINER=kuplyuta@localhost SQL SECURITY DEFINER` — import on different user/host fails.

## 4. Missing tables in dump
- **Location:** `kuplyuta_db.sql:210-242` — lacks `gsc_data, gsc_tokens, ai_sessions, page_revisions` — requires migrations to be complete but runner ignores `.sql`.

## 5. Duplicate dedup tables
- **Location:** `migrations/003_add_dedup_tables.php:31` vs `2026_07_19_add_dedup_tables.sql:1` vs `kuplyuta_db.sql:181` — drift; `004_add_timestamps_to_dedup.php` `ADD COLUMN` not `IF NOT EXISTS` → rerun fails.

## 6. `DROP INDEX IF EXISTS` syntax fails on older MariaDB
- **Location:** `migrations/001_utm_source_columns.php:17` `DROP INDEX IF EXISTS idx_utm_source` — MySQL <8.0.13 syntax error.

## 7. No DOWN migration, no checksum
- **Location:** `migrations/*` — no `DOWN`, no verification in `schema_migrations`; concurrent runners double-apply (`migrate.php:65` no lock).

## 8. `create_request_access_tokens_table.php` returns void
- **Location:** `migrations/create_request_access_tokens_table.php:8` `$pdo = require config/database.php` — that file doesn't return PDO → `$pdo` is `1` (include return).

## 9. `kuplyuta_db.sql` commits users + hashes
- **Location:** `kuplyuta_db.sql:638` `users` with password hashes, `bot_users`, `seo_settings` — secret exposure in VCS.

## 10. `.sql` `DROP INDEX` on non-existent index
- **Location:** `migrations/2026_07_19_fix_utm_source_data_loss.sql:14-15` `DROP INDEX idx_utm_source` — index never existed in dump, migration fails on clean import.
