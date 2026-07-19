<?php
// Run: /usr/local/bin/php test-dedup-insert.php
require __DIR__ . '/config/database.php';
require __DIR__ . '/core/Database.php';

try {
    $db = Database::getInstance();
    
    $testId = 'diag_' . bin2hex(random_bytes(8));

    // Test INSERT IGNORE into dedup_clicks
    $r = $db->query(
        "INSERT IGNORE INTO analytics_dedup_clicks (visitor_id, page_slug, language, view_date, utm_source) VALUES (?, ?, 'ru', CURDATE(), 'diag_test')",
        [$testId, 'diag-page']
    );
    echo "1) Dedup INSERT IGNORE rowCount: " . var_export($r->rowCount(), true) . "\n";

    // Try same again (should be 0)
    $r2 = $db->query(
        "INSERT IGNORE INTO analytics_dedup_clicks (visitor_id, page_slug, language, view_date, utm_source) VALUES (?, ?, 'ru', CURDATE(), 'diag_test')",
        [$testId, 'diag-page']
    );
    echo "2) Second INSERT IGNORE rowCount (expect 0): " . var_export($r2->rowCount(), true) . "\n";

    // Count
    $row = $db->fetchOne("SELECT COUNT(*) as cnt FROM analytics_dedup_clicks WHERE visitor_id = ?", [$testId]);
    echo "3) Rows in dedup_clicks: " . $row['cnt'] . "\n";

    // Test analytics INSERT ... ON DUPLICATE KEY UPDATE
    $r3 = $db->query(
        "INSERT INTO analytics (page_slug, language, visits, clicks, phone_calls, utm_source, date)
         VALUES ('diag-page', 'ru', 0, 1, 0, 'diag_test', CURDATE())
         ON DUPLICATE KEY UPDATE clicks = clicks + 1"
    );
    echo "4) Analytics INSERT rowCount: " . var_export($r3->rowCount(), true) . "\n";

    // Check clicks
    $row2 = $db->fetchOne("SELECT clicks, utm_source FROM analytics WHERE page_slug = 'diag-page' AND date = CURDATE() AND utm_source = 'diag_test'");
    echo "5) analytics.clicks for diag_test: " . ($row2 ? $row2['clicks'] : 'ROW NOT FOUND') . "\n";

    // Do it again (should be 2 now)
    $r4 = $db->query(
        "INSERT INTO analytics (page_slug, language, visits, clicks, phone_calls, utm_source, date)
         VALUES ('diag-page', 'ru', 0, 1, 0, 'diag_test', CURDATE())
         ON DUPLICATE KEY UPDATE clicks = clicks + 1"
    );
    $row3 = $db->fetchOne("SELECT clicks FROM analytics WHERE page_slug = 'diag-page' AND date = CURDATE() AND utm_source = 'diag_test'");
    echo "6) analytics.clicks after 2nd (expect 2): " . ($row3 ? $row3['clicks'] : 'ROW NOT FOUND') . "\n";

    // Cleanup
    $db->query("DELETE FROM analytics_dedup_clicks WHERE visitor_id = ?", [$testId]);
    $db->query("DELETE FROM analytics WHERE page_slug = 'diag-page' AND utm_source = 'diag_test' AND date = CURDATE()");

    echo "\nAll OK\n";
} catch (Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
