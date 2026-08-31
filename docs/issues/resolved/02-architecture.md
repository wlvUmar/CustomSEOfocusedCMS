# 02 — Architecture & Flow Issues

> Feature: AI Studio — audit only, no fixes.
> **Status 2026-08-30: 7/10 FIXED (1,3,4,5,6,7,8,9), 2 deferred per CLEANED.md (2 requires more evidence, 10 dropped).**

## 1. Duplicated OpenRouter clients — ✅ FIXED 2026-08-30
- **Location:** `models/OpenRouter.php:75-191` (`chat`) vs `216-357` (`chatWithTools`) → now `OpenRouter.php:75-343` with `doRequest()`
- **What:** ~90% curl/retry/error logic duplicated, diverging on `X-Title` (`CMS Page Editor` vs `CMS AI Studio`), reasoning extraction (`reasoning` only vs `reasoning`+`reasoning_details`), `finish_reason` handling.
- **Why it matters:** Drift risk — one path gets a fix, the other doesn't; inconsistent `deepseek-r1` reasoning.
- **Fix:** Extracted private `doRequest(payload, retries, xTitle, apiKey): array` handling curl, jitter, `X-Title` param, transient 429/5xx + `json_decode`. `chat()` now calls `doRequest` with `CMS Page Editor` and extracts `content`+`reasoning`; `chatWithTools()` calls `doRequest` with `CMS AI Studio` and preserves `reasoning_details` `tool_calls` extraction. `reasoning_details` handling now via single path.

## 2. Tool definitions built twice per turn — ⏸️ DEFERRED (CLEANED.md:105 Requires More Evidence)
- **Location:** `models/ai/AiToolRegistry.php:70-75`
- **What:** `definitionsForMode()` calls `self::definitions()` twice (once to filter), and `isPlanAllowed()` linear-scans `PLAN_ALLOWLIST` per tool.
- **Why it matters:** Wasteful per-turn cost; BUILD mode pays PLAN filter anyway.
- **Triage:** Only one call for PLAN; BUILD filter cost ~0.5ms, opcache mitigates. Defer memoization to `05-performance` batch where `OpenRouter`+`definitions` caching bundled.

## 3. PageTools is a God object — ✅ FIXED (incremental) 2026-08-30
- **Location:** `models/ai/tools/PageTools.php:1-1486` → now facade + `PageSectionsHelper.php`
- **What:** 19 tools (list/get/search/sections/chunk/str_replace/set/insert/update/patch/style/wrap/marker/sectionize/batch/revisions) in one class, high cyclomatic complexity.
- **Why it matters:** Hard to test, review, or change one tool without touching the file; batch atomicity relies on same class's helpers.
- **Fix:** Created `models/ai/tools/PageSectionsHelper.php` (splitIntoSections/rebuildContent/findSectionIndex/mergeStyleIntoTag/expandStyleTokens) and made `PageTools` delegate via `require_once PageSectionsHelper`. Facade kept for BC; private helpers `findSectionIndex`, `rebuildContentFromSections`, `mergeStyleIntoTag`, `expandStyleTokens` now proxy to helper, `public splitIntoSections` delegates. Enables per-class testing without breaking `AiToolRegistry::TOOL_CLASSES`.

## 4. 500-line system prompt bakes domain doctrine — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/AiStudioController.php:462-516` → now `AiStudioController.php:507-560` + `PromptDoctrine.php`
- **What:** Prompt embeds Tashkent buyback, RU/UZ parity, Lighthouse 95+, CLS<0.1, `get_design_tokens` values `--teal` etc., plus contradictory operating loops.
- **Why it matters:** Not reusable, duplicates token tool, counts against every turn's context budget.
- **Fix:** Shrank `buildSystemPrompt()` ~55→~30 lines: moved SEO/DESIGN/DONE to `models/ai/PromptDoctrine.php` constant `SEO_DESIGN_DONE` (outside per-turn budget). Kept conditional operating loop (concrete vs vague) resolving #9. Prompt now points `Call get_design_tokens + get_global_settings for live values; see PromptDoctrine`.

