<?php
// path: ./scripts/freshness-run.php
// Weekly cron: refresh the site-wide freshness block (fresh_blocks) from REAL data only.
//   php scripts/freshness-run.php --dry-run     print what would be written, save nothing
//   php scripts/freshness-run.php               write if the underlying facts changed
//   php scripts/freshness-run.php --force       write even if facts are unchanged
//   php scripts/freshness-run.php --rollback    make the previous block live again
// cPanel cron (weekly, Mon 06:00):  /usr/local/bin/php /home/kuplyuta/scripts/freshness-run.php
// Kill switch: FRESHNESS_DISABLED=1 in .env. Model: FRESHNESS_MODEL (default deepseek/deepseek-chat).
// Page bodies are never touched. The date shown on the site is only bumped when the facts changed.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/database.php';
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/models/FreshBlock.php';

$opts = array_flip(array_slice($argv, 1));
$dry = isset($opts['--dry-run']);
$force = isset($opts['--force']);

function out(string $msg): void { fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] $msg\n"); }
function fail(string $msg, int $code = 1): void { fwrite(STDERR, '[' . date('Y-m-d H:i:s') . "] ERROR: $msg\n"); exit($code); }

if (getenv('FRESHNESS_DISABLED') === '1') { out('FRESHNESS_DISABLED=1 — nothing to do'); exit(0); }

if (isset($opts['--rollback'])) {
    out(FreshBlock::rollback() ? 'Rolled back to the previous block' : 'No previous block to roll back to');
    exit(0);
}

$db = Database::getInstance();

// ── 1. Gather facts (real data only) ───────────────────────
$facts = [];

$minRequests = max(1, (int)(getenv('FRESHNESS_MIN_REQUESTS') ?: 5));
try {
    $row = $db->fetchOne("SELECT COUNT(*) c FROM product_requests WHERE created_at >= (NOW() - INTERVAL 7 DAY)");
    $n = (int)($row['c'] ?? 0);
    if ($n >= $minRequests) $facts['requests_7d'] = $n;
} catch (Throwable $e) {
    out('product_requests unavailable: ' . $e->getMessage());
}

$topQueries = [];
try {
    require_once BASE_PATH . '/models/GscClient.php';
    if (GscClient::isConfigured() && GscClient::isConnected()) {
        $end = date('Y-m-d', strtotime('-3 days')); // GSC data lags ~2-3 days
        $start = date('Y-m-d', strtotime('-9 days'));
        $rows = GscSearchApi::searchAnalytics($start, $end, ['query'], [], 50);
        if (is_array($rows)) {
            usort($rows, fn($a, $b) => ($b['impressions'] ?? 0) <=> ($a['impressions'] ?? 0));
            foreach ($rows as $r) {
                $q = trim((string)($r['keys'][0] ?? ''));
                if ($q === '' || mb_strlen($q) > 60 || !preg_match('/^[\p{L}\p{N}\s\-]+$/u', $q)) continue;
                if (mb_stripos($q, 'kuplyu') !== false) continue; // skip branded queries
                $topQueries[] = mb_strtolower($q);
                if (count($topQueries) >= 5) break;
            }
        } else {
            out('GSC returned no data (auth required?) — skipping query facts');
        }
    }
} catch (Throwable $e) {
    out('GSC unavailable: ' . $e->getMessage());
}
if ($topQueries) $facts['top_queries_7d'] = $topQueries;

$seasons = (array)(@include BASE_PATH . '/config/freshness_seasons.php');
$season = $seasons[(int)date('n')] ?? null;
$seasonText = ['ru' => '', 'uz' => ''];
if (is_array($season) && !empty($season['ru']) && !empty($season['uz'])) {
    $seasonText = ['ru' => trim($season['ru']), 'uz' => trim($season['uz'])];
    $facts['season_month'] = (int)date('n');
}

if (!$facts) { out('No usable facts this week — block left unchanged'); exit(0); }

// ── 2. Skip when nothing changed (no fake freshness) ───────
$last = $db->fetchOne("SELECT data_json FROM fresh_blocks WHERE block_key = 'site' AND lang = 'ru' AND status = 'live' ORDER BY id DESC LIMIT 1");
if (!$force && $last && json_decode((string)$last['data_json'], true) === $facts) {
    out('Facts unchanged since the live block — not bumping the date');
    exit(0);
}

