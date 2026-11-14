<?php
/**
 * Migration: allow the 'ai_tools' scope on SEO API tokens (POST /api/mcp/v1/call).
 * Only widens the SET; existing tokens keep their scopes and the column default stays read-only.
 * Safe to re-run.
 */
return [
    "ALTER TABLE `seo_api_tokens`
        MODIFY `scopes` SET('pages','stats','gsc','write','ai_tools') NOT NULL DEFAULT 'pages,stats,gsc'",
];
