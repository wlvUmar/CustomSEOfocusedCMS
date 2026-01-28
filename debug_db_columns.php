<?php
// Run from project root
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/core/Database.php';

echo "Debugging Database Columns...\n\n";

try {
    $db = Database::getInstance();
    
    echo "--- Table: analytics ---\n";
    $columns = $db->fetchAll("DESCRIBE analytics");
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    echo "\n--- Table: analytics_monthly ---\n";
    $columns = $db->fetchAll("DESCRIBE analytics_monthly");
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
