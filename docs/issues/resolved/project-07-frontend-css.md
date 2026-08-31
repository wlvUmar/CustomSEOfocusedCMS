# Project 07 — Frontend: CSS

> Whole-project audit — no fixes.

## 1. `scroll-behavior:smooth` ignores `prefers-reduced-motion`
- **Location:** `public/css/pages.css:52`
- **What:** Forced smooth for motion-sensitive users (Lighthouse a11y).

## 2. `will-change:transform` never removed + oversized paint
- **Location:** `public/css/pages.css:73-93` `.site-bg` fixed `inset:0` + `inset:-20% -10%` + 2 radials + `will-change` → extra compositor layer, 140%×120% paint, memory leak on low-end.

## 3. Only one component respects `prefers-reduced-motion`
- **Location:** `public/css/pages.css:223-277,921-923` `pulse-green` infinite, `brands-track`, `bot-modal`, `links-tile`, `hero__content` don't; only `brands-track` has reduce.

## 4. `:has()` and `mask` heavy / unsupported
- **Location:** `public/css/pages.css:697-812` `:has(.process-step)`, `mask:url(data:image/svg+xml...)` duplicated — heavy selector perf, Safari<15.4 fail.

## 5. `transition: all` triggers layout
- **Location:** `public/css/pages.css:209,619-623,1940` vs explicit `background-color, transform`.

## 6. `scrollbar-width:none` breaks keyboard affordance
- **Location:** `public/css/pages.css:1254-1257` `.links-track` hides scrollbar, `scroll-snap mandatory` traps focus.

## 7. Float layout obsolete, CLS
- **Location:** `public/css/pages.css:1449-1461,1587-1597` `.img-left/right` float + clear hack, breaks at 768 but leaves `max-width:400px`.

## 8. Duplicated admin toolbar + review modal
- **Location:** `public/css/pages.css:1898-1961` + `views/templates/header.php:169-240` + `article.php:78-106`; `pages.css:1822-1850` + `footer.php:134-204` — 3× bytes.

## 9. Fragmented breakpoints
- **Location:** `public/css/pages.css:164-1386` 480,600,640,720,768,900,1024 no scale → cascade inconsistent.

## 10. `pages.min.css` single-line no sourcemap, no cache-bust
- **Location:** `public/css/pages.min.css:1` + `public/.htaccess:80` 1-month expiry, `?v=` only on admin, `header.php:139` render-blocking.
