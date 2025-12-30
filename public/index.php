<?php
error_reporting(E_ALL);

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) mkdir($logDir, 0775, true); // create folder if missing
$logFile = $logDir . '/php_errors.log';

set_error_handler(function ($severity, $message, $file, $line) use ($logFile) {
    $log = "[".date('Y-m-d H:i:s')."] [Error][$severity] $message in $file on line $line\n";
    file_put_contents($logFile, $log, FILE_APPEND);
});

set_exception_handler(function ($e) use ($logFile) {
    $log = "[".date('Y-m-d H:i:s')."] [Exception] ".$e->getMessage().
           " in ".$e->getFile()." on line ".$e->getLine()."\n";
    file_put_contents($logFile, $log, FILE_APPEND);
});

register_shutdown_function(function () use ($logFile) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $log = "[".date('Y-m-d H:i:s')."] [Fatal] {$error['message']} in {$error['file']} on line {$error['line']}\n";
        file_put_contents($logFile, $log, FILE_APPEND);
    }
});
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../core/Database.php';
require_once '../core/Router.php';
require_once '../core/Controller.php';
require_once '../core/helpers.php';

$router = new Router();

// Public routes
$router->get('/', function() {
    require_once BASE_PATH . '/controllers/PageController.php';
    $controller = new PageController();
    $controller->show('home');
});

$router->get('/{slug}', function($slug) {
    require_once BASE_PATH . '/controllers/PageController.php';
    setLanguage(DEFAULT_LANGUAGE); 
    $controller = new PageController();
    $controller->show($slug);
});

$router->get('/{slug}/{lang}', function($slug, $lang) {
    require_once BASE_PATH . '/controllers/PageController.php';
    $controller = new PageController();
    $controller->show($slug, $lang);
});

// Click tracking
$router->post('/track-click', function() {
    require_once BASE_PATH . '/controllers/PageController.php';
    $controller = new PageController();
    $controller->trackClick();
});

// Admin routes
$router->get('/admin', function() {
    if (!empty($_SESSION['user_id'])) {
        header("Location: /admin/dashboard");
        exit;
    }
    header("Location: /admin/login");
    exit;
});

$router->get('/admin/login', function() {
    require_once BASE_PATH . '/controllers/admin/AuthController.php';
    $controller = new AuthController();
    $controller->showLogin();
});

$router->post('/admin/login', function() {
    require_once BASE_PATH . '/controllers/admin/AuthController.php';
    $controller = new AuthController();
    $controller->login();
});

$router->get('/admin/logout', function() {
    require_once BASE_PATH . '/controllers/admin/AuthController.php';
    $controller = new AuthController();
    $controller->logout();
});

$router->get('/admin/dashboard', function() {
    require_once BASE_PATH . '/controllers/admin/DashboardController.php';
    $controller = new DashboardController();
    $controller->index();
});

// Pages
$router->get('/admin/pages', function() {
    require_once BASE_PATH . '/controllers/admin/PageAdminController.php';
    $controller = new PageAdminController();
    $controller->index();
});

$router->get('/admin/pages/new', function() {
    require_once BASE_PATH . '/controllers/admin/PageAdminController.php';
    $controller = new PageAdminController();
    $controller->edit();
});

$router->get('/admin/pages/edit/{id}', function($id) {
    require_once BASE_PATH . '/controllers/admin/PageAdminController.php';
    $controller = new PageAdminController();
    $controller->edit($id);
});

$router->post('/admin/pages/save', function() {
    require_once BASE_PATH . '/controllers/admin/PageAdminController.php';
    $controller = new PageAdminController();
    $controller->save();
});

$router->post('/admin/pages/delete', function() {
    require_once BASE_PATH . '/controllers/admin/PageAdminController.php';
    $controller = new PageAdminController();
    $controller->delete();
});

// Content Rotations
$router->get('/admin/rotations/manage/{pageId}', function($pageId) {
    require_once BASE_PATH . '/controllers/admin/RotationAdminController.php';
    $controller = new RotationAdminController();
    $controller->manage($pageId);
});

$router->get('/admin/rotations/new/{pageId}', function($pageId) {
    require_once BASE_PATH . '/controllers/admin/RotationAdminController.php';
    $controller = new RotationAdminController();
    $controller->edit(null, $pageId);
});

$router->get('/admin/rotations/edit/{id}', function($id) {
    require_once BASE_PATH . '/controllers/admin/RotationAdminController.php';
    $controller = new RotationAdminController();
    $controller->edit($id);
});

$router->post('/admin/rotations/save', function() {
    require_once BASE_PATH . '/controllers/admin/RotationAdminController.php';
    $controller = new RotationAdminController();
    $controller->save();
});

$router->post('/admin/rotations/delete', function() {
    require_once BASE_PATH . '/controllers/admin/RotationAdminController.php';
    $controller = new RotationAdminController();
    $controller->delete();
});

// FAQs
$router->get('/admin/faqs', function() {
    require_once BASE_PATH . '/controllers/admin/FAQAdminController.php';
    $controller = new FAQAdminController();
    $controller->index();
});

$router->get('/admin/faqs/new', function() {
    require_once BASE_PATH . '/controllers/admin/FAQAdminController.php';
    $controller = new FAQAdminController();
    $controller->edit();
});

$router->get('/admin/faqs/edit/{id}', function($id) {
    require_once BASE_PATH . '/controllers/admin/FAQAdminController.php';
    $controller = new FAQAdminController();
    $controller->edit($id);
});

$router->post('/admin/faqs/save', function() {
    require_once BASE_PATH . '/controllers/admin/FAQAdminController.php';
    $controller = new FAQAdminController();
    $controller->save();
});

$router->post('/admin/faqs/delete', function() {
    require_once BASE_PATH . '/controllers/admin/FAQAdminController.php';
    $controller = new FAQAdminController();
    $controller->delete();
});

// Analytics
$router->get('/admin/analytics', function() {
    require_once BASE_PATH . '/controllers/admin/AnalyticsController.php';
    $controller = new AnalyticsController();
    $controller->index();
});

// SEO
$router->get('/admin/seo', function() {
    require_once BASE_PATH . '/controllers/admin/SEOController.php';
    $controller = new SEOController();
    $controller->index();
});

$router->post('/admin/seo/save', function() {
    require_once BASE_PATH . '/controllers/admin/SEOController.php';
    $controller = new SEOController();
    $controller->save();
});

// Media
$router->get('/admin/media', function() {
    require_once BASE_PATH . '/controllers/admin/MediaController.php';
    $controller = new MediaController();
    $controller->index();
});

$router->post('/admin/media/upload', function() {
    require_once BASE_PATH . '/controllers/admin/MediaController.php';
    $controller = new MediaController();
    $controller->upload();
});

$router->post('/admin/media/delete', function() {
    require_once BASE_PATH . '/controllers/admin/MediaController.php';
    $controller = new MediaController();
    $controller->delete();
});

$router->notFound(function() {
    http_response_code(404);
    echo '<h1>404 - Page Not Found</h1>';
});

$router->dispatch();