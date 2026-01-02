<?php
require_once __DIR__ . '/../config/init.php';

require_once '../core/Database.php';
require_once '../core/Router.php';
require_once '../core/Controller.php';
require_once '../core/helpers.php';

$router = new Router();

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
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

// Click / internal link tracking
$router->post('/track-click', function() {
    require_once BASE_PATH . '/controllers/PageController.php';
    $controller = new PageController();
    $controller->trackClick();
});

$router->post('/track-internal-link', function() {
    require_once BASE_PATH . '/controllers/PageController.php';
    $controller = new PageController();
    $controller->trackInternalLink();
});


/*
|--------------------------------------------------------------------------
| Admin Auth Routes
|--------------------------------------------------------------------------
*/
$router->get('/admin', function() {
    if (!empty($_SESSION['user_id'])) {
        header("Location: /admin/dashboard");
    } else {
        header("Location: /admin/login");
    }
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


/*
|--------------------------------------------------------------------------
| Admin Dashboard
|--------------------------------------------------------------------------
*/
$router->get('/admin/dashboard', function() {
    require_once BASE_PATH . '/controllers/admin/DashboardController.php';
    $controller = new DashboardController();
    $controller->index();
});


/*
|--------------------------------------------------------------------------
| Page Management (Admin)
|--------------------------------------------------------------------------
*/
$router->get('/admin/pages', fn() => requirePageAdmin('index'));
$router->get('/admin/pages/new', fn() => requirePageAdmin('edit'));
$router->get('/admin/pages/edit/{id}', fn($id) => requirePageAdmin('edit', $id));
$router->post('/admin/pages/save', fn() => requirePageAdmin('save'));
$router->post('/admin/pages/delete', fn() => requirePageAdmin('delete'));

/*
|--------------------------------------------------------------------------
| Rotation Management (Admin)
|--------------------------------------------------------------------------
*/
$router->get('/admin/rotations/manage/{pageId}', fn($pageId) => requireRotationAdmin('manage', $pageId));
$router->get('/admin/rotations/new/{pageId}', fn($pageId) => requireRotationAdmin('edit', null, $pageId));
$router->get('/admin/rotations/edit/{id}', fn($id) => requireRotationAdmin('edit', $id));
$router->post('/admin/rotations/save', fn() => requireRotationAdmin('save'));
$router->post('/admin/rotations/delete', fn() => requireRotationAdmin('delete'));
$router->get('/admin/rotations/overview', fn() => requireRotationAdmin('overview'));
$router->post('/admin/rotations/clone', fn() => requireRotationAdmin('clone'));
$router->post('/admin/rotations/bulk-action', fn() => requireRotationAdmin('bulkAction'));
$router->post('/admin/rotations/preview', fn() => requireRotationAdmin('preview'));
$router->post('/admin/rotations/bulk-upload', fn() => requireRotationAdmin('bulkUpload'));
$router->get('/admin/rotations/download-template', fn() => requireRotationAdmin('downloadTemplate'));

/*
|--------------------------------------------------------------------------
| FAQ Management (Admin)
|--------------------------------------------------------------------------
*/
$router->get('/admin/faqs', fn() => requireFAQAdmin('index'));
$router->get('/admin/faqs/new', fn() => requireFAQAdmin('edit'));
$router->get('/admin/faqs/edit/{id}', fn($id) => requireFAQAdmin('edit', $id));
$router->post('/admin/faqs/save', fn() => requireFAQAdmin('save'));
$router->post('/admin/faqs/delete', fn() => requireFAQAdmin('delete'));
$router->post('/admin/faqs/bulk-upload', fn() => requireFAQAdmin('bulkUpload'));
$router->get('/admin/faqs/download-template', fn() => requireFAQAdmin('downloadTemplate'));

/*
|--------------------------------------------------------------------------
| Media Management (Admin)
|--------------------------------------------------------------------------
*/
$router->get('/admin/media', fn() => requireMediaAdmin('index'));
$router->post('/admin/media/upload', fn() => requireMediaAdmin('upload'));
$router->post('/admin/media/delete', fn() => requireMediaAdmin('delete'));
$router->post('/admin/media/bulk-upload', fn() => requireMediaAdmin('bulkUpload'));

/*
|--------------------------------------------------------------------------
| Analytics (Admin)
|--------------------------------------------------------------------------
*/
$router->get('/admin/analytics', fn() => requireAnalytics('index'));
$router->get('/admin/analytics/rotation', fn() => requireAnalytics('rotationAnalytics'));
$router->get('/admin/analytics/crawl', fn() => requireAnalytics('crawlAnalysis'));
$router->get('/admin/analytics/page/{slug}', fn($slug) => requireAnalytics('pageDetail', $slug));
$router->get('/admin/analytics/export', fn() => requireAnalytics('export'));
$router->get('/admin/analytics/navigation', fn() => requireAnalytics('navigationAnalytics'));

/*
|--------------------------------------------------------------------------
| SEO (Admin)
|--------------------------------------------------------------------------
*/
$router->get('/admin/seo', fn() => requireSEO('index'));
$router->post('/admin/seo/save', fn() => requireSEO('save'));


/*
|--------------------------------------------------------------------------
| 404 Route
|--------------------------------------------------------------------------
*/
$router->notFound(function() {
    http_response_code(404);
    echo '<h1>404 - Page Not Found</h1>';
});

$router->dispatch();

/*
|--------------------------------------------------------------------------
| Helper functions for cleaner route callbacks
|--------------------------------------------------------------------------
*/
function requirePageAdmin($method, $id = null) {
    require_once BASE_PATH . '/controllers/admin/PageAdminController.php';
    $controller = new PageAdminController();
    $id !== null ? $controller->$method($id) : $controller->$method();
}

function requireRotationAdmin($method, $id = null, $pageId = null) {
    require_once BASE_PATH . '/controllers/admin/RotationAdminController.php';
    $controller = new RotationAdminController();
    if ($pageId !== null) $controller->$method($pageId);
    else if ($id !== null) $controller->$method($id);
    else $controller->$method();
}

function requireFAQAdmin($method, $id = null) {
    require_once BASE_PATH . '/controllers/admin/FAQAdminController.php';
    $controller = new FAQAdminController();
    $id !== null ? $controller->$method($id) : $controller->$method();
}

function requireMediaAdmin($method) {
    require_once BASE_PATH . '/controllers/admin/MediaController.php';
    $controller = new MediaController();
    $controller->$method();
}

function requireAnalytics($method, $arg = null) {
    require_once BASE_PATH . '/controllers/admin/AnalyticsController.php';
    $controller = new AnalyticsController();
    $arg !== null ? $controller->$method($arg) : $controller->$method();
}

function requireSEO($method) {
    require_once BASE_PATH . '/controllers/admin/SEOController.php';
    $controller = new SEOController();
    $controller->$method();
}
