<?php
require_once __DIR__ . '/config.php';    
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';  

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/admin/login?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();
