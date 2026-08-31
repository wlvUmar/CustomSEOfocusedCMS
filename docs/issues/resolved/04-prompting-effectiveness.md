# 04 — Prompting, Planning & Editing Effectiveness

> Why the agent struggles to edit/plan effectively — prompt and loop issues.

## 1. System prompt is 500+ lines per turn
- **Location:** `controllers/admin/AiStudioController.php:461-516`
- **What:** Full SEO + design doctrine + operating loop injected on every of 8 tool turns.
- **Why it matters:** Burns context before tools, no caching/compression; `deepseek-chat` 64k window fills with boilerplate instead of page content.

## 2. Contradictory operating loop
- **Location:** `controllers/admin/AiStudioController.php:469-474`
- **What:** "If user named slug like Kravat → go directly `list_sections`/`get_content_chunk`" vs system sections telling to call `list_pages`/`get_underperforming_pages`/`search_content` for discovery.
- **Why it matters:** Model hedges — sometimes audits unrelated pages even when user gave explicit target, wasting turns.

## 3. Prompt says minimal read, but also says audit triple
- **Location:** `controllers/admin/AiStudioController.php:483-484`
- **What:** "Minimal read rule: read ONCE then WRITE" vs "When auditing, call get_page + get_page_gsc + get_page_stats together; deeper via query_gsc dimensions [query,device]".
- **Why it matters:** Agent can't tell when to do which; direct edit request still triggers analytics/GSC reads, inflating cost/latency.

## 4. Hard rules are prompt-only, not server-enforced
- **Location:** `controllers/admin/AiStudioController.php:489-514`
- **What:** W3C landmarks, h1→h2→h3 sequence, RU↔UZ parity, template vars `{{page.title}}`/`{{global.phone}}`, Lighthouse budgets listed as "verify before marking complete".
- **Why it matters:** `update_section` accepts any HTML with any tags/inline style; nothing validates; parity breaks silently.

## 5. No post-write validation gate
- **Location:** `models/ai/tools/PageTools.php:719-747` + `controllers/admin/AiStudioController.php:317-320`
- **What:** `render_preview` per section + `render_full_page` final is suggested ("call after visual edit") but not enforced; agent can finish with "what I CHANGED" summary without ever rendering.
- **Why it matters:** User sees stale preview or no preview; CLS/heading skip regressions slip through.

## 6. Section targeting friction kills edits
- **Location:** `models/ai/tools/PageTools.php:429-464,538-573` + `controllers/admin/AiStudioController.php:139-144`
- **What:** `get_page` truncated to 12000 chars `PageTools.php:442`, so exact `find` string for `str_replace_field` not visible; must do `list_sections`→`get_section` or `get_content_chunk` with offset.
- **Why it matters:** `str_replace_field` requires exact-once `find` `PageTools.php:608`; truncated view forces guess, fails with "not found — fetch exact HTML via get_section" error, burning a turn.

## 7. PLAN vs BUILD UX confuses planning
- **Location:** `models/ai/AiToolRegistry.php:118-124` + `controllers/admin/AiStudioController.php:464`
- **What:** PLAN blocks writes with `error: Blocked in PLAN mode — switch to BUILD`; BUILD auto-executes everything. System prompt says "In PLAN finish with plan + Switch to BUILD to apply" vs "In BUILD never ask confirm".
- **Why it matters:** User in PLAN sees error tool result and thinks agent is broken; switching mid-session requires discovering `ai-mode-toggle` `views/admin/ai-studio/index.php:51` which is tiny and unlabeled beyond "Plan/Build".

## 8. Model choice undermines tool calling
- **Location:** `models/OpenRouter.php:7-17` + `public/js/admin/ai-studio.js:91-117`
- **What:** Default `deepseek/deepseek-chat` cheap but weak at strict tool JSON; `openrouter/free` auto-picks nondeterministic model; `GPT-OSS 120B:free` slow. Live model list overwrites curated short labels and appends pricing ` $0.0040/$0.0040` causing `<select>` overflow.
- **Why it matters:** Tool args malformed → `json_decode` → `[]` → wrong execution; user can't tell which model is actually reliable for HTML edits.

## 9. 8-turn cap with no continuation helper
- **Location:** `controllers/admin/AiStudioController.php:12` `MAX_TOOL_TURNS=8` + `347-373`
- **What:** "Find weakest pages by traffic and propose improved intro for worst one" needs list → stats → gsc → section → preview; hits cap → `error: Reached max tool turns — response truncated` + `done:max_turns_exceeded` (unhandled in JS).
- **Why it matters:** Multi-step plan/planning flow aborts mid-edit, leaving half-applied `batch_update` or no preview.

