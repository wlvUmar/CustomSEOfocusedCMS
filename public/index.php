<?php
error_reporting(E_ALL);

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) mkdir($logDir, 0775, true);
$customLogFile = $logDir . '/php_errors.log';

/* ---------------- Error Handling ---------------- */

set_error_handler(function ($severity, $message, $file, $line) use ($customLogFile) {
    file_put_contents(
        $customLogFile,
        "[".date('Y-m-d H:i:s')."] [Error][$severity] $message in $file on line $line\n",
        FILE_APPEND
    );
});

set_exception_handler(function ($e) use ($customLogFile) {
    file_put_contents(
        $customLogFile,
        "[".date('Y-m-d H:i:s')."] [Exception] {$e->getMessage()} in {$e->getFile()} on line {$e->getLine()}\n",
        FILE_APPEND
    );
    http_response_code(500);
    echo "Internal Server Error";
});

/* ---------------- Bootstrap ---------------- */

require_once '../config/database.php';
require_once '../config/config.php';
require_once '../core/Database.php';
require_once '../core/Router.php';
require_once '../core/Controller.php';
require_once '../core/helpers.php';

$router = new Router();

/* ---------------- Public ---------------- */

$router->get('/', function () {
    require BASE_PATH.'/controllers/PageController.php';
    (new PageController())->show('home');
});

$router->get('/{slug}', function ($slug) {
    require BASE_PATH.'/controllers/PageController.php';
    setLanguage(DEFAULT_LANGUAGE);
    (new PageController())->show($slug);
});

$router->get('/{slug}/{lang}', function ($slug, $lang) {
    require BASE_PATH.'/controllers/PageController.php';
    (new PageController())->show($slug, $lang);
});

$router->post('/track-click', function () {
    require BASE_PATH.'/controllers/PageController.php';
    (new PageController())->trackClick();
});

$router->post('/track-internal-link', function () {
    require BASE_PATH.'/controllers/PageController.php';
    (new PageController())->trackInternalLink();
});

/* ---------------- Admin Auth ---------------- */

$router->get('/admin', function () {
    header('Location: '.(!empty($_SESSION['user_id']) ? '/admin/dashboard' : '/admin/login'));
    exit;
});

$router->get('/admin/login', function () {
    require BASE_PATH.'/controllers/admin/AuthController.php';
    (new AuthController())->showLogin();
});

$router->post('/admin/login', function () {
    require BASE_PATH.'/controllers/admin/AuthController.php';
    (new AuthController())->login();
});

$router->get('/admin/logout', function () {
    require BASE_PATH.'/controllers/admin/AuthController.php';
    (new AuthController())->logout();
});

/* ---------------- Admin Core ---------------- */

$router->get('/admin/dashboard', function () {
    require BASE_PATH.'/controllers/admin/DashboardController.php';
    (new DashboardController())->index();
});

/* ---------------- Pages ---------------- */

$router->get('/admin/pages', function () {
    require BASE_PATH.'/controllers/admin/PageAdminController.php';
    (new PageAdminController())->index();
});

$router->get('/admin/pages/new', function () {
    require BASE_PATH.'/controllers/admin/PageAdminController.php';
    (new PageAdminController())->edit();
});

$router->get('/admin/pages/edit/{id}', function ($id) {
    require BASE_PATH.'/controllers/admin/PageAdminController.php';
    (new PageAdminController())->edit($id);
});

$router->post('/admin/pages/save', function () {
    require BASE_PATH.'/controllers/admin/PageAdminController.php';
    (new PageAdminController())->save();
});

$router->post('/admin/pages/delete', function () {
    require BASE_PATH.'/controllers/admin/PageAdminController.php';
    (new PageAdminController())->delete();
});

/* ---------------- Rotations (Full pages) ---------------- */

$router->get('/admin/rotations/new/{pageId}', function ($pageId) {
    require BASE_PATH.'/controllers/admin/RotationAdminController.php';
    (new RotationAdminController())->edit(null, $pageId);
});

$router->get('/admin/rotations/edit/{id}', function ($id) {
    require BASE_PATH.'/controllers/admin/RotationAdminController.php';
    (new RotationAdminController())->edit($id);
});

$router->post('/admin/rotations/save', function () {
    require BASE_PATH.'/controllers/admin/RotationAdminController.php';
    (new RotationAdminController())->save();
});

$router->post('/admin/rotations/delete', function () {
    require BASE_PATH.'/controllers/admin/RotationAdminController.php';
    (new RotationAdminController())->delete();
});

