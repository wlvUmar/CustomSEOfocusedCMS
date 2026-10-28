<?php
// path: ./models/ai/AiPromptBuilder.php
// AI Studio system-prompt assembly. Extracted from AiStudioController — no behavior change.

require_once BASE_PATH . '/models/ai/PromptLoader.php';
require_once BASE_PATH . '/models/ai/SkillLoader.php';

class AiPromptBuilder {

    /**
     * Prompt-cache layout: providers cache the longest identical PREFIX of a request (system -> tools -> messages).
     * So the system message holds only what is constant between calls (mode prompt + AGENTS.md), and everything
     * that changes per run (summary, cached tokens, memory, session state) goes into buildContextBlock(), which the
     * controller injects as a run-internal message right before the user's current message.
     */
    public static function buildStaticPrompt(string $mode = 'plan'): string {
        $base = $mode === 'build' ? self::buildBuildPrompt() : self::buildPlanPrompt();
        return $base . SkillLoader::agentsBlock();
    }

    /** Static + dynamic in one string (legacy callers / debugging). */
    public static function buildSystemPrompt(string $mode = 'plan', ?array $ctx = null): string {
        return self::buildStaticPrompt($mode) . self::buildContextBlock($ctx);
    }

    public static function buildContextBlock(?array $ctx, ?string $summary = null): string {
        $summaryAddon = '';
        if ($summary !== null && trim($summary) !== '') {
            $summaryAddon = "\n\n═══ CONVERSATION SUMMARY (older turns, compacted — may be stale; prefer fresh tool reads for HTML) ═══\n" . mb_substr($summary, 0, 2000);
        }
        $cachedAddon = '';
        if (is_array($ctx) && isset($ctx['_cached_tokens']) && is_array($ctx['_cached_tokens'])) {
            $age = isset($ctx['_cached_tokens_at']) ? (time() - (int)$ctx['_cached_tokens_at']) : 999999;
            if ($age < 3600) {
                $tokJson = json_encode($ctx['_cached_tokens'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($tokJson !== false && mb_strlen($tokJson) > 20) {
                    if (mb_strlen($tokJson) > 2000) $tokJson = mb_substr($tokJson, 0, 2000) . '…[truncated]';
                    $cachedAddon = "\n\n═══ CACHED DESIGN TOKENS (from earlier turn, " . $age . "s ago — do NOT call get_design_tokens again; use this) ═══\n" . $tokJson;
                }
            }
        }
        $memoryAddon = '';
        if (is_array($ctx)) {
            $lines = [];
            $chars = 0;
            foreach ($ctx as $k => $v) {
                if (!is_string($k) || $k === '' || $k[0] === '_') continue;
                if (!is_string($v) && !is_numeric($v)) continue;
                $v = (string)$v;
                if ($v === '') continue;
                $show = mb_strlen($v) > 120 ? mb_substr($v, 0, 120) . '…' : $v;
                $line = $k . ': ' . $show;
                $chars += mb_strlen($line);
                if ($chars > 800) break;
                $lines[] = $line;
            }
            if (!empty($lines)) {
                $memoryAddon = "\n\n═══ SAVED MEMORY (auto-injected — current, do NOT call list_context/get_context to re-read; only call store_context when the user says remember/pin) ═══\n" . implode("\n", $lines);
            }
        }
        return trim($summaryAddon . $cachedAddon . $memoryAddon . self::renderSessionState($ctx));
    }

    /**
     * SESSION STATE: numbers-only facts already fetched in this session.
     * Rendered into every run's prompt so the model reuses data instead of
     * re-fetching it. Capped at ~1500 chars; empty state renders nothing.
     */
    private static function renderSessionState(?array $ctx): string {
        if (!is_array($ctx)) return '';
        $lines = [];
        if (!empty($ctx['_pages']) && is_array($ctx['_pages'])) {
            $parts = [];
            foreach ($ctx['_pages'] as $slug => $p) {
                if (!is_array($p)) continue;
                $parts[] = $slug . '(#' . (int)($p['id'] ?? 0) . ', ' . round((int)($p['c'] ?? 0) / 1000, 1) . 'k)';
            }
            if (!empty($parts)) $lines[] = 'Pages seen: ' . implode(', ', array_slice($parts, 0, 15));
        }
        if (!empty($ctx['_writes']) && is_array($ctx['_writes'])) {
            $parts = [];
            foreach (array_slice($ctx['_writes'], -6) as $w) {
                if (!is_array($w)) continue;
                $parts[] = (string)($w['p'] ?? '?') . ':' . (string)($w['s'] ?? '') . ' @' . (string)($w['h'] ?? '') . (!empty($w['v']) ? ' ok' : '');
            }
            if (!empty($parts)) $lines[] = 'Writes: ' . implode(' | ', $parts);
        }
        if (!empty($ctx['_page_slugs']) && is_array($ctx['_page_slugs'])) {
            $lines[] = 'Slug index: ' . (int)($ctx['_pages_total'] ?? count($ctx['_page_slugs'])) . ' pages (' . implode(', ', array_slice($ctx['_page_slugs'], 0, 12)) . (count($ctx['_page_slugs']) > 12 ? '…' : '') . ')';
        }
        if (!empty($ctx['_sections']) && is_array($ctx['_sections'])) {
            $parts = [];
            foreach ($ctx['_sections'] as $key => $s) {
                if (!is_array($s)) continue;
                $names = isset($s['names']) && is_array($s['names']) ? $s['names'] : [];
                $parts[] = $key . ': ' . (int)($s['n'] ?? 0) . ' [' . implode(', ', array_slice($names, 0, 6)) . ']';
            }
            if (!empty($parts)) $lines[] = 'Sections: ' . implode(' | ', array_slice($parts, 0, 8));
        }
        if (!empty($ctx['_gsc']) && is_array($ctx['_gsc'])) {
            $g = $ctx['_gsc'];
            $lines[] = 'GSC ' . (int)$g['d'] . 'd: ' . (int)$g['imp'] . ' imp / ' . (int)$g['clk'] . ' clk / ' . (float)$g['ctr'] . '% / pos ' . (float)$g['pos'];
        }
        if (!empty($ctx['_gsc_pages']) && is_array($ctx['_gsc_pages'])) {
            $parts = [];
            foreach ($ctx['_gsc_pages'] as $slug => $t) {
                if (!is_array($t)) continue;
                $parts[] = $slug . ' ' . (int)$t['imp'] . '/' . (int)$t['clk'] . '/' . (float)$t['pos'];
            }
            if (!empty($parts)) $lines[] = 'GSC pages (imp/clk/pos): ' . implode(', ', array_slice($parts, 0, 10));
        }
        if (!empty($ctx['_queries']) && is_array($ctx['_queries'])) {
            $lines[] = 'Top queries: ' . implode(', ', array_slice(array_map('strval', $ctx['_queries']), 0, 8));
        }
        if (!empty($ctx['_stats']) && is_array($ctx['_stats'])) {
            $parts = [];
            foreach ($ctx['_stats'] as $slug => $s) {
                if (!is_array($s)) continue;
                $parts[] = $slug . ' ' . (int)$s['v'] . 'v/' . (int)$s['c'] . 'c';
            }
            if (!empty($parts)) $lines[] = 'Traffic: ' . implode(', ', array_slice($parts, 0, 10));
        }
        if (!empty($ctx['_underperforming']) && is_array($ctx['_underperforming'])) {
            $u = $ctx['_underperforming'];
            $uslugs = isset($u['slugs']) && is_array($u['slugs']) ? $u['slugs'] : [];
            $lines[] = 'Low traffic (' . (int)($u['m'] ?? 0) . 'm): ' . (int)($u['n'] ?? 0) . ' pages [' . implode(', ', array_slice(array_map('strval', $uslugs), 0, 8)) . ']';
        }
        if (!empty($ctx['_monthly']) && is_array($ctx['_monthly'])) {
            $mm = $ctx['_monthly'];
            $lines[] = 'Monthly (' . (int)($mm['m'] ?? 0) . 'm): ' . (int)($mm['v'] ?? 0) . 'v/' . (int)($mm['c'] ?? 0) . 'c';
        }
        if (!empty($ctx['_links']) && is_array($ctx['_links'])) {
            $parts = [];
            foreach ($ctx['_links'] as $slug => $l) {
                if (!is_array($l)) continue;
                $parts[] = $slug . ' ' . (int)$l['in'] . 'in/' . (int)$l['out'] . 'out';
            }
            if (!empty($parts)) $lines[] = 'Links: ' . implode(', ', array_slice($parts, 0, 8));
        }
        if (!empty($ctx['_sql']) && is_array($ctx['_sql'])) {
            $parts = [];
            foreach (array_slice($ctx['_sql'], -2, null, true) as $s) {
                if (!is_array($s)) continue;
                $parts[] = '"' . (string)($s['q'] ?? '') . '" -> ' . (int)($s['n'] ?? 0) . ' rows';
            }
            if (!empty($parts)) $lines[] = 'Ran SQL: ' . implode(' | ', $parts);
        }
        if (!empty($ctx['_serp']) && is_array($ctx['_serp'])) {
            $parts = [];
            foreach (array_slice($ctx['_serp'], -3, null, true) as $q => $s) {
                if (!is_array($s)) continue;
                $bits = [];
                if (!empty($s['domains']) && is_array($s['domains'])) $bits[] = implode(',', array_slice(array_map('strval', $s['domains']), 0, 3));
                if (!empty($s['related']) && is_array($s['related'])) $bits[] = 'related: ' . implode(',', array_slice(array_map('strval', $s['related']), 0, 4));
                $parts[] = '"' . mb_substr((string)$q, 0, 40) . '": ' . implode('; ', $bits);
            }
            if (!empty($parts)) $lines[] = 'SERP: ' . implode(' | ', $parts);
        }
        if (!empty($ctx['_search']) && is_array($ctx['_search'])) {
            $parts = [];
            foreach (array_slice($ctx['_search'], -6, null, true) as $q => $n) {
                $parts[] = '"' . mb_substr((string)$q, 0, 30) . '"->' . (int)$n;
            }
            if (!empty($parts)) $lines[] = 'Searches: ' . implode(', ', $parts);
        }
        if (!empty($ctx['_sec_hash']) && is_array($ctx['_sec_hash'])) {
            $parts = [];
            foreach (array_slice($ctx['_sec_hash'], -6, null, true) as $key => $s) {
                if (!is_array($s)) continue;
                $parts[] = (string)$key . '@' . (string)($s['h'] ?? '');
            }
            if (!empty($parts)) $lines[] = 'Section hash: ' . implode(', ', $parts);
        }
        $goal = !empty($ctx['_goal']) && is_string($ctx['_goal']) ? 'First request this session: ' . $ctx['_goal'] : '';
        if (empty($lines) && $goal === '') return '';
        // Whole lines only (a mid-line cut produced garbage like "Writes: home:hero @a1"); the goal line is always kept.
        $kept = [];
        $chars = 0;
        foreach ($lines as $line) {
            if ($chars + mb_strlen($line) > 1800) continue;
            $kept[] = $line;
            $chars += mb_strlen($line) + 1;
        }
        if ($goal !== '') array_unshift($kept, $goal);
        return "\n\n═══ SESSION STATE (numbers/ids already fetched this session — reuse them instead of repeating the same read; HTML is NOT stored here, so read a section once when you need verbatim markup) ═══\n" . implode("\n", $kept);
    }

    private static function buildPlanPrompt(): string {
        return PromptLoader::load('ai-studio-plan');
    }

    private static function buildBuildPrompt(): string {
        return PromptLoader::load('ai-studio-build');
    }
}
