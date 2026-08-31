# 01 — Security Issues

> Feature: AI Studio (`/admin/ai-studio`) — audit only, no fixes. All paths absolute under repo root.
> **Status 2026-08-30: 8/10 FIXED in build mode (issues 1-5, 7-9). Issues 6 and 10 deferred per CLEANED.md triage (requires more evidence / not silently failing). See fixes below.**

## 1. Rate limit is session-only and trivially bypassable — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/AiStudioController.php:46-54` → now `controllers/admin/AiStudioController.php:43-88`
- **What:** `$_SESSION["ratelimit_ai_studio_{user_id}"]` counts 20 runs / 600s. No IP, no DB, no atomicity, no `Retry-After` header.
- **Why it matters:** Clearing cookies / new browser resets bucket. `anon` bucket is dead code behind `requireAuth()` but still conflates unauth. Budget bypass = free LLM spend.
- **Evidence:** `if (time() - $rlData['timestamp'] > 600) $rlData = ['count'=>0,...]; $rlData['count']++;`
- **Fix:** Bound key to `user_id + sha1(IP)`, added file-backed `storage/ratelimit_ai_studio/<hash>.json` with `flock` atomicity, `max(session, file)` effective count, `Retry-After` header on 429. `anon` removed. Verified via `grep ratelimit_ai_studio`.

## 2. Silent model whitelist bypass — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/AiStudioController.php:58-61` → now `controllers/admin/AiStudioController.php:90-98`
- **What:** Only non-empty unknown `$model` falls back to `deepseek/deepseek-chat`. Empty string passes straight to `OpenRouter::chatWithTools` which then re-defaults separately.
- **Why it matters:** Client can send empty or probe invalid labels (`openrouter/free` typo); `logAi('model_fallback')` concatenates raw `$model` unsanitized.
- **Evidence:** `if ($model !== '' && !isset(OpenRouter::MODELS[$model])) { $model='deepseek/deepseek-chat'; }`
- **Fix:** Changed to `if ($model === '' || !isset(MODELS[$model]))` with `trim` + sanitized log via `preg_replace('/[^a-z0-9\/\-\.:_]/i','', $model)` capped 80 chars. Empty now falls back explicitly.

## 3. History / pending prompt injection via client JSON — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/AiStudioController.php:67-69,75-82,518-537,561-614` → now `AiStudioController.php:555-615,112-125`
- **What:** `history`/`pending` decoded from `$_POST`, shallow-checked for `role: user|assistant` + 4000-char cap, then `array_merge($dbHistory, $history)` without dedup or ordering guarantee.
- **Why it matters:** Attacker or misbehaving client can poison prompt, inject `system`-like content via crafted `content`, or pollute `$_SESSION['ai_context']` indirectly through tool output reflection.
- **Evidence:** `sanitizeHistory` keeps only `user|assistant` but server later persists `tool` role `699-702` — restore diverges.
- **Fix:** `sanitizeHistory()` now strips control chars, filters `system`/`ignore previous instructions` prefixes, deduplicates consecutive hashes, caps 48. Merge now dedups via `sha1(content)` hash set against DB history, preserves ordering, truncates 24.

## 4. Session takeover via `persistSession` missing `user_id` filter — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/AiStudioController.php:688-690` → now `AiStudioController.php:725-727` + `public/js/admin/ai-studio.js:132`
- **What:** `SELECT id FROM ai_sessions WHERE id=?` then `UPDATE ... WHERE id=?` without `AND user_id=?`.
- **Why it matters:** Guessing a 15-char JS `genSessionId()` `public/js/admin/ai-studio.js:132` (`Date.now base36 + random`) overwrites another user's row. ID is not `CHAR(36)` predictable entropy.
- **Evidence:** `$exists = Database::getInstance()->fetchOne("SELECT id FROM ai_sessions WHERE id = ?", [$sessionId]);`
- **Fix:** `persistSession()` now `WHERE id=? AND user_id=?` for SELECT and UPDATE. JS `genSessionId()` upgraded to `crypto.randomUUID()` / `crypto.getRandomValues` 128-bit fallback, matching PHP `CHAR(36)`.

## 5. BUILD mode disables all destructive guards — ✅ FIXED 2026-08-30 (Option A)
- **Location:** `models/ai/AiToolRegistry.php:127-143` → now `AiToolRegistry.php:126-143`
- **What:** `GUARDED_TOOLS` + `>800` char guard for `str_replace_field`/`patch_section`/`update_section` only when `mode !== 'build'`. In `build`, `delete_faq`, `set_field`, `update_section` with arbitrary HTML auto-executes.
- **Why it matters:** Owner-requested `APPROVALS DISABLED` comment — one bad LLM turn can wipe field wholesale, no undo UI beyond hidden `restore_page_revision`.
- **Evidence:** `if ($mode !== 'build') { $isGuarded = isset(self::GUARDED_TOOLS[$name]); ... }`
- **Fix:** Guard logic now applies in BUILD as well (Option A): `GUARDED_TOOLS` + `>800` char checks run regardless of mode. Small non-guarded writes still auto-execute in BUILD; destructive (`delete_faq`, `set_field`, `restore_page_revision`, large payloads) require approval via `call_id` flow.