## 5. Triple persistence split-brain — ✅ FIXED 2026-08-30
- **Location:** `public/js/admin/ai-studio.js:122-321` + `controllers/admin/AiStudioController.php:100-102,696-721` + `models/ai/tools/MemoryTools.php:78-124` → now `AiStudioController.php:134-145`
- **What:** `localStorage ai-studio-sessions` + `ai_sessions` DB (`id, history, context JSON`) + `$_SESSION['ai_context']` + `$_SESSION['ai_session_id']` merged ad-hoc.
- **Why it matters:** Desync: JS `currentSessionId` vs PHP `ai_session_id` vs DB `id CHAR(36)`. `loadSessionHistory` merges without dedup.
- **Fix:** Made DB canonical: PHP auto-create now UUID v4 `random_bytes(16)` formatted `8-4-4-4-12` not `bin2hex(8)+uniqid`; `localStorage` cache-only (already deduped in File 1 merge + rebuildTranscript). `ensureAiSessionsTable` stays `CHAR(36)`; regex `^[a-z0-9\-]{8,64}$` allows legacy 22-char IDs.

## 6. Include via string interpolation, no autoloader — ✅ FIXED 2026-08-30
- **Location:** `public/index.php:391-394` `requireAdminController()` + `models/ai/AiToolRegistry.php:8-15` `require_once` → now `core/Autoloader.php` + `config/init.php`
- **What:** Each request `require_once` per tool class via relative `BASE_PATH` strings.
- **Why it matters:** Fragile include order, no PSR-4, IDE can't resolve, dead-code detection blind.
- **Fix:** Added `core/Autoloader.php` with `PageSectionsHelper/PromptDoctrine` map + fallback `models/`/`core/`/`models/ai/tools/`; registered in `config/init.php` (`require Autoloader; Autoloader::register()`). Kept string `require_once` in `AiToolRegistry` as BC fallback with comment. No composer needed.

## 7. Preview executes view templates with globals — ✅ FIXED 2026-08-30
- **Location:** `models/ai/tools/SiteTools.php:295-319` → now `SiteTools.php:340-341` synthetic only
- **What:** `@include BASE_PATH.'/views/templates/header.php'` inside agent context with globals `$page`, `$seo`, side-effects (header/footer echo).
- **Why it matters:** Agent can trigger view PHP that expects front-controller state; errors swallowed by `@`, debugging blind.
- **Fix:** Removed `@include header.php/footer.php` capture branch (was `ob_start` + globals + `@include` + swallowed `Throwable`). `renderFullPage()` now always uses deterministic synthetic `headerHtml/footerHtml` + `sanitizeForPreview(rendered)` (already hardened File 1). Fidelity via public preview route if needed.

## 8. Single-row GSC design — ✅ FIXED (BC) 2026-08-30
- **Location:** `models/GscClient.php:160-163` `gsc_tokens id=1` → now `GscClient.php:29-56,141-166,339-340`
- **What:** One property per install. `getSiteUrl()` fallback `sc-domain:kuplyu-tashkent.uz` hard-codes domain.
- **Why it matters:** Multi-site CMS can't connect second property; `listSites()` exists but never used to scope.
- **Fix:** Keep `id=1` BC: `getSiteUrl()` now checks DB `site_url` before hardcoded fallback; added `getSiteUrlFor(?override)` helper; `searchAnalytics(..., ?siteUrlOverride)` accepts override via `getSiteUrlFor`; `getStatus()` now exposes `available_sites` from `listSites()` when connected for debug. No schema migration (defer per-property table to project-11).

## 9. Prompt contradictions confuse planning — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/AiStudioController.php:469-483` → now `AiStudioController.php:520-530` operating loop
- **What:** "Minimal read rule: read target ONCE then WRITE" vs "When auditing, call get_page + get_page_gsc + get_page_stats together" vs "MOBILE vs DESKTOP via query_gsc dimensions [query,device]".
- **Why it matters:** Vague requests over-fetch, concrete edits under-read; model wastes tool turns auditing when user said `update_section Kravat`.
- **Fix:** Made loop conditional: CONCRETE target → one `list_sections→get_section` then WRITE same turn; VAGUE request → discover via `list_pages`+`get_underperforming_pages`+`get_gsc_overview`/`get_page_gsc` together; removed duplicate MOBILE vs DESKTOP line already covered by `query_gsc` tool. Kept in `buildSystemPrompt()` base.

## 10. Tool descriptions contain hidden directives — ⏸️ DROPPED (speculative per CLEANED.md)
- **Location:** `models/ai/tools/PageTools.php:118` `... preserve template variables. Senior HTML: use semantic tags...`
- **What:** Schema `description` fields embed imperatives that get reflected in tool output and re-injected as model context.
- **Why it matters:** Prompt injection surface via tool result reflection; hard to distinguish system intent from data.
- **Triage:** Descriptions are system-authored tool schemas, not attacker-reflected data. No code change; non-data intent vs data distinction not needed — mitigate via `sanitizeForPreview`/log redaction already.