$router->post('/admin/rotations/clone', function () {
    require BASE_PATH.'/controllers/admin/RotationAdminController.php';
    (new RotationAdminController())->clone();
});

$router->post('/admin/rotations/bulk-action', function () {
    require BASE_PATH.'/controllers/admin/RotationAdminController.php';
    (new RotationAdminController())->bulkAction();
});

/* ---------------- FAQs ---------------- */

$router->get('/admin/faqs', function () {
    require BASE_PATH.'/controllers/admin/FAQAdminController.php';
    (new FAQAdminController())->index();
});

$router->get('/admin/faqs/new', function () {
    require BASE_PATH.'/controllers/admin/FAQAdminController.php';
    (new FAQAdminController())->edit();
});

$router->get('/admin/faqs/edit/{id}', function ($id) {
    require BASE_PATH.'/controllers/admin/FAQAdminController.php';
    (new FAQAdminController())->edit($id);
});

$router->post('/admin/faqs/save', function () {
    require BASE_PATH.'/controllers/admin/FAQAdminController.php';
    (new FAQAdminController())->save();
});

$router->post('/admin/faqs/delete', function () {
    require BASE_PATH.'/controllers/admin/FAQAdminController.php';
    (new FAQAdminController())->delete();
});

/* ---------------- SEO ---------------- */

$router->get('/admin/seo', function () {
    require BASE_PATH.'/controllers/admin/SEOController.php';
    (new SEOController())->index();
});

$router->post('/admin/seo/save', function () {
    require BASE_PATH.'/controllers/admin/SEOController.php';
    (new SEOController())->save();
});

/* ---------------- Media ---------------- */

$router->get('/admin/media', function () {
    require BASE_PATH.'/controllers/admin/MediaController.php';
    (new MediaController())->index();
});

$router->post('/admin/media/upload', function () {
    require BASE_PATH.'/controllers/admin/MediaController.php';
    (new MediaController())->upload();
});

$router->post('/admin/media/delete', function () {
    require BASE_PATH.'/controllers/admin/MediaController.php';
    (new MediaController())->delete();
});

/* ---------------- Analytics Section (AJAX-based) ---------------- */

$router->get('/admin/analytics-section', function () {
    require BASE_PATH.'/controllers/admin/AnalyticsSectionController.php';
    (new AnalyticsSectionController())->index();
});

$router->get('/admin/analytics-section/overview-content', function () {
    require BASE_PATH.'/controllers/admin/AnalyticsSectionController.php';
    (new AnalyticsSectionController())->overviewContent();
});

$router->get('/admin/analytics-section/rotation-content', function () {
    require BASE_PATH.'/controllers/admin/AnalyticsSectionController.php';
    (new AnalyticsSectionController())->rotationContent();
});

$router->get('/admin/analytics-section/navigation-content', function () {
    require BASE_PATH.'/controllers/admin/AnalyticsSectionController.php';
    (new AnalyticsSectionController())->navigationContent();
});

$router->get('/admin/analytics-section/crawl-content', function () {
    require BASE_PATH.'/controllers/admin/AnalyticsSectionController.php';
    (new AnalyticsSectionController())->crawlContent();
});

$router->get('/admin/analytics/export', function () {
    require BASE_PATH.'/controllers/admin/AnalyticsController.php';
    (new AnalyticsController())->export();
});

$router->get('/admin/analytics/page/{slug}', function ($slug) {
    require BASE_PATH.'/controllers/admin/AnalyticsController.php';
    (new AnalyticsController())->pageDetail($slug);
});

/* ---------------- Rotations Section (AJAX-based) ---------------- */

$router->get('/admin/rotations-section', function () {
    require BASE_PATH.'/controllers/admin/RotationSectionController.php';
    (new RotationSectionController())->index();
});

$router->get('/admin/rotations-section/overview-content', function () {
    require BASE_PATH.'/controllers/admin/RotationSectionController.php';
    (new RotationSectionController())->overviewContent();
});

$router->get('/admin/rotations-section/manage-content/{pageId}', function ($pageId) {
    require BASE_PATH.'/controllers/admin/RotationSectionController.php';
    (new RotationSectionController())->manageContent($pageId);
});

/* ---------------- 404 ---------------- */

$router->notFound(function () {
    http_response_code(404);
    echo '<h1>404 - Page Not Found</h1>';
});

$router->dispatch();