## 6. GSC token falls back to plaintext — ⏸️ DEFERRED (Requires More Evidence per CLEANED.md:103)
- **Location:** `models/GscClient.php:84-96,104-116`
- **What:** `encrypt()` returns `$plain` if `GSC_ENCRYPTION_KEY`+`BOT_API_SECRET` empty or `openssl_*` missing, after `error_log`. `isConfigured()` still returns true on `GSC_CLIENT_ID` alone.
- **Why it matters:** New installs without key store `refresh_token` plaintext in `gsc_tokens`. Log line itself hints at insecurity but execution continues.
- **Evidence:** `if ($key === '' || !function_exists('openssl_encrypt')) { error_log(...); return $plain; }`
- **Triage:** Code now throws `RuntimeException` if both keys empty (`GscClient.php:92`), not silent. Path only when `openssl` missing. No fix in this batch; monitor. If strict fail-closed desired, change `isConfigured()` to require key — would break BC for existing installs.

## 7. Preview XSS hardening is regex-only — ✅ FIXED 2026-08-30
- **Location:** `models/ai/tools/SiteTools.php:215-217,282-285` + `views/admin/ai-studio/index.php:126` → now `SiteTools.php:210-260`
- **What:** `preg_replace('/<script\b/')` and `on\w+=` strip. Iframe `sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-modals"` with `srcdoc`.
- **Why it matters:** Bypassable via `<svg/onload=`, `<details open ontoggle=`, `javascript:` URI, `<style>@import`, `data:text/html;base64`. Preview can still execute script.
- **Evidence:** `$html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);`
- **Fix:** Added `sanitizeForPreview()` with layer 1 expanded regex (`javascript:`, `data:text/html`, `vbscript:`, `iframe/object/embed/link`, `@import`) + layer 2 DOMDocument purifier (removes `on*` attrs, dangerous `href/src/action`, `style` with `expression/@import`, strips `script/iframe/object` nodes via XPath). Both `renderPreview` and `renderFullPage` now use it. Iframe `sandbox` unchanged but content is purified before `srcdoc`.

## 8. Stored `transcriptHTML` re-injected without sanitization — ✅ FIXED 2026-08-30
- **Location:** `public/js/admin/ai-studio.js:148-153,194,232-234` → now `ai-studio.js:146-215`
- **What:** `saveCurrentSession()` stores `els.transcript.innerHTML`; `restoreSession()` sets `innerHTML` directly.
- **Why it matters:** Markdown renderer `619-830` builds DOM safely, but snapshot path bypasses it — any injected HTML persisted in `localStorage` survives reload.
- **Evidence:** `transcriptHTML: els.transcript ? els.transcript.innerHTML : ''` → `els.transcript.innerHTML = s.transcriptHTML`
- **Fix:** `saveCurrentSession()` no longer persists `transcriptHTML`; `restoreSession()` rebuilds transcript safely via `rebuildTranscriptFromHistory()` using `addUserBubble`/`addAgentBubble` (DOM-safe markdown). Legacy `transcriptHTML` ignored. Added `rebuildTranscriptFromHistory()` helper. Legacy `syncSessionsFromDb` and `createNewSession` also cleaned.

## 9. Logs leak secrets to the model — ✅ FIXED 2026-08-30
- **Location:** `models/ai/tools/MemoryTools.php:184-225` + `models/ai/AiToolRegistry.php:60`
- **What:** `get_tool_logs` in `PLAN_ALLOWLIST` reads `logs/ai-studio.log` + `logs/php_errors.log`, redacts only `Authorization:` via `preg_replace`.
- **Why it matters:** Exception messages can contain `OPENROUTER_API_KEY`, `GSC_CLIENT_SECRET`, `GSC_ENCRYPTION_KEY` verbatim; any PLAN-mode turn can exfiltrate via LLM.
- **Evidence:** `$content = preg_replace('/Authorization:\s*[^\n]+/i', 'Authorization: [redacted]', $content);`
- **Fix:** Removed `get_tool_logs` from `PLAN_ALLOWLIST` (`AiToolRegistry.php:60`) — now BUILD-only. Expanded redaction to `api_key|secret|password|token|OPENROUTER_API_KEY|GSC_*|BOT_API_SECRET`, `sk-*`, `Bearer`. Added `tailFile()` 32KB tail read instead of `file_get_contents` 10MB full load.

## 10. CSRF token lifetime mismatch + AuthZ over-broad — ⏸️ DEFERRED (Requires More Evidence per CLEANED.md:104)
- **Location:** `views/admin/ai-studio/index.php:135` + `public/js/admin/ai-studio.js:953` + `controllers/admin/AiStudioController.php:39` + `public/index.php:140-149`
- **What:** `generateCSRFToken()` at page load, reused for every `/run` and `/gsc-disconnect`. After idle/rotation, `validateCSRFToken` 400s with generic `Request failed (400):` shown via `ai-studio.js:840`. Router checks `requireAuth` only, not admin role.
- **Why it matters:** Long-lived studio tab fails silently; any authenticated non-admin can drive page/faq/rotation tools.
- **Evidence:** `window.AI_STUDIO = { csrf: generateCSRFToken() }` → `body: new URLSearchParams({ csrf_token: cfg.csrf })`
- **Triage:** Per-session token is design for single-admin app; over-broad AuthZ is defense-in-depth. Defer unified CSRF fix to `project-02`/`project-03` batch where all admin CSRF gaps are addressed together to avoid piecemeal rotation.
