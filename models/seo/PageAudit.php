<?php
// path: ./models/seo/PageAudit.php
// Read-only on-page SEO audit over the published pages table (no GSC, no HTTP). Pure functions: array in, array out.
// Thresholds are common heuristics, not rules Google publishes — each finding carries the measured value so the
// caller can judge. Mirrors the AGENTS.md doctrine (RU/UZ parity, one H1, no inline style, alt text).

class PageAudit {
    public const TITLE_MAX = 60;
    public const TITLE_MIN = 25;
    public const DESC_MAX = 160;
    public const DESC_MIN = 70;
    public const THIN_WORDS = 200;
    private const SEVERITY_RANK = ['info' => 1, 'warn' => 2, 'error' => 3];
    private const LANGS = ['ru', 'uz'];

    /**
     * @param array  $pages   rows from Page::getAll() (published)
     * @param string $ownHost host of the site, e.g. kuplyu-tashkent.uz (for canonical checks)
     * @param array  $opts    lang (ru|uz|both), min_severity (info|warn|error), only_issues (bool), slug (string)
     */
    public static function run(array $pages, string $ownHost, array $opts = []): array {
        $lang = $opts['lang'] ?? 'both';
        $langs = $lang === 'both' ? self::LANGS : [$lang];
        $minRank = self::SEVERITY_RANK[$opts['min_severity'] ?? 'info'] ?? 1;
        $onlyIssues = $opts['only_issues'] ?? true;
        $slugFilter = (string)($opts['slug'] ?? '');

        $dupTitle = $dupDesc = [];
        foreach ($pages as $p) {
            foreach (self::LANGS as $l) {
                $t = mb_strtolower(trim((string)($p["meta_title_$l"] ?? '')));
                $d = mb_strtolower(trim((string)($p["meta_description_$l"] ?? '')));
                if ($t !== '') $dupTitle[$l][$t][] = $p['slug'];
                if ($d !== '') $dupDesc[$l][$d][] = $p['slug'];
            }
        }
        $inbound = self::inboundLinkCounts($pages);

        $out = [];
        $byCode = [];
        $bySeverity = ['error' => 0, 'warn' => 0, 'info' => 0];

        foreach ($pages as $p) {
            $slug = (string)$p['slug'];
            if ($slugFilter !== '' && $slug !== $slugFilter) continue;
            $issues = [];
            $metrics = [];

            // RU↔UZ parity (AGENTS.md: UZ must match RU before publish)
            foreach (['title', 'meta_title', 'meta_description', 'content'] as $field) {
                $ru = trim((string)($p["{$field}_ru"] ?? ''));
                $uz = trim((string)($p["{$field}_uz"] ?? ''));
                if ($ru !== '' && $uz === '') $issues[] = self::issue('parity_missing_uz', 'error', null, "{$field}_uz is empty while {$field}_ru is set");
                if ($uz !== '' && $ru === '') $issues[] = self::issue('parity_missing_ru', 'error', null, "{$field}_ru is empty while {$field}_uz is set");
            }

            $canonical = trim((string)($p['canonical_url'] ?? ''));
            if ($canonical !== '') {
                $host = strtolower((string)parse_url($canonical, PHP_URL_HOST));
                if ($host !== '' && preg_replace('/^www\./', '', $host) !== $ownHost) {
                    $issues[] = self::issue('canonical_off_domain', 'error', null, $canonical);
                }
            }

            foreach ($langs as $l) {
                $title = trim((string)($p["meta_title_$l"] ?? ''));
                $desc = trim((string)($p["meta_description_$l"] ?? ''));
                $titleLen = mb_strlen($title);
                $descLen = mb_strlen($desc);
                $metrics["meta_title_len_$l"] = $titleLen;
                $metrics["meta_description_len_$l"] = $descLen;

                if ($title === '') $issues[] = self::issue('missing_meta_title', 'warn', $l, 'meta_title_' . $l . ' is empty (the page title may be used instead)');
                elseif ($titleLen > self::TITLE_MAX) $issues[] = self::issue('meta_title_too_long', 'warn', $l, "$titleLen chars (> " . self::TITLE_MAX . ')');
                elseif ($titleLen < self::TITLE_MIN) $issues[] = self::issue('meta_title_too_short', 'info', $l, "$titleLen chars (< " . self::TITLE_MIN . ')');

                if ($desc === '') $issues[] = self::issue('missing_meta_description', 'warn', $l, 'meta_description_' . $l . ' is empty');
                elseif ($descLen > self::DESC_MAX) $issues[] = self::issue('meta_description_too_long', 'warn', $l, "$descLen chars (> " . self::DESC_MAX . ')');
                elseif ($descLen < self::DESC_MIN) $issues[] = self::issue('meta_description_too_short', 'info', $l, "$descLen chars (< " . self::DESC_MIN . ')');

                if ($title !== '') {
                    $same = array_values(array_diff($dupTitle[$l][mb_strtolower($title)] ?? [], [$slug]));
                    if ($same) $issues[] = self::issue('duplicate_meta_title', 'warn', $l, 'also on: ' . implode(', ', array_slice($same, 0, 5)));
                }
                if ($desc !== '') {
                    $same = array_values(array_diff($dupDesc[$l][mb_strtolower($desc)] ?? [], [$slug]));
                    if ($same) $issues[] = self::issue('duplicate_meta_description', 'warn', $l, 'also on: ' . implode(', ', array_slice($same, 0, 5)));
                }

                $html = (string)($p["content_$l"] ?? '');
                if ($html === '') continue;
                $words = self::wordCount($html);
                $metrics["words_$l"] = $words;
                if ($words < self::THIN_WORDS) $issues[] = self::issue('thin_content', 'warn', $l, "$words words (< " . self::THIN_WORDS . ')');
                if (preg_match('/<h1[\s>]/i', $html)) $issues[] = self::issue('h1_in_content', 'warn', $l, 'the hero already renders the page H1');
                $inline = preg_match_all('/\sstyle\s*=/i', $html);
                if ($inline) $issues[] = self::issue('inline_styles', 'info', $l, "$inline element(s) with style=\"\" (use .c-* classes)");
                $noAlt = self::imagesWithoutAlt($html);
                if ($noAlt) $issues[] = self::issue('images_missing_alt', 'warn', $l, "$noAlt <img> without alt");
            }

            $inboundCount = $inbound[$slug] ?? 0;
            $metrics['inbound_content_links'] = $inboundCount;
            if ($inboundCount === 0 && $slug !== 'main') {
                $issues[] = self::issue('no_inbound_content_links', 'info', null, 'no other page links here from its body content (link widgets / navigation are not counted)');
            }

            $issues = array_values(array_filter($issues, static fn($i) => self::SEVERITY_RANK[$i['severity']] >= $minRank));
            foreach ($issues as $i) {
                $byCode[$i['code']] = ($byCode[$i['code']] ?? 0) + 1;
                $bySeverity[$i['severity']]++;
            }
            if ($onlyIssues && !$issues) continue;
            $out[] = [
                'slug' => $slug,
                'id' => (int)($p['id'] ?? 0),
                'updated_at' => $p['updated_at'] ?? null,
                'issue_count' => count($issues),
                'issues' => $issues,
                'metrics' => $metrics,
            ];
        }

        usort($out, static fn($a, $b) => $b['issue_count'] <=> $a['issue_count']);
        arsort($byCode);

        return [
            'pages_checked' => $slugFilter !== '' ? count(array_filter($pages, static fn($p) => $p['slug'] === $slugFilter)) : count($pages),
            'pages_with_issues' => count(array_filter($out, static fn($r) => $r['issue_count'] > 0)),
            'issues_by_severity' => $bySeverity,
            'issues_by_code' => $byCode,
            'pages' => $out,
            'thresholds' => [
                'meta_title' => [self::TITLE_MIN, self::TITLE_MAX], 'meta_description' => [self::DESC_MIN, self::DESC_MAX],
                'thin_content_words' => self::THIN_WORDS,
                'note' => 'Heuristic thresholds, not Google rules. Inbound counts use links in page bodies only.',
            ],
        ];
    }

