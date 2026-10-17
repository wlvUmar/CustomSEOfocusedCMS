<?php
// path: ./models/ai/ReportSnippets.php
// Pure markdown formatters for analytics answers.

class ReportSnippets {

    public static function topPagesTable(array $pages, int $max = 10): string {
        $rows = array_slice($pages, 0, max(1, min(15, $max)));
        if (empty($rows)) return "_No pages with traffic in this window._";
        $out = ["| Page | Visits | Calls | CTR |", "|---|---|---|---|"];
        foreach ($rows as $p) {
            $slug = self::cell($p['slug'] ?? '?', 32);
            $out[] = '| ' . $slug . ' | ' . (int)($p['visits'] ?? 0) . ' | ' . (int)($p['phone_calls'] ?? 0) . ' | ' . number_format((float)($p['ctr_percent'] ?? 0), 1) . '% |';
        }
        return implode("\n", $out);
    }

    public static function monthlySummary(array $totals, array $top, array $weak, string $label): string {
        $v = (int)($totals['visits'] ?? 0);
        $c = (int)($totals['clicks'] ?? 0);
        $calls = (int)($totals['phone_calls'] ?? 0);
        $ctr = $v > 0 ? round(100.0 * $calls / $v, 2) : 0;
        $lines = ["**{$label}: {$v} visits / {$c} clicks / {$calls} calls / {$ctr}% CTR**", '', self::topPagesTable($top, 8)];
        if (!empty($weak)) {
            $names = [];
            foreach (array_slice($weak, 0, 5) as $w) $names[] = self::cell($w['slug'] ?? '?', 32);
            $lines[] = '';
            $lines[] = '_Needs work: ' . implode(', ', $names) . '_';
        }
        return implode("\n", $lines);
    }

    private static function cell(string $s, int $max): string {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
        $s = str_replace('|', '/', $s);
        if (mb_strlen($s) > $max) $s = mb_substr($s, 0, $max) . '…';
        return $s === '' ? '?' : $s;
    }
}
