# SEO operator setup — kuplyu-tashkent.uz

Status: **PLAN / PROPOSAL.** Nothing in this document is implemented yet unless marked ✅. Rules: see `AGENTS.md`.

## 0. Current state (verified 2026-10-20)

- ✅ Site live: 200 OK; sitemap index with 2 files; robots.txt clean (per owner check).
- ✅ GSC OAuth code exists: `controllers/admin/GscAuthController.php`, `models/gsc/GscAuth.php`, `models/GscClient.php`, `migrations/011_gsc_tokens.php` (table `gsc_tokens`, single row id=1, encrypted tokens).
- ✅ SERP code exists: `models/ai/tools/SerpTools.php` (SerpApi, `SERPAPI_API_KEY`, 1h cache).
- ⚠️ `GSC_CLIENT_ID`, `GSC_CLIENT_SECRET`, `SERPAPI_API_KEY` — owner must set in `.env` (values never copied here).
- ⚠️ No Bearer-token API for agents yet. Existing `/api/bot/*` uses HMAC signing (`X-Bot-Timestamp`, `X-Bot-Signature`, `BOT_API_SECRET`, 60s window, nonce store) and is meant for the Telegram bot, not for SEO agents.

---

## 1. Token-based DB access (proposal)

**Goal:** the Hermes SEO operator reads SEO data (pages, stats, GSC snapshot, SERP cache) without DB credentials and without admin session cookies.

### 1.1 Endpoint

```
POST /api/seo/token            (NOT BUILT — tokens are issued via CLI only: scripts/issue-seo-token.php)
GET  /api/seo/v1/pages         Authorization: Bearer ***        (built)
GET  /api/seo/v1/page/{slug}                                     (built)
GET  /api/seo/v1/stats/top-pages?days=28                         (built)
GET  /api/seo/v1/gsc/summary?days=28       (built; reads gsc_tokens via GscClient; no token exposed)
GET  /api/seo/v1/serp/cache?q=...          (NOT BUILT)
```

Read-only. No write endpoints in v1.

### 1.2 Token design

- Random 32 bytes (`random_bytes`), shown **once**; store only `sha256(token)` in a new table `seo_api_tokens`:
  `id, label, token_hash CHAR(64) UNIQUE, scopes SET('pages','stats','gsc'), created_at, expires_at (default +30d), last_used_at, revoked_at`. Not built: `serp` scope, `ip_allowlist`.
- Compare with `hash_equals`. Reject expired/revoked. Rate limit per token (e.g. 120 req/min) using the existing bot nonce/rate infrastructure pattern.
- Log `token_id`, route, status, time — never the token.
- Transport: HTTPS only. Reject `Authorization` over plain HTTP.
- Rotation: issue new → update Hermes secret store → revoke old. Owner runs it; agents cannot issue tokens.

### 1.3 Migration

✅ `migrations/013_seo_api_tokens.php` (created; `CREATE TABLE IF NOT EXISTS`, same pattern as `011_gsc_tokens.php`). `012` is already taken by `012_ai_pending_approvals.php`.
- Implemented scopes: `pages`, `stats`, `gsc`. `serp` scope and `ip_allowlist` are not built.
- Implemented routes: `GET /api/seo/v1/pages`, `/page/{slug}`, `/stats/top-pages`, `/gsc/summary`. `/serp/cache` and `POST /api/seo/token` are not built.
- Issue tokens with the CLI only: `php scripts/issue-seo-token.php issue --label=... --scopes=pages,stats,gsc --days=30` (also `list`, `revoke --id=N`).

### 1.4 Hermes side

- Store token in the Hermes profile secret store, not in `.env` of this repo and not in `AGENTS.md`.
- Agent calls only the GET routes above; on 401/403 it stops and reports.

---

## 2. GSC OAuth flow (existing — operator checklist)

Current implementation (do not re-implement):

1. Google Cloud project: enable **Search Console API**. Create OAuth client, type *Web application*.
2. Authorized redirect URI: `https://kuplyu-tashkent.uz/admin/ai-studio/gsc-callback` (from `models/gsc/GscAuth.php:28`, built from `BASE_URL`). Must match byte-for-byte.
3. Scope: `https://www.googleapis.com/auth/webmasters.readonly`. Params: `access_type=offline`, state stored in session (`gsc_oauth_state`).
4. `.env`: `GSC_CLIENT_ID`, `GSC_CLIENT_SECRET`, `GSC_SITE_URL=https://kuplyu-tashkent.uz/`, **`GSC_ENCRYPTION_KEY` set to its own 32+ char random value** (currently defaults to `BOT_API_SECRET` — see AGENTS.md §7).
5. Run migration 011 (`migrate.php`).
6. Owner logs into `/admin`, opens `/admin/ai-studio/gsc-auth`, approves Google consent with the account that **owns or has Full/Restricted access** to the GSC property.
7. Verify: `/admin/ai-studio/gsc-status` shows connected + site URL. Then ask the agent: `get_gsc_overview` (one call) and paste the result table.

Verification checks:
- Property must be the URL-prefix or domain property matching `GSC_SITE_URL`. Check both `https://` and `www`/non-www variants — pick the canonical one and note it.
- Token refresh failure (`invalid_grant`) → re-run step 6. Do not loop.

Disconnect: `/admin/ai-studio/gsc-disconnect` (owner only).

---

## 3. Cron and monitoring plan

Run through Hermes cron (profile `default`), not server crontab, so output lands in the agent's report flow. Each job is read-only and writes a report file to `reports/` (gitignored by existing rules — verify) or sends a summary.

| Job | Schedule (UZT, UTC+5) | Source | Output | Alert when |
|---|---|---|---|---|
| Uptime + status | every 15 min | HTTP GET `/`, `/api/health` | log line | non-200 ×2 in a row |
| Sitemap + robots | daily 06:00 | HTTP GET `/sitemap.xml`, `/robots.txt` | diff vs yesterday | sitemap 404, robots disallows `/` |
| Indexation spot-check | daily 07:00 | `/api/seo/v1/pages` + HTTP canonical/meta/hreflang check on top 20 pages | table | missing canonical, missing UZ hreflang |
| GSC summary | daily 08:00 | `get_gsc_overview`, `get_gsc_pages` (28d) | markdown table, max 3 bullets | clicks −30% WoW, new 4xx/5xx in coverage |
| GSC queries | weekly Mon 09:00 | `get_gsc_queries` | top 20 queries, RU/UZ split | new query with impressions >100 and CTR <1% |
| SERP niche watch | weekly Mon 10:00 | `serp_niche_overview` | 10 key queries, top 3 domains | owner-defined terms only |
| Internal analytics | weekly Sun 20:00 | `get_underperforming_pages` | list | — |

Budget guard: SerpApi weekly job capped at N queries (owner sets N). Cron must check remaining budget before calling.

Failure handling: a failed job reports the error and HTTP/API status; it does not retry more than 2× and does not write partial data as if complete.

Reports: stored under `reports/seo/YYYY-MM-DD-<job>.md`. Include source and fetch timestamp on every number.

---

## 4. Open decisions for owner

1. Approve the `seo_api_tokens` table and `/api/seo/v1` routes (§1), or keep admin-tool-only access.
2. Set a separate `GSC_ENCRYPTION_KEY`.
3. Choose the canonical property (`https://kuplyu-tashkent.uz/` vs `www`).
4. Set SerpApi weekly query budget and the list of watched terms (RU + UZ).
5. Decide whether `AGENTS.md` and this doc should be tracked in git (`.gitignore` currently ignores `*.md`).
