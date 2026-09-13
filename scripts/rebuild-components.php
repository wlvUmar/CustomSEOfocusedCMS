<?php
// Rebuild public/css/components.css from ai_components if seeded.
// Safe to run on every deploy: no-ops when table missing or empty.
// Used by .cpanel.yml after migrate + public copy.

$paths = [
    '/home/kuplyuta/config/config.php',
    __DIR__ . '/../config/config.php',
    dirname(__DIR__) . '/config/config.php',
];
$loaded = false;
foreach ($paths as $p) { if (is_file($p)) { require $p; $loaded = true; break; } }
if (!$loaded) { echo "rebuild: config not found — skip\n"; exit(0); }

require BASE_PATH . '/config/database.php';
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/models/Component.php';

try {
    $db = Database::getInstance();
    try {
        $row = $db->fetchOne('SELECT COUNT(*) c FROM ai_components');
    } catch (Throwable $e) {
        echo "ai_components table missing — skip rebuild\n";
        exit(0);
    }
    $cnt = (int)($row['c'] ?? 0);
    if ($cnt === 0) {
        echo "ai_components empty — skip rebuild (run scripts/import-components.php once to seed)\n";
        exit(0);
    }
    $stats = Component::rebuildCss();
    echo "components rebuilt: " . json_encode($stats) . "\n";
    // rebuild writes to BASE_PATH/public; sync to public_html if different
    $srcCss = BASE_PATH . '/public/css/components.css';
    $srcMin = BASE_PATH . '/public/css/components.min.css';
    $dstCss = '/home/kuplyuta/public_html/css/components.css';
    $dstMin = '/home/kuplyuta/public_html/css/components.min.css';
    if (is_file($srcCss) && $srcCss !== $dstCss) @copy($srcCss, $dstCss);
    if (is_file($srcMin) && $srcMin !== $dstMin) @copy($srcMin, $dstMin);
    if (is_file($dstCss)) echo "components synced to public_html\n";
} catch (Throwable $e) {
    echo "component rebuild skip: " . $e->getMessage() . "\n";
    exit(0);
}
