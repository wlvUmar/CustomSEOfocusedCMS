# Project 01 — Security: Routing & Router

> Whole-project audit — 10 issues per file — no fixes.

## 1. Router `HEAD` → `GET` without dropping body
- **Location:** `core/Router.php:29-31`
- **What:** `if ($method==='HEAD') $method='GET'` then dispatches with body.
- **Why:** Violates HTTP, caches GET for HEAD, breaks conditional HEAD checks.

## 2. Base path stripping fails behind alias
- **Location:** `core/Router.php:32-37` `dirname($_SERVER['SCRIPT_NAME'])`
- **What:** When `SCRIPT_NAME` is `/index.php` vs `/public/index.php` behind alias, leaves `/public` prefix → 404 bypass.

## 3. Wrong error code on method mismatch
- **Location:** `core/Router.php:41-42`
- **What:** Unknown method → `404` not `405`.
- **Why:** WAF can't distinguish enumeration, SEO misreports.

## 4. Route specificity only by `{` presence
- **Location:** `core/Router.php:45` `uksort(... strpos($a,'{')!==false)`
- **What:** `/admin/pages/{id}` may match before `/admin/pages/new` if inserted differently → `/new` treated as `{id}=new`.

## 5. Route param allows path traversal
- **Location:** `core/Router.php:64-67` `preg_replace('/\{([a-zA-Z0-9_]+)\}/','(?P<$1>[^/]+)')`
- **What:** `[^/]+` accepts `..`, `%2f` encoded slashes, `../../etc/passwd`.

## 6. `group()` discards parent handlers
- **Location:** `core/Router.php:70-82`
- **What:** `group()` creates `new self()` isolated, discarding `notFound`/`error` handlers and middleware.

## 7. `public/index.php` undefined `$router` in 404 closure
- **Location:** `public/index.php:379` `notFound(function(){ $router->error(404); })`
- **What:** No `use ($router)` — fatal Undefined variable on 404 fallback.

## 8. Helpers defined after dispatch
- **Location:** `public/index.php:384-453` after `$router->dispatch()`
- **What:** Works via PHP hoisting but OPcache fatal if helper fatals before definition.

## 9. Catch-all `/{slug}` shadows `/admin/login` on slash handling
- **Location:** `public/index.php:363-376` + `public/.htaccess:48`
- **What:** Trailing slash handling may cause `/admin/login/` to fall through to `{slug}`.

## 10. Bot API routes public at router layer
- **Location:** `public/index.php:281-310` `/api/bot/*` 15 endpoints
- **What:** No auth at router, delegated to controller but exposed to `isBot()` tracking bypass.
