# 03 — Code Bugs & Reliability

> **Status 2026-08-30: 10/10 FIXED — all code-bug issues closed (8 already in working copy from prior batch + 2 closed now: #6 JS explicit max_turns_exceeded, #9 draft filter). No breaking changes, BC preserved.**
> Previous: Audit only, no fixes.

## 1. SSE headers sent before empty-message validation — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/AiStudioController.php:112-117` → now `AiStudioController.php:154-167`
- **What:** `startStream()` sent SSE headers then checked empty `message`.
- **Fix:** Validate `if ($message==='') $this->json(...,400)` **before** `startStream()`. Client `ai-studio.js:851` `sendTurn` now receives JSON 400 as expected, not SSE frame.

## 2. `alreadyInHistory` dedup is exact-string only — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/AiStudioController.php:132-138` → now `AiStudioController.php:180-194`
- **What:** `===` on raw content missed `\r\n` vs `\n` and whitespace variants.
- **Fix:** Normalized compare: `str_replace("\r\n","\n")` + `preg_replace('/\s+/u',' ',trim())` on both sides before `===`.

## 3. Malformed tool args silently become `[]` — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/AiStudioController.php:222-225` → now `AiStudioController.php:276-288`
- **What:** `json_decode` failure fell back to `[]` and executed with wrong args.
- **Fix:** If `!is_array($args)`, emit `sse('tool_result', ok:false)` + push `role:tool` `{"error":"Invalid tool arguments JSON ..."}` with `json_last_error_msg()` and `continue`; deterministic `toolMsgId = $tc['id'] ?? sha1(name:rawArgs)`.

## 4. `toolMsgId` fallback collides — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/AiStudioController.php:232` → now `AiStudioController.php:295-296`
- **What:** Fallback `?? $name` reused same ID across turns.
- **Fix:** `$toolMsgId = $tc['id'] ?? $out['call_id'] ?? AiToolRegistry::callId($name,$args)` — deterministic `sha1(name:normalized args)` via `AiToolRegistry::callId`.

## 5. Tool result mid-JSON truncation corrupts context — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/AiStudioController.php:324-326` → now `AiStudioController.php:388-402`
- **What:** `mb_substr(...,12000)."…"` produced invalid JSON in `tool` role.
- **Fix:** Wrap truncated payload in valid JSON: `json_encode(['_truncated'=>true,'original_chars'=>..., 'preview_json'=>mb_substr(...,11500), 'result_summary'=>..., 'note'=>...])`. Never emits broken JSON.

## 6. `hitCap` may reference undefined `$toolCalls` — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/AiStudioController.php:347-350` + `public/js/admin/ai-studio.js:1018-1031` → now `ai-studio.js:1018-1036`
- **What:** `?? null` safe but JS lumped `max_turns_exceeded` as generic `Stopped`.
- **Fix:** PHP now correctly emits `sse('error',...)` + `sse('done',{status:'max_turns_exceeded'})` (already in working copy). JS now has explicit `else if (data.status==='max_turns_exceeded')` branch: `addAgentBubble('⚠ Reached max tool turns (8)…')` + `setStatus('Max turns (8) — continue?','error')`. CLEANED triage was Requires More Evidence — now distinct UX.

## 7. `CHAR(36)` column vs 22-char generated IDs — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/AiStudioController.php:642-656` + `91` + `public/js/admin/ai-studio.js:132` → now `AiStudioController.php:136-141,708-709` + `ai-studio.js:132-139`
- **What:** `CHAR(36)` with 22-char PHP and 12-char JS values → space-pad Quirks.
- **Fix:** PHP `random_bytes(16)` UUID v4 `8-4-4-4-12` (36 chars, version bits). JS `crypto.randomUUID()` with `crypto.getRandomValues` fallback (36 chars). DB `CHAR(36)` unchanged, BC regex `^[a-z0-9\-]{8,64}$` still accepts legacy IDs.

## 8. `callId` is type-brittle — ✅ FIXED 2026-08-30
- **Location:** `models/ai/AiToolRegistry.php:90-93` → now `AiToolRegistry.php:91-119`
- **What:** `{"page_id":1}` vs `{"page_id":"1"}` hash diverged, approval `call_id` mismatch on retry.
- **Fix:** Added `normalizeArgs()` canonicalizing `int`/`float`→string and numeric strings via `(string)($v+0)` before `sortKeysRecursive` + `sha1`.

## 9. `search_content` LIKE not escaped — ✅ FIXED 2026-08-30
- **Location:** `models/ai/tools/PageTools.php:476-479` → now `PageTools.php:476-486`
- **What:** `%`/`_` unescaped → overbroad; drafts returned despite `is_published` flag.
- **Fix:** `$escaped = str_replace(['\\','%','_'],['\\\\','\%','\_'],$query)` + `WHERE (title LIKE ? ESCAPE '\\' OR content LIKE ? ESCAPE '\\') AND is_published=1`. `list_pages` still exposes drafts for admin discovery.

## 10. Byte vs multibyte miscounts + style double-escape — ✅ FIXED 2026-08-30
- **Location:** `models/ai/tools/PageTools.php:608,753,765` → now `PageTools.php:612,753` + `models/ai/tools/PageSectionsHelper.php:52-71`
- **What:** `substr_count` byte-count broke Cyrillic; `mergeStyleIntoTag` double-escaped `&quot;`.
- **Fix:** `substr_count` → `mb_substr_count(..., 'UTF-8')` in `strReplaceField` and `patchSection`. `mergeStyleIntoTag` delegated to `PageSectionsHelper::mergeStyleIntoTag` which does `html_entity_decode` then single `htmlspecialchars(...,ENT_COMPAT)`.
