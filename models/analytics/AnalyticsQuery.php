<?php
// models/analytics/AnalyticsQuery.php
// Shared query builder for analytics filters.
// Collapses the copy-paste slug/utm/date branches from Analytics (former lines 11-90 pattern).
// Pure string building only — no DB. All fragments are byte-identical to the originals.

class AnalyticsQuery {
    const SITE_SLUG = '__site__';

    public static function clampMonths($months, $max = 24) {
        return max(1, min($max, (int)$months));
    }

    public static function metricField($type) {
        return ($type === 'visits') ? 'visits' : ((($type === 'phone_calls') ? 'phone_calls' : 'clicks'));
    }

    public static function slugExpr($slug, &$params) {
        if ($slug !== '') {
            $params[] = $slug;
            return 'page_slug = ?';
        }
        return "page_slug != '" . self::SITE_SLUG . "'";
    }

    public static function utmExpr($utmSource, &$params) {
        if ($utmSource === '') {
            return '';
        }
        if ($utmSource === 'direct') {
            return "(utm_source = '' OR utm_source IS NULL)";
        }
        $params[] = $utmSource;
        return 'utm_source = ?';
    }

    public static function slugSql($slug, &$params) {
        return ' AND ' . self::slugExpr($slug, $params);
    }

    public static function utmSql($utmSource, &$params) {
        $expr = self::utmExpr($utmSource, $params);
        return $expr === '' ? '' : ' AND ' . $expr;
    }

    public static function pageConds(&$conds, &$params, $slug, $pageSlug = null) {
        if ($slug !== '') {
            $conds[] = 'page_slug = ?';
            $params[] = $slug;
        } elseif ($pageSlug !== null) {
            $conds[] = 'page_slug = ?';
            $params[] = $pageSlug;
        } else {
            $conds[] = "page_slug != '" . self::SITE_SLUG . "'";
        }
    }

    public static function utmConds(&$conds, &$params, $utmSource) {
        $expr = self::utmExpr($utmSource, $params);
        if ($expr !== '') {
            $conds[] = $expr;
        }
    }

    public static function hourlySkeleton() {
        $result = [];
        for ($hour = 0; $hour <= 23; $hour++) {
            $label = str_pad((string)$hour, 2, '0', STR_PAD_LEFT) . ':00';
            $result[$label] = 0;
        }
        return $result;
    }

    public static function rangeLabel(DateTime $d, $labelMode, $spansYear) {
        if ($labelMode === 'weekday') {
            return $d->format('D');
        }
        return $spansYear ? $d->format('M j Y') : $d->format('M j');
    }

    public static function fillDailyRange($startDate, $endDate, $labelMode = 'date') {
        $result = [];
        $cursor = new DateTime($startDate);
        $end = new DateTime($endDate);
        $spansYear = $cursor->format('Y') !== $end->format('Y');
        while ($cursor <= $end) {
            $result[self::rangeLabel($cursor, $labelMode, $spansYear)] = 0;
            $cursor->modify('+1 day');
        }
        return [$result, $spansYear];
    }
}
