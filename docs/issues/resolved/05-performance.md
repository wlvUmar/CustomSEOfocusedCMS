# 05 — Performance & Optimization Issues

> Audit only, no fixes.

## 1. No token budgeting — context can exceed model window
- **Location:** `controllers/admin/AiStudioController.php:62-68,518-537`
- **What:** Single message 8000 cap, per-history 4000×48=192k, plus tool results 12k×8=96k, plus 5k prompt. No `tiktoken` count.
- **Why it matters:** DeepSeek 64k tokens (~250k chars) easily exceeded; API truncates or errors, agent loop stalls with `finish_reason: length` not surfaced.

## 2. Tool result 12k cap still huge per turn
- **Location:** `controllers/admin/AiStudioController.php:324-326`
- **What:** Each `role: tool` result truncated at 12000 chars, SSE summary at 300, but model still receives 12k.
- **Why it matters:** 8 turns ×12k = 96k fed to model; `get_page` 12k + `get_section` untruncated stack, slow + costly.

## 3. `get_tool_logs` reads entire 10MB file per call
- **Location:** `models/ai/tools/MemoryTools.php:192-195`
- **What:** `file_get_contents` on `logs/ai-studio.log` (rotates at 10MB) then `explode("\n")`, slice last 20.
- **Why it matters:** Repeated by agent in loop, kills I/O; file grows between rotations with no compression.

## 4. `list_pages` loads full table
- **Location:** `models/ai/tools/PageTools.php:411-427`
- **What:** `$model->getAll(true)` then `array_slice($rows,0,$limit)`.
- **Why it matters:** No DB `LIMIT` pushdown; with 500 pages, every call scans all rows + builds 500 arrays.

## 5. GSC cache wipe causes thundering herd
- **Location:** `models/GscClient.php:352-356` + `171-180`
- **What:** On 401/403, `clearCache()` deletes `storage/gsc_cache/*.json` entirely, plus `UPDATE gsc_tokens SET access_token=NULL`.
- **Why it matters:** All subsequent `searchAnalytics` re-fetch live API concurrently, hitting 429 burst with only 1 retry `models/OpenRouter.php:152`.

## 6. JS preview parsing per section per turn
- **Location:** `public/js/admin/ai-studio.js:514-557` `buildCombinedPreview` + `extractPreviewFragment`
- **What:** Each `preview` event `DOMParser.parseFromString(html)` + `querySelector('.content-body')` + string concat for stacked sections.
- **Why it matters:** 8 sections stacked repeatedly O(n×sections); layout thrash on large HTML.

## 7. 16-minute PHP worker hold after client Stop
- **Location:** `controllers/admin/AiStudioController.php:109-110` + `public/js/admin/ai-studio.js:934-948`
- **What:** `ignore_user_abort(true)` + `set_time_limit(0)` + `CURLOPT_TIMEOUT 120` per turn ×8 = 960s. JS watchdog 180s aborts client, server keeps burning tokens.
- **Why it matters:** Worker exhaustion, cost leakage, no server cancel on `AbortError`.

## 8. Tool definitions rebuilt each turn
- **Location:** `models/ai/AiToolRegistry.php:63-75`
- **What:** `definitions()` array_merge across 8 classes called twice per `definitionsForMode`, every loop turn `AiStudioController.php:167`.
- **Why it matters:** Redundant allocations; `definitionsForMode('plan')` filters 30+ tools via linear scan per turn.

## 9. `auto_sectionize` byte→char conversion is O(n²)
- **Location:** `models/ai/tools/PageTools.php:1033-1034`
- **What:** `PREG_OFFSET_CAPTURE` byte offsets + `mb_strlen(substr(...))` conversion per match, then `mb_substr` later with drift for multibyte.
- **Why it matters:** Large `content_ru` Uzbek Cyrillic pages mis-split, proposals offset wrong.

## 10. No model context caching or compression
- **Location:** `controllers/admin/AiStudioController.php:139-144` + `models/OpenRouter.php:236-243`
- **What:** Full `messages` (system+history+pending+tool results) JSON-encoded per turn, no prompt cache header, no history summarization.
- **Why it matters:** Same 5k system prompt resent 8×, paying input tokens each time; OpenRouter prompt caching not used.
