<?php
// path: ./scripts/cleanup-media.php
// Frees disk space in the uploads folder. Owner-run CLI (cPanel terminal or cron); REPORT ONLY unless --apply.
//   php scripts/cleanup-media.php                 report: sizes, orphans, what lean mode would remove
//   php scripts/cleanup-media.php --apply         delete (1) derived copies whose original no longer exists in the media table,
//                                                 (2) in lean mode (default): derived JPG copies and widths other than 480/768/1366
//   php scripts/cleanup-media.php --apply --originals   ALSO delete files in uploads/ that have no media row (be sure!)
// Lean-mode leftovers are safe to delete: the site finds whichever derived files exist, and the original is the plain <img src>.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/database.php';
require BASE_PATH . '/core/Database.php';
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', rtrim((string)(getenv('UPLOAD_PATH') ?: BASE_PATH . '/public/uploads'), '/\\') . '/');
require BASE_PATH . '/services/ImageVariants.php';

$apply = in_array('--apply', $argv, true);
$originals = in_array('--originals', $argv, true);

function mb($bytes) { return number_format($bytes / 1048576, 2) . ' MB'; }

$uploads = rtrim(UPLOAD_PATH, '/\\');
if (!is_dir($uploads)) exit("Uploads folder not found: $uploads\n");
$derivedDir = $uploads . '/derived';

$known = [];
foreach (Database::getInstance()->fetchAll("SELECT filename FROM media") as $r) {
    $known[pathinfo($r['filename'], PATHINFO_FILENAME)] = $r['filename'];
}

$lean = ImageVariants::leanMode();
$leanWidths = ImageVariants::leanWidths();

$orphanDerived = $extraDerived = $orphanOriginals = [];
$totalDerived = $totalOriginals = 0;

if (is_dir($derivedDir)) {
    foreach (new DirectoryIterator($derivedDir) as $f) {
        if (!$f->isFile()) continue;
        $totalDerived += $f->getSize();
        if (!preg_match('/^(.+)_w(\d+)\.(jpg|webp)$/i', $f->getFilename(), $m)) continue;
        if (!isset($known[$m[1]])) {
            $orphanDerived[] = [$f->getPathname(), $f->getSize()];
        } elseif ($lean && (strtolower($m[3]) === 'jpg' || !in_array((int)$m[2], $leanWidths, true))) {
            $extraDerived[] = [$f->getPathname(), $f->getSize()];
        }
    }
}
foreach (new DirectoryIterator($uploads) as $f) {
    if (!$f->isFile() || $f->getFilename() === '.htaccess') continue;
    $totalOriginals += $f->getSize();
    if (!isset($known[pathinfo($f->getFilename(), PATHINFO_FILENAME)])) $orphanOriginals[] = [$f->getPathname(), $f->getSize()];
}

function sumBytes(array $list) { return array_sum(array_column($list, 1)); }

echo "Uploads folder: $uploads\n";
echo "  originals:         " . mb($totalOriginals) . "\n";
echo "  derived variants:  " . mb($totalDerived) . "\n";
echo "  media rows:        " . count($known) . "   (lean mode: " . ($lean ? 'ON' : 'off') . ")\n\n";
printf("Orphan derived files (original deleted):     %4d files  %s\n", count($orphanDerived), mb(sumBytes($orphanDerived)));
printf("Extra derived files (not needed in lean):    %4d files  %s\n", count($extraDerived), mb(sumBytes($extraDerived)));
printf("Files in uploads/ with no media row:         %4d files  %s%s\n", count($orphanOriginals), mb(sumBytes($orphanOriginals)), $originals ? '' : '  (kept unless --originals)');

$freeable = sumBytes($orphanDerived) + sumBytes($extraDerived) + ($originals ? sumBytes($orphanOriginals) : 0);
echo "\nWould free: " . mb($freeable) . "\n";

if (!$apply) {
    echo "\nDry run — nothing deleted. Re-run with --apply to delete.\n";
    exit(0);
}

$deleted = 0;
$lists = [$orphanDerived, $extraDerived];
if ($originals) $lists[] = $orphanOriginals;
foreach ($lists as $list) {
    foreach ($list as [$path]) {
        if (@unlink($path)) $deleted++;
    }
}
echo "\nDeleted $deleted files.\n";
