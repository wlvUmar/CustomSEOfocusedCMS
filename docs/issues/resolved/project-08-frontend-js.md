# Project 08 — Frontend: JS

> Whole-project audit — no fixes.

## 1. `param-persistence.js` mutates all `<a>` on init, drops hash
- **Location:** `public/js/param-persistence.js:84-130` `querySelectorAll('a[href]')` second layout pass O(n), `newHref=pathname+newQuery` loses `#anchor`, loses duplicate `?a=1&a=2`.

## 2. `link-tracking.js` capture phase hazard + slug heuristic wrong
- **Location:** `public/js/link-tracking.js:7-10,203-214,244` `addEventListener('click',...,true)` before app handlers; `part.length>2 ? part : 'main'` misclassifies `/uz` (2 chars) and `tv`.

## 3. `sendBeacon(URLSearchParams)` wrong type
- **Location:** `public/js/link-tracking.js:22-23` spec expects `Blob/FormData/string`, URLSearchParams serializes as `[object URLSearchParams]` in some browsers.

## 4. `media_manager.js` contains raw PHP tags
- **Location:** `public/js/media_manager.js:14,53,169` `<?= BASE_URL ?>` inside static `.js` not parsed → broken `fetch` URLs.

## 5. `media_manager.js` global pollution + null assumption
- **Location:** `public/js/media_manager.js:1-4,286` top-level globals `selectMode`, `document.getElementById('insert-modal').addEventListener` null error if loaded globally.

## 6. Feather duplicated from CDN + local
- **Location:** `public/js/feather.min.js:1-13` + `views/templates/preview.php:151` + `views/admin/layout/footer.php:6` — loads both locally and `unpkg.com`, version skew, extra DNS.

## 7. `MutationObserver` observes entire body subtree
- **Location:** `views/admin/layout/footer.php:39-41` `observe(body, childList subtree true)` + `addTableLabels()` on every mutation → thrash on media filter typing.

## 8. Admin Chart.js loaded synchronously on every admin page
- **Location:** `views/admin/layout/header.php:15` `Chart.js 3.9.1 ~200KB` without `defer` — TBT 400-600ms on `media`, `pages/edit`.

## 9. GTM double loader
- **Location:** `views/templates/header.php:76-100` + `views/templates/footer.php:116-131` — both add 5 listeners `pointerdown/keydown/touchstart/scroll/wheel`; `__loadGTM` may fire twice before guard; no `preconnect`.

## 10. Inline 221-line blocking script before LCP
- **Location:** `views/templates/page.php:247-468` 6 `IntersectionObserver` instances vs single, `el.style.transitionDelay = (i%6)*70+'ms'` inline recalc, `split(/\s+/)` breaks brand names with spaces.