// ── 3. Generate the demand line (RU + UZ) ──────────────────
$demand = ['ru' => '', 'uz' => ''];
if (isset($facts['requests_7d']) || isset($facts['top_queries_7d'])) {
    require_once BASE_PATH . '/models/OpenRouter.php';
    $model = getenv('FRESHNESS_MODEL') ?: 'deepseek/deepseek-chat';
    $input = array_intersect_key($facts, ['requests_7d' => 1, 'top_queries_7d' => 1]);
    $system = "You write ONE short line for the footer of a Tashkent appliance-buying website (kuplyu-tashkent.uz).\n"
        . "Use ONLY the facts in the JSON. Do not add numbers, prices, discounts, offers, brands or claims that are not in it.\n"
        . "requests_7d = number of buy requests received in the last 7 days. top_queries_7d = what visitors searched for this week.\n"
        . "Write 1-2 plain sentences, max 200 characters per language, no HTML, no links, no emoji.\n"
        . "RU: natural Russian. UZ: natural Uzbek in Latin script (not a word-for-word translation). Both must say the same thing.\n"
        . "Answer with JSON only: {\"ru\":\"...\",\"uz\":\"...\"}";
    $attempt = 0;
    while ($attempt < 2 && $demand['ru'] === '') {
        $attempt++;
        try {
            $raw = OpenRouter::chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_UNICODE)],
            ], $model, 0.4, 600, 1);
        } catch (Throwable $e) {
            fail('LLM call failed: ' . $e->getMessage());
        }
        $err = validateDemand($raw, $facts, $parsed);
        if ($err === null) { $demand = $parsed; }
        else out("Attempt $attempt rejected: $err");
    }
    if ($demand['ru'] === '' && !array_filter($seasonText)) fail('Generated text failed validation twice — nothing written', 2);
}

$body = [
    'ru' => ['demand' => $demand['ru'], 'season' => $seasonText['ru']],
    'uz' => ['demand' => $demand['uz'], 'season' => $seasonText['uz']],
];

// ── 4. Write (or print) ────────────────────────────────────
out(($dry ? 'DRY RUN — would write' : 'Writing') . ":\n" . json_encode(['facts' => $facts, 'body' => $body], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
if (!$dry) {
    FreshBlock::publish($body, $facts);
    writeReport($facts, $body);
    out('Done');
}

// ── helpers ────────────────────────────────────────────────

/** @return string|null error message, or null when valid ($out filled) */
function validateDemand(string $raw, array $facts, ?array &$out): ?string {
    $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', $raw));
    $j = json_decode($raw, true);
    if (!is_array($j) || !isset($j['ru'], $j['uz'])) return 'not valid {"ru","uz"} JSON';
    $ru = trim((string)$j['ru']);
    $uz = trim((string)$j['uz']);
    foreach (['ru' => $ru, 'uz' => $uz] as $l => $t) {
        $len = mb_strlen($t);
        if ($len < 20 || $len > 220) return "$l length $len out of range";
        if ($t !== strip_tags($t) || preg_match('~https?:|www\.|@~i', $t)) return "$l contains markup/link";
    }
    if (!preg_match('/\p{Cyrillic}/u', $ru)) return 'ru has no Cyrillic';
    if (preg_match('/\p{Cyrillic}/u', $uz)) return 'uz must be Latin script';
    $allowed = isset($facts['requests_7d']) ? [(string)$facts['requests_7d']] : [];
    preg_match_all('/\d+/', $ru, $nr);
    preg_match_all('/\d+/', $uz, $nu);
    if (array_diff($nr[0], $allowed) || array_diff($nu[0], $allowed)) return 'contains a number that is not in the input';
    sort($nr[0]); sort($nu[0]);
    if ($nr[0] !== $nu[0]) return 'RU/UZ numbers differ';
    $out = ['ru' => $ru, 'uz' => $uz];
    return null;
}

function writeReport(array $facts, array $body): void {
    $dir = BASE_PATH . '/reports/seo';
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) return;
    $md = "# Freshness block — " . date('Y-m-d H:i') . "\n\nSource: product_requests (DB), GSC searchAnalytics (7d, fetched " . date('c') . ")\n\n"
        . "```json\n" . json_encode(['facts' => $facts, 'body' => $body], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n```\n";
    @file_put_contents($dir . '/' . date('Y-m-d') . '-freshness.md', $md);
}
