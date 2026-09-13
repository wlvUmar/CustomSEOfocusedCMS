<?php
// One-off importer: public/css/components.css → ai_components (per-block, 95 markers + shared synthetic)
// Usage: php scripts/import-components.php [--dry-run]
// Dry-run prints counts; without flag it truncates and inserts.

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/database.php';
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/models/Component.php';

$dryRun = in_array('--dry-run', $argv, true);
$cssPath = BASE_PATH . '/public/css/components.css';
if (!is_file($cssPath)) { fwrite(STDERR, "Missing $cssPath\n"); exit(1); }
$raw = file_get_contents($cssPath);
if ($raw === false) { fwrite(STDERR, "Cannot read $cssPath\n"); exit(1); }

// Extract file header (initial /* ═══ ... */ block)
$header = '';
if (preg_match('/\A\/\*.*?═══════════════════════════════════════════════════════════════════════════ \*\/\s*\n/s', $raw, $m)) {
    $header = $m[0];
}
echo "Header bytes: " . strlen($header) . "\n";

// Shared helpers: between header and first "/* 1 ──"
$sharedCss = '';
$sharedStart = strlen($header);
$cat1Pos = strpos($raw, '/* 1 ──');
if ($cat1Pos !== false && $cat1Pos > $sharedStart) {
    $sharedRaw = substr($raw, $sharedStart, $cat1Pos - $sharedStart);
    // sharedRaw includes "/* ── Shared helpers ── */\n" + rules + blank line
    // Strip that header comment for storage
    $sharedCss = preg_replace('/\A\s*\/\* ── Shared helpers.*?\*\/\s*\n/s', '', $sharedRaw);
    $sharedCss = trim($sharedCss);
    echo "Shared helpers bytes: " . strlen($sharedCss) . "\n";
}

// Parse category headers: map position → category key
$catMap = [];
$sectionPattern = '/\/\* (\d+) ── .*?─{2,} \*\//';
if (preg_match_all($sectionPattern, $raw, $cm, PREG_OFFSET_CAPTURE)) {
    foreach ($cm[0] as $idx => $full) {
        $pos = $full[1];
        $num = (int)$cm[1][$idx][0];
        $cat = Component::sectionMap()[$num] ?? null;
        if ($cat) $catMap[$pos] = $cat;
    }
    ksort($catMap);
    echo "Category headers found: " . count($catMap) . "\n";
}

// Parse blocks: /* === C-NAME === */
$blocks = [];
$markerPat = '/\/\* === ([A-Z0-9\-_]+) === \*\//';
preg_match_all($markerPat, $raw, $mm, PREG_OFFSET_CAPTURE);
$markers = $mm[0];
$markerNames = $mm[1];
$total = count($markers);
echo "Markers (blocks) found: $total\n";

for ($i = 0; $i < $total; $i++) {
    $markerPos = $markers[$i][1];
    $markerLen = strlen($markers[$i][0]);
    $slugUpper = $markerNames[$i][0];
    $slug = strtolower($slugUpper);
    // Determine category: nearest preceding catMap entry
    $cat = 'utilities';
    foreach ($catMap as $cpos => $ckey) {
        if ($cpos < $markerPos) $cat = $ckey;
        else break;
    }
    $bodyStart = $markerPos + $markerLen;
    $bodyEnd = ($i + 1 < $total) ? $markers[$i+1][1] : strlen($raw);
    $body = substr($raw, $bodyStart, $bodyEnd - $bodyStart);
    // Body may start with newline, then CSS, then maybe category header before next marker
    // Strip leading/trailing whitespace and remove any embedded "/* N ── ... */" category headers at tail
    $body = ltrim($body, "\r\n");
    // Remove trailing category header that belongs to next block, if present at tail
    $body = preg_replace('/\s*\/\* \d+ ──.*?─{2,} \*\/\s*\z/s', '', $body);
    $body = trim($body);
    // Detect tokens
    preg_match_all('/var\(\s*(--[\w\-]+)/', $body, $tm);
    $tokens = $tm[1] ? array_values(array_unique($tm[1])) : [];
    sort($tokens);

    $blocks[] = [
        'slug' => $slug,
        'category' => $cat,
        'title' => ucwords(str_replace(['c-','-'], ['',' '], $slug)),
        'css_body' => $body,
        'tokens' => $tokens,
        'sort' => $i,
    ];
}

