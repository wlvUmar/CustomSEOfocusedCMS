<?php
/**
 * Migration: allow the 'write' scope on SEO API tokens (POST /api/seo/v1/page/{slug}/fields).
 * Only widens the SET; existing tokens keep their scopes and the column default stays read-only.
 * Safe to re-run.
 */
return [
    "ALTER TABLE `seo_api_tokens`
        MODIFY `scopes` SET('pages','stats','gsc','write') NOT NULL DEFAULT 'pages,stats,gsc'",
];
