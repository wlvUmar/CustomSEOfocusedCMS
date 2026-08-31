# 06 — State, Session & SSE Issues

> Audit only, no fixes.

## 1. Triple ID format split
- **Location:** `public/js/admin/ai-studio.js:132` `genSessionId()` vs `controllers/admin/AiStudioController.php:91` `bin2hex(random_bytes(8)).'-'.substr(uniqid(),-6)` vs `CHAR(36)` `642`
- **What:** JS base36 12 chars, PHP hex 22 chars, DB CHAR 36 padded; `sanitize session_id` regex `^[a-z0-9\-]{8,64}$` `AiStudioController.php:73` allows 8-char brute force.
- **Why it matters:** Merges `loadSessionHistory` `75-82` mix formats; weak JS IDs guessable.

## 2. `persistAfterRun` headers-already-sent
- **Location:** `controllers/admin/AiStudioController.php:709-720`
- **What:** After `startStream()` headers + `session_write_close()`, does `@session_start()` + `$_SESSION['ai_session_id']=...` + `@session_write_close()`.
- **Why it matters:** `headers already sent` hidden by `@`, `ai_sessions` update runs but `$_SESSION` restoration likely fails; next request missing context.

## 3. `session_write_close` early but context mutated later
- **Location:** `controllers/admin/AiStudioController.php:108` + `models/ai/tools/MemoryTools.php:145-146` `storeContext` → `ensureSession()` + `dbContextPersist`
- **What:** Parent request unlocked session before loop; concurrent tab's `storeContext` reopens session, race overwrites `ai_context`.
- **Why it matters:** Lost writes, split-brain between DB latest-row fallback `MemoryTools.php:117` and `$_SESSION`.

## 4. `pendingContext` is in-memory only
- **Location:** `public/js/admin/ai-studio.js:48,991-993` + `controllers/admin/AiStudioController.php:334-342`
- **What:** `pendingContext = data.pending` set on `approval_required` SSE, resent on Approve via `pending` POST param. Not persisted to DB.
- **Why it matters:** Refresh before Approve discards interrupt; `sanitizePending` requires exactly 1 tool_call `561-614` — batch guarded stalls with empty array.

## 5. History caps diverge + tool role dropped
- **Location:** `controllers/admin/AiStudioController.php:518-537` + `696-708` + `public/js/admin/ai-studio.js:1056`
- **What:** `sanitizeHistory` keeps only `user|assistant` (drops `tool`), allows 48 then slices to 24; `persistAfterRun` keeps `user|assistant|tool` up to 48; JS keeps 24.
- **Why it matters:** After 25 turns JS truncates more aggressively than server, next turn loses tool grounding → model repeats reads.

## 6. `pending` validation truncates instead of rejecting
- **Location:** `controllers/admin/AiStudioController.php:582,586,603` `substr($id,0,64)`
- **What:** 64-char limit on `id`/`name`/`tool_call_id` enforced by silent `substr`, not rejection.
- **Why it matters:** Mangles IDs, `call_id === tool_call_id` check `600` may pass on truncated values, wrong tool paired.

## 7. SSE `retry:2000` + `flushAll` may be fully buffered
- **Location:** `controllers/admin/AiStudioController.php:397-432` + `public/js/admin/ai-studio.js:864-869`
- **What:** `header('X-Accel-Buffering: no')` + `ob_end_clean` loop + `flush()`, but host nginx gzip/mod_deflate still buffers. Client fallback `if (buf.trim()) parseFrame(buf)` handles coalesced body but activity timer inaccurate.
- **Why it matters:** Events arrive all-at-once at `done`, user sees frozen "Thinking…" then jump.

## 8. `parseFrame` assumes `\n\n`, proxies send `\r\n`
- **Location:** `public/js/admin/ai-studio.js:855-861`
- **What:** `while ((idx=buf.indexOf('\n\n'))!==-1) parseFrame(...)` splits on `\n\n`, not `\r\n\r\n`.
- **Why it matters:** Some proxies normalize to `\r\n`, frames never split, stream appears stalled until watchdog fires.

## 9. `connection_aborted` only checked at loop top
- **Location:** `controllers/admin/AiStudioController.php:151-160`
- **What:** `if (connection_aborted())` at `for ($turn=1; ...)` top, not after 120s `OpenRouter::chatWithTools` blocking curl.
- **Why it matters:** User clicks Stop (`abortCtrl.abort()` `ai-studio.js:911`), server continues until curl returns, burning cost.

## 10. `uniqid()` leaks microtime + table creation swallows errors
- **Location:** `controllers/admin/AiStudioController.php:91` + `642-656` `ensureAiSessionsTable`
- **What:** `$newId = bin2hex(random_bytes(8)).'-'.substr(uniqid(),-6)` without `more_entropy` predictable; `ensureAiSessionsTable` catches `Throwable` empty.
- **Why it matters:** Session ID entropy weaker than `random_bytes` alone; DB permission errors hidden, table never created, `persistSession` silently fails via `error_log` only.
