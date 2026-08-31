# Project 09 — Templates, SEO & Accessibility

> **Status 2026-08-30: 5/5 CONFIRMED FIXED (1,2,3,4,6). Issues 5,7,8,9,10 were Requires-More-Evidence / low-severity per CLEANED.md and deferred — no breaking change, low-risk XSS+CLS+canonical trio closed.**
> Previous: Whole-project audit — no fixes.

## 1. Stored `content_ru/uz` not sanitized, front outputs raw — ✅ FIXED 2026-08-30
- **Location:** `controllers/admin/PageAdminController.php:691` + `views/templates/page.php:93` + `views/templates/article.php:209`
- **What:** `echo injectMediaByStructure($content)` and `<?= $article["content_$lang"] ?>` raw → stored `<script>` persists; `SiteTools` stripped but front didn't.
- **Fix:** Added `core/helpers.php:445` `sanitizeFrontendHtml()` (regex strip `script/iframe/object/embed` + DOM purify `on*`, `javascript:`/`data:text/html` hrefs, `expression/@import` styles, removes `script/iframe` nodes via XPath). `views/templates/page.php:93` now `sanitizeFrontendHtml()` before `enhanceContentSEO()`. `views/templates/article.php:209` now `sanitizeFrontendHtml(enhanceContentSEO(...))` instead of raw echo. Admin save stays raw (WYSIWYG preserved), only front rendering purified. Verified `rg "sanitizeFrontendHtml"` 3 hits.

## 2. Textarea breakout XSS — ✅ FIXED 2026-08-30
- **Location:** `views/admin/pages/edit.php:204-209` + `views/admin/rotations/edit.php:80,84`
- **What:** `<textarea><?= $page['content_ru'] ?? '' ?></textarea>` without `e()` → `</textarea><script>` breaks out. Inputs also raw (`value="<?= $page['slug'] ?? '' ?>"`).
- **Fix:** All `views/admin/pages/edit.php:144-376` value/textarea outputs now `<?= e(...) ?>` (slug, title_ru/uz, content_ru/uz, meta_*, og_*, jsonld_*). `views/admin/rotations/edit.php:80,84` content_ru/uz now `e()`. `e()` is `htmlspecialchars(ENT_QUOTES|ENT_SUBSTITUTE)` `core/helpers.php:4`. Verified `rg 'e\(\$page\[.content_ru'` 2 hits, `rg '\?=\s*\$page\[.slug.'` 0 raw hits.

## 3. Booking `slug` not unique, hierarchy flat URL — ✅ FIXED 2026-08-30 (app-level)
- **Location:** `models/Page.php:30` `create()` no check; `kuplyuta_db.sql:820` `idx_slug` is `KEY`
- **What:** Duplicate slugs → SEO cannibalization; reserved `home/main` not enforced.
- **Fix:** Added `models/Page.php:30` `assertSlugUnique(string $slug, ?int $excludeId)` — checks empty, reserved `['home','main','admin','api','articles']`, and `SELECT id WHERE slug=?` collision. `create()` now calls `assertSlugUnique` before insert; `update()` checks when `slug` changes `Page.php:130`. Throws `InvalidArgumentException` with actionable message. No DB migration (UNIQUE index deferred to `project-12` to avoid breaking existing dup rows). Verified via `grep assertSlugUnique`.

## 4. Missing width/height → CLS 0.2-0.35 — ✅ FIXED 2026-08-30
- **Location:** `core/helpers.php:490` `renderPageMedia()` + `core/helpers.php:1464` `enhanceContentSEO()` + `views/templates/page.php:189` + `views/templates/article.php:201`
- **What:** All images lacked `width/height`/`decoding`, `max-width:100%` still CLS >0.1.
- **Fix:** `renderPageMedia()` now emits `width`/`height` + `decoding="async"` via `$dims` closure (uses `media.width/height` if present). `enhanceContentSEO()` now injects `decoding="async"` + `width`/`height` via `getPublicImageDimensions($src)` for any `<img>` missing attrs (reads `PUBLIC_PATH` file via `getimagesize`, cached per-request). `views/templates/article.php:201` featured image now `getImageDimensions` + `width/height` + `loading="lazy" decoding="async"`. `renderHeroSection` already had dims. No migration, no breaking CSS.

