<div align="center">

# CustomSEOfocusedCMS

**A PHP MVC CMS built from scratch — content rotation, SEO automation, and analytics, with no framework underneath it.**

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Architecture](https://img.shields.io/badge/Architecture-Custom%20MVC-0A0A0A?style=flat-square)](#architecture)
[![License](https://img.shields.io/badge/License-MIT-blue?style=flat-square)](#license)

</div>

---

Most CMS projects either wrap a framework or stop at "create post, edit post, delete post." This one doesn't. It's a router, ORM-less data layer, and admin panel written by hand, built around a problem most CMSs ignore entirely: **content that's correct in January is stale by June.**

So instead of static pages, content here rotates on a schedule, every page generates its own structured data without a plugin, and there's an analytics layer built in to actually tell you whether any of it is working.

![Admin dashboard](docs/screenshots/dashboard.png)
<p align="center"><i>The admin dashboard — rotation status, recent activity, and request queue at a glance.</i></p>

## Why this exists

I wanted to build something where the interesting decisions weren't "which package do I install" but "how do I design this myself." No CMS framework, no SEO plugin, no analytics SaaS embed. Router, auth, the JSON-LD generators, the analytics aggregation — all of it written for this project specifically, which meant actually thinking through the schema and the edge cases instead of configuring someone else's.

## What it does

**Content rotation** — Pages aren't static. A page can be scheduled to swap its content on a monthly cycle, with a dedicated overview for tracking what's live now versus what's queued. Built for content that needs to stay seasonally relevant without manual republishing.

**SEO that's structural, not an afterthought** — Every `Page` and `Article` carries its own metadata and generates JSON-LD automatically — there are separate generators for articles, FAQs, and site-wide schema, because a one-size-fits-all schema generator produces wrong markup more often than it produces right markup. Sitemap and `robots.txt` generation, plus IndexNow ping-on-publish, are part of the same system rather than a bolted-on plugin.

**Analytics, built for this CMS specifically** — Page views and navigation-flow tracking (how users actually move between pages), crawl stats, and rotation performance, all native to the admin panel. Not a third-party embed — the aggregation logic is mine.

**Internal link management** — A dedicated tool for tracking and managing internal links sitewide, plus a configurable link widget for surfacing related content.

**Buyback request review** — A second, smaller system layered on top: users submit a photo and description of an item, an admin reviews and prices it from the panel. This is where the [Telegram bot](telegram_bot/README.md) plugs in — full writeup there, since it's a project in its own right (async state handling, signed webhooks, a separate pricing microservice).

<table>
<tr>
<td width="50%">

![Analytics](docs/screenshots/analytics.png)
<p align="center"><i>Navigation flow and crawl analytics</i></p>

</td>
<td width="50%">

![Request review](docs/screenshots/requests-show.png)
<p align="center"><i>Buyback request review screen</i></p>

</td>
</tr>
</table>

## Architecture

No framework — a deliberately small custom MVC core:

```
core/Router.php        →  routes requests to controllers
core/Controller.php    →  base controller, shared request/response handling
core/Database.php      →  thin PDO wrapper, no ORM
core/helpers.php        →  view rendering, shared utilities
```

Controllers split cleanly between public-facing (`controllers/`) and admin (`controllers/admin/`) — fourteen admin controllers, each scoped to one concern (analytics, articles, FAQs, internal links, media, requests, rotations, schema, SEO) rather than one God-controller doing everything.

```
config/        →  DB, security, app bootstrap
controllers/   →  public + controllers/admin/ for the panel
models/        →  Article, Page, FAQ, Analytics, JSON-LD generators, request/bot models
views/         →  admin/ (panel) and templates/ (public pages)
public/        →  entry point, CSS, JS, uploads
```

## Stack

PHP 7.4+, MySQL, vanilla JS/CSS in the admin panel — no frontend build step, no framework dependency. Apache + `mod_rewrite` for routing.

## A look at the admin panel

> Screenshots live in `docs/screenshots/`.

| File | Capture |
|---|---|
| `dashboard.png` | `views/admin/dashboard.php` — main admin landing |
| `analytics.png` | `views/admin/analytics/index.php` — analytics overview |
| `requests-show.png` | `views/admin/requests/show.php` — a buyback request under review |
| `rotation-overview.png` *(optional)* | `views/admin/rotations/overview.php` — content rotation schedule |
| `internal-links.png` *(optional)* | `views/admin/internal_links/index.php` — internal link manager |

---

<div align="center">

Part of a small ecosystem of projects — see [the Telegram bot integration](telegram_bot/README.md) for the buyback-request intake system.

</div>