    private static function issue(string $code, string $severity, ?string $lang, string $detail): array {
        return ['code' => $code, 'severity' => $severity, 'lang' => $lang, 'detail' => $detail];
    }

    private static function wordCount(string $html): int {
        $text = strip_tags(preg_replace('#<(script|style)\b.*?</\1>#is', ' ', $html));
        return preg_match_all('/\p{L}[\p{L}\p{N}\'’-]*/u', $text);
    }

    private static function imagesWithoutAlt(string $html): int {
        if (!preg_match_all('/<img\b[^>]*>/i', $html, $m)) return 0;
        $n = 0;
        foreach ($m[0] as $tag) {
            if (!preg_match('/\salt\s*=\s*("[^"]*\S[^"]*"|\'[^\']*\S[^\']*\')/i', $tag)) $n++;
        }
        return $n;
    }

    /** @return array<string,int> slug => number of OTHER pages whose body links to it */
    private static function inboundLinkCounts(array $pages): array {
        $slugs = array_flip(array_map(static fn($p) => (string)$p['slug'], $pages));
        $counts = [];
        foreach ($pages as $p) {
            $from = (string)$p['slug'];
            $targets = [];
            foreach (self::LANGS as $l) {
                $html = (string)($p["content_$l"] ?? '');
                if ($html === '' || !preg_match_all('/href\s*=\s*["\']([^"\'#?]+)/i', $html, $m)) continue;
                foreach ($m[1] as $href) {
                    $path = (string)(parse_url($href, PHP_URL_PATH) ?? '');
                    $segs = array_values(array_filter(explode('/', $path), static fn($s) => $s !== '' && $s !== 'ru' && $s !== 'uz'));
                    if (!$segs) continue;
                    $last = rawurldecode(end($segs));
                    if (isset($slugs[$last]) && $last !== $from) $targets[$last] = true;
                }
            }
            foreach (array_keys($targets) as $t) $counts[$t] = ($counts[$t] ?? 0) + 1;
        }
        return $counts;
    }
}
