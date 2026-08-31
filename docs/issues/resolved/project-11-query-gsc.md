# Project 11 — Query Builder, GSC & OpenRouter

> Whole-project audit — no fixes.

## 1. `DENIED_KEYWORDS` blocks legitimate `UNION`
- **Location:** `models/ai/tools/AnalyticsQueryTools.php:27-32` `UNION, SET, INTO` denial blocks analytics comparisons; `UNION` bypass via comment not but still overly broad.

## 2. `LIMIT`/`OFFSET` capping order double-caps, 10k still DoS
- **Location:** `models/ai/tools/AnalyticsQueryTools.php:97-115` `preg_replace LIMIT` single pass; `LIMIT 10,20` comma handled separately double-caps; `OFFSET 10000 LIMIT 50` scans 10k rows.

## 3. Raw `$sql` without params after `assertSafe`
- **Location:** `models/ai/tools/AnalyticsQueryTools.php:117` `Database->query($sql)->fetchAll()` — if bypassed, SQLi.

## 4. `last_30_days` approximated as `INTERVAL 1 MONTH` on monthly table
- **Location:** `models/ai/tools/AnalyticsQueryTools.php:147-212` semantic mismatch daily vs monthly.

## 5. GSC `encKey` reuses `BOT_API_SECRET`
- **Location:** `models/GscClient.php:75-82` fallback `GSC_ENCRYPTION_KEY ?: BOT_API_SECRET` — key reuse, `hash('sha256',key,true)` weak KDF not HKDF.

## 6. `decrypt` downgrade — plaintext row accepted
- **Location:** `models/GscClient.php:104-115` if `stored` not `gcm$` returns plaintext → attacker inserts plaintext row bypasses encryption.

## 7. File lock may block forever + stale
- **Location:** `models/GscClient.php:189-195` `flock(LOCK_EX|LOCK_NB)` then blocking `flock(LOCK_EX)` — holder death never cleaned.

## 8. Cache TOCTOU + unbounded growth
- **Location:** `models/GscClient.php:284-300` `cacheGet` without `LOCK_SH` vs `cacheSet` `LOCK_EX` race; `clearCache` only on 401/403 → disk fills, `glob+unlink` symlink not verified.

## 9. `fetchModels` cache no lock, stale on key rotation
- **Location:** `models/OpenRouter.php:24-57` `storage/openrouter_models.json` 600 TTL no lock, `mkdir 0750` race, no eviction on key change.

## 10. `MODELS` const blocks live models
- **Location:** `models/OpenRouter.php:87-88` `if(!isset(self::MODELS[$model])) $model='deepseek-chat'` prevents using live fetched models not in const.
