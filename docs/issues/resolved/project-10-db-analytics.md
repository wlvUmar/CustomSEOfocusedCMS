# Project 10 — DB Core & Analytics

> **Status 2026-08-30: 10/10 FIXED.**

## 1. Singleton not double-checked, clone/wakeup open — ✅ FIXED
- **Location:** `core/Database.php:70-75` `getInstance()` no lock, no `__clone/__wakeup`
- **Fix:** Added `private __clone()` and `public __wakeup() { throw LogicException }` `Database.php:76`. PHP single-threaded so no double-checked lock needed; clone/unserialize now blocked.

## 2. `getConnection()` bypasses retry — ✅ FIXED
- **Location:** `core/Database.php:77-79` exposes raw PDO bypassing `ensureAlive()`
- **Fix:** `getConnection()` now calls `ensureAlive()` `Database.php:78` before returning PDO. Still exposes PDO but connection is validated.

## 3. Transaction retry corrupts atomicity — ✅ FIXED
- **Location:** `core/Database.php:81-96` retries once unconditionally without `inTransaction()` check
- **Fix:** Captures `$inTxn = $pdo->inTransaction()` before try `Database.php:82`; retry only if `!$inTxn && isConnectionLoss()`. Transactions never retried automatically, preserving atomicity.

## 4. `INTERVAL ? MONTH` placeholder unportable + mixed raw interpolation — ✅ FIXED
- **Location:** `models/Analytics.php:11-34` + `591,1027` — `INTERVAL ? MONTH` may fail on strict MySQL
- **Fix:** All analytics methods now cast `$months = max(1,min(24,(int)$months))` and interpolate as `INTERVAL {$months} MONTH` (int-safe). No placeholder. `getMonthlyData`, `getPageStats`, `getTotalStats`, `getSitewide*`, `getDailyChartData`, `getWeeklyChartData`, `getTopPerformers`, `getTopUtmSources`, `getLinkEffectiveness`, etc. all unified `Database.php:81` — no param for interval.

## 5. Weekly aggregation uses 3 different week starts — ✅ FIXED
- **Location:** `models/Analytics.php:265,532,667` `WEEKDAY(date)` (Mon) vs `YEARWEEK(date,1)` vs `DAYOFWEEK(date)-1` (Sun)
- **Fix:** Unified all to Monday start: `WEEKDAY(date)` + `YEARWEEK(date,1)` `Analytics.php:264,531`. `getRotationWeeklyDataLast` changed `DAYOFWEEK-1` → `WEEKDAY` and `YEARWEEK(a.date)` → `YEARWEEK(a.date,1)` `Analytics.php:665`. Charts now consistent Monday.

## 6. `M j` keys collide across year boundary — ✅ FIXED
- **Location:** `models/Analytics.php:328-344,418-434` pre-fill `M j` without year → overwrite when range crosses Jan 1
- **Fix:** `getSitewideRangeChartData` + `getRangeChartData` now detect `$spansYear` and use `M j Y` label when spanning year `Analytics.php:327`, summing collisions `+` instead of overwrite. `getDailyChartData`/`getWeeklyChartData` aggregate with sum `Analytics.php:509`.

## 7. `LAG() OVER` requires MySQL 8, fails on 5.7 — ✅ FIXED
- **Location:** `models/Analytics.php:1363-1373` `getGrowthTrends()` window function fatal on older hosts
- **Fix:** Wrapped `LAG() OVER` in try/catch `Analytics.php:1364`; fallback PHP manual lag computes `prev_visits/prev_clicks` via loop when exception thrown. Host MariaDB 11.4 still uses window path.

## 8. Analytics internal links map ambiguous `slug` key — ✅ FIXED
- **Location:** `models/ai/tools/AnalyticsTools.php:214-245` `from_slug ?? to_slug` → single `slug` ambiguous
- **Fix:** Split mapper into `$mapInbound` returning `from_slug` and `$mapOutbound` returning `to_slug` `AnalyticsTools.php:214`. Inbound/outbound no longer ambiguous for UI.

## 9. Function on column kills index — ✅ FIXED
- **Location:** `models/ai/tools/AnalyticsTools.php:169` `DATE(CONCAT(am.year,'-',am.month,'-01')) >= DATE_SUB...` prevents index on monthly table; plus many `models/Analytics.php` same pattern
- **Fix:** Replaced all `DATE(CONCAT...)` with index-friendly `(year*12 + month) >= (YEAR(DATE_SUB(...))*12 + MONTH(...))` `Analytics.php:590,804,1192,1210,1225,1257,1375,1410,1453,1475,1495,1613` and `AnalyticsTools.php:176` underperforming LEFT JOIN condition. Uses composite index on `(year,month)`.

## 10. `LIMIT ?` quoted as string may fail — ✅ FIXED
- **Location:** `models/Analytics.php:1083` `LIMIT ?` with PDO may quote as `'20'`
- **Fix:** `getTopPerformers` now interpolates `LIMIT {$limit}` with int cast `Analytics.php:1082`; `getPopularPaths` `LIMIT {$limit}` `Analytics.php:1195`; `getTopUtmSources` `LIMIT {$limit}` `Analytics.php:1653`. All limits are `max(1,min(N,(int)$limit))` sanitized before interpolation.
