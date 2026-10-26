<?php
// path: ./scripts/issue-seo-token.php
// Owner-only CLI for read-only SEO API tokens (seo_api_tokens). Agents must not run this.
//   php scripts/issue-seo-token.php issue --label=hermes-seo --scopes=pages,stats,gsc --days=30
//   php scripts/issue-seo-token.php list
//   php scripts/issue-seo-token.php revoke --id=3
// The plaintext token is printed once; only its sha256 is stored.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/database.php';
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/models/SeoApiTokenStore.php';

function usage(): void {
    fwrite(STDERR, "Usage:\n"
        . "  php scripts/issue-seo-token.php issue --label=NAME [--scopes=pages,stats,gsc[,write]] [--days=30]\n"
        . "    (write is opt-in: add it explicitly; default scopes are read-only)\n"
        . "  php scripts/issue-seo-token.php list\n"
        . "  php scripts/issue-seo-token.php revoke --id=N\n");
    exit(2);
}

$args = array_slice($argv, 1);
// forgiving: `php issue-seo-token.php --label=x` (subcommand omitted) means `issue`
if ($args && str_starts_with($args[0], '--')) array_unshift($args, 'issue');
$cmd = $args[0] ?? '';
$opts = [];
foreach (array_slice($args, 1) as $arg) {
    if (preg_match('/^--([a-z]+)=(.*)$/', $arg, $m)) $opts[$m[1]] = $m[2];
    else usage();
}

try {
    switch ($cmd) {
        case 'issue':
            $label = $opts['label'] ?? '';
            $scopes = array_filter(array_map('trim', explode(',', $opts['scopes'] ?? 'pages,stats,gsc')));
            $days = isset($opts['days']) ? (int)$opts['days'] : SeoApiTokenStore::DEFAULT_DAYS;
            $t = SeoApiTokenStore::issue($label, $scopes, $days);
            echo "Issued SEO API token #{$t['id']} ({$t['label']}), scopes={$t['scopes']}, expires={$t['expires_at']}\n";
            echo "TOKEN (shown once, store it in the Hermes secret store):\n{$t['token']}\n";
            break;

        case 'list':
            $rows = SeoApiTokenStore::listAll();
            if (!$rows) { echo "No tokens.\n"; break; }
            foreach ($rows as $r) {
                $state = $r['revoked_at'] ? 'REVOKED ' . $r['revoked_at'] : (strtotime($r['expires_at']) > time() ? 'active' : 'expired');
                printf("#%d  %-20s scopes=%-18s expires=%s last_used=%s  [%s]\n",
                    $r['id'], $r['label'], $r['scopes'], $r['expires_at'], $r['last_used_at'] ?? '-', $state);
            }
            break;

        case 'revoke':
            if (!isset($opts['id']) || !ctype_digit($opts['id'])) usage();
            echo SeoApiTokenStore::revoke((int)$opts['id'])
                ? "Revoked token #{$opts['id']}\n"
                : "No active token #{$opts['id']} (unknown or already revoked)\n";
            break;

        default:
            usage();
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
