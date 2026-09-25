<?php
// path: ./models/ai/tools/SerpTools.php
// READ-ONLY live Google SERP via SerpClient (pay-per-search — call once per query per request and reuse).

require_once BASE_PATH . '/models/SerpClient.php';

class SerpTools {

    public static function definitions(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'serp_search',
                    'description' => 'Live Google search via SerpApi: top organic results (title/link/snippet/position) plus answer box, knowledge graph and local pack when present. Use to check current niche demand, competitor pages and SERP features for a query. Paid API with 1h cache — call once per query per request and reuse.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Search query, e.g. "выкуп холодильников Ташкент" (2-200 chars).'],
                            'num' => ['type' => 'integer', 'description' => 'Max organic results (default 10, max 10).'],
                            'hl' => ['type' => 'string', 'description' => 'Interface language (default ru).'],
                            'gl' => ['type' => 'string', 'description' => 'Geo country code (default uz).'],
                            'location' => ['type' => 'string', 'description' => 'Full location (default "Tashkent, Uzbekistan").'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'serp_niche_overview',
                    'description' => 'Niche standing for a query: related searches, People Also Ask, and top competing domains by frequency in the top 10. Use to learn what the niche currently asks for and who ranks. Same paid call as serp_search (shared 1h cache) — call once per query per request and reuse.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Seed query for the niche (2-200 chars).'],
                            'hl' => ['type' => 'string', 'description' => 'Interface language (default ru).'],
                            'gl' => ['type' => 'string', 'description' => 'Geo country code (default uz).'],
                            'location' => ['type' => 'string', 'description' => 'Full location (default "Tashkent, Uzbekistan").'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        switch ($name) {
            case 'serp_search': return self::search($args);
            case 'serp_niche_overview': return self::nicheOverview($args);
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    private static function ensureConfigured(): void {
        if (!SerpClient::isConfigured()) {
            throw new InvalidArgumentException('SerpApi not connected — set SERPAPI_API_KEY in .env (https://serpapi.com).');
        }
    }

    private static function locale(array $args): array {
        $hl = preg_match('/^[a-z]{2}$/i', (string)($args['hl'] ?? 'ru')) ? strtolower((string)$args['hl']) : 'ru';
        $gl = preg_match('/^[a-z]{2}$/i', (string)($args['gl'] ?? 'uz')) ? strtolower((string)$args['gl']) : 'uz';
        $location = trim((string)($args['location'] ?? 'Tashkent, Uzbekistan'));
        if ($location === '') $location = 'Tashkent, Uzbekistan';
        if (mb_strlen($location) > 120) throw new InvalidArgumentException('location too long (max 120 chars)');
        return [$gl, $hl, $location];
    }

    private static function clean($s, int $max): string {
        $s = trim(strip_tags((string)$s));
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) . '…' : $s;
    }

    private static function domainOf(string $link): string {
        $host = parse_url($link, PHP_URL_HOST);
        return is_string($host) ? mb_strtolower($host) : '';
    }

    private static function organics(array $data, int $num): array {
        $out = [];
        foreach (array_slice($data['organic_results'] ?? [], 0, $num) as $r) {
            if (!is_array($r)) continue;
            $link = (string)($r['link'] ?? '');
            if ($link === '') continue;
            $out[] = [
                'position' => (int)($r['position'] ?? 0),
                'title' => self::clean($r['title'] ?? '', 200),
                'link' => $link,
                'domain' => self::domainOf($link),
                'snippet' => self::clean($r['snippet'] ?? '', 300),
            ];
        }
        return $out;
    }

    private static function search(array $args): array {
        self::ensureConfigured();
        [$gl, $hl, $location] = self::locale($args);
        $num = isset($args['num']) ? max(1, min(10, (int)$args['num'])) : 10;
        $query = trim((string)($args['query'] ?? ''));
        if ($query === '') throw new InvalidArgumentException('query is required, e.g. "выкуп холодильников Ташкент"');

        $data = SerpClient::search($query, $gl, $hl, $location, $num);
        $info = $data['search_information'] ?? [];
        $organics = self::organics($data, $num);

        $answer = null;
        if (isset($data['answer_box']) && is_array($data['answer_box'])) {
            $a = $data['answer_box'];
            $answer = ['title' => self::clean($a['title'] ?? '', 200), 'answer' => self::clean($a['answer'] ?? ($a['snippet'] ?? ''), 400), 'link' => (string)($a['link'] ?? '')];
        }
        $knowledge = null;
        if (isset($data['knowledge_graph']) && is_array($data['knowledge_graph'])) {
            $k = $data['knowledge_graph'];
            $knowledge = ['title' => self::clean($k['title'] ?? '', 200), 'type' => self::clean($k['type'] ?? '', 80), 'description' => self::clean($k['description'] ?? '', 400)];
        }
        $local = [];
        foreach (array_slice($data['local_results'] ?? $data['local_pack'] ?? [], 0, 3) as $l) {
            if (!is_array($l)) continue;
            $local[] = ['title' => self::clean($l['title'] ?? '', 200), 'address' => self::clean($l['address'] ?? '', 200), 'phone' => self::clean($l['phone'] ?? '', 40)];
        }

        return [
            'query' => $query, 'gl' => $gl, 'hl' => $hl, 'location' => $location,
            'total_results' => $info['total_results'] ?? null,
            'organic' => $organics,
            'count' => count($organics),
            'answer_box' => $answer,
            'knowledge_graph' => $knowledge,
            'local_pack' => $local,
        ];
    }

    private static function nicheOverview(array $args): array {
        self::ensureConfigured();
        [$gl, $hl, $location] = self::locale($args);
        $query = trim((string)($args['query'] ?? ''));
        if ($query === '') throw new InvalidArgumentException('query is required, e.g. "скупка мебели Ташкент"');

        // Shared 1h cache with serp_search — no extra charge on repeat queries.
        $data = SerpClient::search($query, $gl, $hl, $location, 10);
        $organics = self::organics($data, 10);

        $related = [];
        foreach (array_slice($data['related_searches'] ?? [], 0, 8) as $r) {
            $q = self::clean(is_array($r) ? ($r['query'] ?? '') : (string)$r, 150);
            if ($q !== '') $related[] = $q;
        }
        $paa = [];
        foreach (array_slice($data['related_questions'] ?? [], 0, 8) as $q) {
            if (!is_array($q)) continue;
            $question = self::clean($q['question'] ?? '', 200);
            if ($question === '') continue;
            $paa[] = ['question' => $question, 'snippet' => self::clean($q['snippet'] ?? '', 300), 'link' => (string)($q['link'] ?? '')];
        }
        $freq = [];
        foreach ($organics as $o) {
            if ($o['domain'] !== '') $freq[$o['domain']] = ($freq[$o['domain']] ?? 0) + 1;
        }
        arsort($freq);
        $domains = [];
        foreach (array_slice($freq, 0, 10, true) as $d => $c) $domains[] = ['domain' => $d, 'results_in_top10' => $c];

        return [
            'query' => $query, 'gl' => $gl, 'hl' => $hl, 'location' => $location,
            'related_searches' => $related,
            'people_also_ask' => $paa,
            'top_domains' => $domains,
            'organic_count' => count($organics),
        ];
    }
}
