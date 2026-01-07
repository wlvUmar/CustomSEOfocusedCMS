<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/models/SearchEngineNotifier.php';

try {
    $notifier = new SearchEngineNotifier();
    $stats = $notifier->getStatistics();
    echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo "EXCEPTION:\n" . $e->getMessage() . "\n" . $e->getTraceAsString();
}