// Validate uniqueness
$slugs = array_column($blocks, 'slug');
if (count($slugs) !== count(array_unique($slugs))) {
    fwrite(STDERR, "Duplicate slugs detected!\n");
    exit(1);
}

echo "Prepared blocks: " . count($blocks) . " (unique slugs: " . count(array_unique($slugs)) . ")\n";
if ($dryRun) {
    foreach (array_slice($blocks, 0, 5) as $b) {
        echo "  {$b['slug']} [{$b['category']}] " . strlen($b['css_body']) . " bytes tokens=" . implode(',', $b['tokens']) . "\n";
    }
    echo "Dry-run done. Run without --dry-run to insert.\n";
    exit(0);
}

// Insert
$db = Database::getInstance();
// Ensure tables exist (in case migrate not yet run in this env)
try {
    $db->query("SELECT 1 FROM ai_components LIMIT 1");
} catch (Throwable $e) {
    fwrite(STDERR, "ai_components table missing — run php migrate.php first\n");
    exit(1);
}

$db->query("DELETE FROM ai_components");
echo "Cleared ai_components\n";

$inserted = 0;
// Insert shared synthetic first if present
if ($sharedCss !== '') {
    preg_match_all('/var\(\s*(--[\w\-]+)/', $sharedCss, $stm);
    $stokens = $stm[1] ? array_values(array_unique($stm[1])) : [];
    sort($stokens);
    $db->query(
        "INSERT INTO ai_components (slug, category, title, description, html_demo, css_body, variants, tokens_used, status, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        ['c-shared', 'shared', 'Shared helpers', 'Shared helpers (.c-section, .c-kicker, .c-title, etc.)', null, $sharedCss, null, $stokens ? json_encode($stokens) : null, 'active', -1]
    );
    $inserted++;
    echo "Inserted c-shared [shared] " . strlen($sharedCss) . " bytes\n";
}
foreach ($blocks as $b) {
    $db->query(
        "INSERT INTO ai_components (slug, category, title, description, html_demo, css_body, variants, tokens_used, status, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$b['slug'], $b['category'], $b['title'], null, null, $b['css_body'], null, $b['tokens'] ? json_encode($b['tokens']) : null, 'active', $b['sort']]
    );
    $inserted++;
}
echo "Inserted $inserted rows\n";

// Verify rebuild produces something close to original
$stats = Component::rebuildCss();
echo "Rebuild: rows={$stats['rows']} bytes_before={$stats['bytes_before']} bytes_after={$stats['bytes_after']} changed=" . ($stats['changed'] ? 'yes' : 'no') . " min_before={$stats['min']['bytes_before']} min_after={$stats['min']['bytes_after']}\n";
$orig = sha1_file($cssPath);
$new = sha1_file($cssPath);
echo "SHA1 after rebuild: $new (was based on original before rebuild backup .bak)\n";
if (is_file($cssPath . '.bak')) {
    $origSha = sha1_file($cssPath . '.bak');
    echo "SHA1 original (.bak): $origSha\n";
    // Compare ignoring header regeneration differences — check that all slugs present
    $newRaw = file_get_contents($cssPath);
    $missing = [];
    foreach ($slugs as $s) {
        $upper = strtoupper($s);
        if (strpos($newRaw, "/* === $upper === */") === false && $s !== 'c-shared') $missing[] = $s;
    }
    if ($missing) echo "Missing markers after rebuild: " . implode(',', $missing) . "\n";
    else echo "All markers present after rebuild\n";
}
echo "Done.\n";
