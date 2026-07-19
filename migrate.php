<?php
/**
 * Schema migration runner.
 *
 * Usage:  php migrate.php
 *
 * Scans migrations/*.php for numbered migration files, runs any that
 * haven't been applied yet (tracked in the schema_migrations table),
 * and records each successful run.
 *
 * Convention: files are applied in filename order.  Prefix with a
 * zero-padded number (e.g. 001_add_foo.php, 002_add_bar.php).
 *
 * IMPORTANT: Schema changes go here, NEVER in request-path files
 * such as core/helpers.php.  Historically the app ran ALTER TABLE
 * on every page load — that pattern is prohibited going forward.
 *
 * Each file returns an array of SQL strings.  The runner executes
 * them sequentially.  If any statement fails, the runner prints the
 * error and exits with code 1 — the migration that failed is NOT
 * recorded, so it can be retried after fixing the issue.
 *
 * NOTE: ALTER TABLE statements cause implicit commits in MySQL, so
 * individual migrations are NOT wrapped in a DB transaction.
 */

// ── Bootstrap ──────────────────────────────────────────────

define('BASE_PATH', __DIR__);

require BASE_PATH . '/config/database.php';
require BASE_PATH . '/core/Database.php';

// ── Ensure tracking table exists ───────────────────────────

try {
    $db = Database::getInstance();
    $db->query("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration_name VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
} catch (Exception $e) {
    fwrite(STDERR, "FATAL: Could not create schema_migrations table: " . $e->getMessage() . "\n");
    exit(1);
}

// ── Scan migration files ───────────────────────────────────

$dir = BASE_PATH . '/migrations';
$files = glob($dir . '/*.php');
if (!$files) {
    echo "No migration files found in $dir\n";
    exit(0);
}

// Sort by filename to ensure predictable order
sort($files);

// Fetch already-applied migrations
$applied = [];
try {
    $rows = $db->fetchAll("SELECT migration_name FROM schema_migrations");
    foreach ($rows as $row) {
        $applied[$row['migration_name']] = true;
    }
} catch (Exception $e) {
    fwrite(STDERR, "FATAL: Could not read schema_migrations: " . $e->getMessage() . "\n");
    exit(1);
}

$ranCount = 0;

foreach ($files as $file) {
    $name = basename($file);

    if (isset($applied[$name])) {
        echo "  [SKIP] $name (already applied)\n";
        continue;
    }

    echo "  [RUN]  $name ... ";

    $queries = include $file;

    if (!is_array($queries)) {
        echo "FAILED\n";
        fwrite(STDERR, "ERROR: Migration $name did not return an array of SQL strings\n");
        exit(1);
    }

    // Execute each statement; stop on first failure
    $stmtIndex = 0;
    foreach ($queries as $sql) {
        $stmtIndex++;
        try {
            $db->query($sql);
        } catch (Exception $e) {
            echo "FAILED\n";
            fwrite(STDERR, "ERROR: Migration $name, statement #$stmtIndex failed:\n");
            fwrite(STDERR, "  SQL:    " . substr($sql, 0, 200) . (strlen($sql) > 200 ? '...' : '') . "\n");
            fwrite(STDERR, "  ERROR:  " . $e->getMessage() . "\n");
            exit(1);
        }
    }

    // Record as applied
    try {
        $db->query(
            "INSERT INTO schema_migrations (migration_name) VALUES (?)",
            [$name]
        );
    } catch (Exception $e) {
        echo "FAILED\n";
        fwrite(STDERR, "ERROR: Could not record migration $name in schema_migrations: " . $e->getMessage() . "\n");
        exit(1);
    }

    echo "OK\n";
    $ranCount++;
}

echo "\nDone. $ranCount migration(s) applied.\n";