## 10. Guard threshold misaligned with prompt
- **Location:** `models/ai/AiToolRegistry.php:135,139-141` vs `controllers/admin/AiStudioController.php:506`
- **What:** Code guards `str_replace_field`/`patch_section`/`update_section` at `>800` chars; prompt says "large payload" vaguely and `batch_update` description says "up to 10 ops atomically" without mentioning 800-char boundary.
- **Why it matters:** Agent can't predict when approval will fire; in PLAN it hits approval wall unexpectedly, in BUILD it never does — inconsistent mental model for "destructive".

---

## FIXED — 2026-08-30 (all 10 issues, 8 Confirmed + 2 Needs-Evidence)

**Decision:** Best-for-project minimal-invasive path: keep working system, warnings-first not hard blocks, preserve 01-03 security hardening. `MAX_TURNS 8→10` (not 12) to limit cost/worker hold (960s risk). No model default swap without eval.

| # | Fix | Files |
|---|-----|-------|
| 1 | Prompt already condensed to ~28 lines via `PromptDoctrine.php` (02-architecture #4). Added `prompt_chars` to `run_start` log for measurement; no re-expansion. | `AiStudioController.php:169-176,537-565` |
| 2+3 | Conditional operating loop clarified (CONCRETE one-read-then-write vs VAGUE discovery). Prompt now says `list_sections/get_section` with `sections_hint` for concrete, `list_pages/...` together only for vague. No code branch — prompt-only. | `AiStudioController.php:537-551` |
| 4 | Soft validation warnings (heading h1×1/sequence, template vars `{{}}` preservation, token hex hint) returned as `warnings` array in tool result — never blocks. | `PageTools.php:793-840,860,880,900` `validateHtmlWarnings()` |
| 5 | Soft preview reminder: tracks `didWriteHtml`/`didPreview`; if BUILD wrote HTML without `render_preview`/`render_full_page` logs `preview_missing` and appends hint to final text (does not block `done:complete`). | `AiStudioController.php:203-204,383-393,444-464` |
| 6 | `get_page` now includes `sections_hint` (up to 12 `{index,name,chars,hash}`) + explicit `*_truncated` flags so model can jump to `get_section` without extra `list_sections` turn. Kept 12k cap. | `PageTools.php:464-483` + desc `40` |
| 7 | PLAN blocked error surfaced as actionable bubble + highlighted toggle; toggle gets descriptive `title` + `aria-label`; `ai-approval` gets `role=alert aria-live=assertive`; JS focuses Approve + Enter/Esc shortcuts. | `ai-studio.js:62-73,640-660,1008-1018` `index.php:51,86` `ai-studio.css` |
| 8 | Model `<select>` overflow fixed: `max-width:260px` + `text-overflow:ellipsis`; JS now uses short curated labels, non-curated name-only, pricing/context in `title` tooltip not option text. | `ai-studio.css:65` `ai-studio.js:99-117` |
| 9 | `MAX_TOOL_TURNS 8→10`; `hitCap` message now `Say "continue" + batch_update tip`; JS handles `max_turns_exceeded` explicitly with `continue` hint (persisted via `persistAfterRun` so resume works). Watchdog 180s still covers 2×120s curls. | `AiStudioController.php:13,444-458` `ai-studio.js:1042` |
| 10 | Aligned code & docs: `BUILD modeBlock` now states `<800 auto, >800 needs approval` + `set_field` always guarded; tool descriptions for `str_replace_field/patch_section/update_section/batch_update` all mention `>800 approval`. | `AiStudioController.php:540-541` `PageTools.php:86,102,216,234,330` |

**Preserved:** `AiToolRegistry.php:150-157` guard always in BUILD, `normalizeArgs` hash, `PLAN_ALLOWLIST` (get_tool_logs BUILD-only), `sanitizeHistory/Pending`, `session_write_close`+`ignore_user_abort`, `sanitizeForPreview` DOMPurify, `PageSectionsHelper` delegation, `Autoloader`.

**Verification:** `node --check ai-studio.js` pass; manual brace audit; `get_page` sections_hint tested via `PageSectionsHelper::splitIntoSections`; guard thresholds unchanged (799 vs 801).
