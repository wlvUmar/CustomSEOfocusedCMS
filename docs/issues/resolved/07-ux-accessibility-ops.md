# 07 — UX, Accessibility & Operational Issues

> Audit only, no fixes.

## 1. `setBusy` blocks model + new session even in PLAN
- **Location:** `public/js/admin/ai-studio.js:361-375`
- **What:** `els.model.disabled=true` + `els.newSession.disabled=true` on every turn, even read-only PLAN.
- **Why it matters:** Can't switch model mid-run when first model hallucinates; `ai-gsc-disconnect`/`history-toggle` not blocked → concurrent fetches during stream.

## 2. Model `<select>` overflow + curated labels lost
- **Location:** `public/js/admin/ai-studio.js:92-117`
- **What:** Live `fetchModels()` sorts with curated first, but pricing ` $0.0040/$0.0040` + `· 128k` appended for non-curated, no ellipsis.
- **Why it matters:** 60-char option text overflows topbar `flex-wrap` `views/admin/ai-studio/index.php:33`, mobile truncates badly.

## 3. Preview stacking heuristic fragile
- **Location:** `public/js/admin/ai-studio.js:559` `isFull` + `514-557` `buildCombinedPreview`
- **What:** `isFull = (kind==='render_full_page') || html.indexOf('<header>')!==-1` — user content containing `<header>` wipes stacked `render_preview` sections, no per-section iframe isolation.
- **Why it matters:** Stacked 2-section preview lost on third turn if HTML contains substring.

## 4. Transcript always steals scroll
- **Location:** `public/js/admin/ai-studio.js:428-433,442` `scrollTranscript()` + FAB `nearBottom` check
- **What:** Every `addAgentBubble`/`addToolEvent` does `scrollTop=scrollHeight`; user reading history scrolled away. FAB visibility uses `nearBottom` but scroll still forced.
- **Why it matters:** Can't inspect earlier tool error without being yanked to bottom.

## 5. Full-bleed `:has()` not supported in Firefox
- **Location:** `public/css/admin/ai-studio.css:35-36` `.admin-main:has(> .admin-content .ai-studio--app)`
- **What:** Breaks admin-main/content constraints via `:has`; Firefox fallback leaves `margin-left:-24px + width calc(100%+48px)` `30` causing horizontal overflow.
- **Why it matters:** 48px overflow scrollbar on Firefox, layout shift.

## 6. Approval UX not accessible
- **Location:** `public/css/admin/ai-studio.css:199-204` + `views/admin/ai-studio/index.php:86-96` + `public/js/admin/ai-studio.js:605-612`
- **What:** `#ai-approval[hidden]` toggled via `hidden` property, no `aria-live`, no focus trap, Approve/Deny only mouse, no `Enter` shortcut; `ai-status--wait` pulsing dot low contrast `#0f172a` on white fails WCAG AA for `.74rem`.
- **Why it matters:** Keyboard-only admin can't approve; screen reader not announced; `prefers-reduced-motion` disables animation but dot remains.

## 7. Tool errors collapsed by default, easy to miss
- **Location:** `public/css/admin/ai-studio.css:166` `ai-tool-event__body` hidden behind `ai-tool-event__head` chevron
- **What:** `Page not found: ID x not found. Call list_pages...` appears as collapsed `ai-tool-event--error` with `▸` chevron.
- **Why it matters:** User misses why edit failed, thinks agent is stuck looping.

## 8. History panel no virtualization
- **Location:** `public/css/admin/ai-studio.css:176` `max-height:240px` + `public/js/admin/ai-studio.js:159-183`
- **What:** 50 sessions rendered as DOM nodes each with `loadBtn`/`delBtn` listeners; `saveCurrentSession` stores full `transcriptHTML` + `lastPreviewHtml` in `localStorage` (quota ~5MB).
- **Why it matters:** Large transcripts hit quota, `saveSessions` silent `/* quota */` catch loses history.

## 9. Operational log rotation race + no retention
- **Location:** `controllers/admin/AiStudioController.php:451-454` + `models/ai/tools/MemoryTools.php:183-224`
- **What:** `if (filesize>10MB) @rename(LOG_FILE, LOG_FILE.'.'.date('Y-m-d_His'))` without lock; concurrent requests lose lines; `logs/ai-studio.log.2025-...` accumulates indefinitely, no compression/`logrotate`. `get_tool_logs` caps 20×500 so important context trimmed.
- **Why it matters:** Debugging truncated, disk fills.

## 10. No structured `session_id` in logs + inconsistent env keys
- **Location:** `controllers/admin/AiStudioController.php:443-449` `logAi` + `models/GscClient.php:17-35` + `.env` docs
- **What:** `logAi` logs `user`/`user_id` but not `session_id`/`model` correlation (only `run_start` ad-hoc). Env keys `OPENROUTER_API_KEY` vs `GSC_CLIENT_ID`/`GSC_ENCRYPTION_KEY`/`BOT_API_SECRET` fallback documented across different files, no central `config/init.php` validation. `storage/openrouter_models.json` + `gsc_token.lock` perms `0750` may fail under different web user silently via `@`.
- **Why it matters:** Can't trace cost per session; new installs misconfigure GSC and silently store plaintext.