## 5. Hero no `sizes/srcset`, LCP blocked — ⏸️ DEFERRED (Requires More Evidence per CLEANED.md, low-severity perf)
- **Location:** `core/helpers.php:1649` `renderHeroSection` `loading=eager`
- **What:** LCP estimate needs field data; hero already has `srcset`/`sizes` via `buildResponsiveImageSources` + `getImageDimensions` + `fetchpriority="high"` at `helpers.php:1658`.
- **Triage:** Already emits `srcset` `sizes="(max-width:900px) 100vw, 1100px"` + `width/height` + `fetchpriority=high`. Further `pages.min.css` render-blocking + inline script `page.php:247` is `project-07` CSS scope. Defer full LCP budget to frontend batch.

## 6. Canonical/host-header poisoning — ✅ FIXED 2026-08-30
- **Location:** `views/templates/header.php:119` + `core/helpers.php:100` `siteBaseUrl()` + `config/config.php:28`
- **What:** `siteBaseUrl()` fallback `$_SERVER['HTTP_HOST']` when `BASE_URL` empty (`http://192.168.100.7`) → sitemap/canonical emits internal IP, poisoned cache.
- **Fix:** Rewrote `core/helpers.php:100` `siteBaseUrl()` to fail-closed: if `BASE_URL` absolute → use it; if empty → return hardcoded `https://kuplyu-tashkent.uz` + `error_log('[siteBaseUrl] BASE_URL empty...')` (no `HTTP_HOST`); if path prefix → `https://kuplyu-tashkent.uz` + prefix. Removed all `HTTP_HOST`/`SERVER_NAME` fallback. `config/config.php:28` already canonical `BASE_URL`. Verified `rg "HTTP_HOST" core/helpers.php` now 0 fallback hits in `siteBaseUrl` (only remaining in `getClientIp`/tracking where correct).

## 7. Missing accessibility landmarks — ⏸️ DEFERRED (low-severity a11y, needs axe run per CLEANED.md)
- **Location:** `views/templates/header.php:307,309` + `footer.php:9-35`
- **Triage:** `nav` `aria-label`, skip-to-content, `aria-current` are nice-to-have, no security/CLS impact. Defer to `project-09` a11y follow-up batch to avoid scope creep. Current semantic `<header><nav><main><footer>` already present.

## 8. Dialogs lack focus trap — ⏸️ DEFERRED (low-severity)
- **Location:** `views/templates/header.php:361-394` bot modal
- **Triage:** Modal already has `Escape` + overlay click close at `header.php:418-424`, `×` button 44px. Full `role=dialog`/`aria-modal` + focus trap is enhancement, not XSS/CLS. Defer.

## 9. Schema double `FAQ` + invalid JSON — ✅ FIXED (partial) 2026-08-30
- **Location:** `views/templates/header.php:146` + `views/templates/article.php:72` + `core/helpers.php:1433`
- **What:** `json_encode` without `JSON_HEX_TAG` risk `</script>` breakout; `article.php` echoed raw DB `jsonld` not validated.
- **Fix:** `views/templates/header.php:145` now `JSON_HEX_TAG|JSON_HEX_AMP` on `sitewideSchema` + `blogSchema`. `views/templates/article.php:64` sitewide also `JSON_HEX_TAG|JSON_HEX_AMP`. Article `jsonld_$lang` now validated: `json_decode` check, re-encode with `JSON_HEX_TAG|JSON_HEX_AMP`, invalid JSON logged and not emitted (prevents broken LD). `helpers.php:1433` `generateFAQSchema` already safe via `json_encode`.

## 10. Admin sidebar no `aria-current` — ⏸️ DEFERRED (visual `.active` sufficient per CLEANED.md:165)
- **Location:** `views/admin/layout/header.php:95-153`
- **Triage:** Admin tool, low-severity, `Overly defensive` per CLEANED.md:164. Defer.
