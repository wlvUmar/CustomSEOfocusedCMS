<?php
// Run from project root
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/core/Database.php';

echo "Testing Analytics Query...\n\n";

try {
    $db = Database::getInstance();
    $year = date('Y');
    $month = date('n');
    $slug = 'test-slug';
    $lang = 'ru';
    
    // Test the SELECT part first
    echo "Testing SELECT...\n";
    $sqlSelect = "SELECT page_slug, language, YEAR(date), MONTH(date), 
                       SUM(visits), SUM(clicks), SUM(phone_calls), COUNT(DISTINCT date)
                FROM analytics
                WHERE page_slug = ? AND language = ? AND YEAR(date) = ? AND MONTH(date) = ?
                GROUP BY page_slug, language, YEAR(date), MONTH(date)";
    
    $result = $db->fetchAll($sqlSelect, [$slug, $lang, $year, $month]);
    echo "SELECT success! Rows: " . count($result) . "\n";
    
    // Test the FULL INSERT
    echo "Testing INSERT...\n";
    $sql = "INSERT INTO analytics_monthly (page_slug, language, year, month, total_visits, total_clicks, total_phone_calls, unique_days)
                SELECT page_slug, language, YEAR(date), MONTH(date), 
                       SUM(visits), SUM(clicks), SUM(phone_calls), COUNT(DISTINCT date)
                FROM analytics
                WHERE page_slug = ? AND language = ? AND YEAR(date) = ? AND MONTH(date) = ?
                GROUP BY page_slug, language, YEAR(date), MONTH(date)
                ON DUPLICATE KEY UPDATE 
                    total_visits = VALUES(total_visits),
                    total_clicks = VALUES(total_clicks),
                    total_phone_calls = VALUES(total_phone_calls),
                    unique_days = VALUES(unique_days)";
                    
    $db->query($sql, [$slug, $lang, $year, $month]);
    echo "INSERT/UPDATE success!\n";

} catch (Exception $e) {
    echo "Detailed Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    
    // Check for weird characters in column name if suspected
    // But describe showed clean output.
}
