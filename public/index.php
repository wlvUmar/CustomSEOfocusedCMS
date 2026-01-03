<?php
require_once __DIR__ . '/../config/init.php';
require_once '../core/Database.php';
require_once '../core/Router.php';
require_once '../core/Controller.php';
require_once '../core/helpers.php';

$router = new Router();

/*
|------------------------------------------------------------------
| Public Routes
|------------------------------------------------------------------
*/
$router->get('/', function() {
    require_once BASE_PATH . '/controllers/PageController.php';
    (new PageController())->show('home');
});

$router->get('/{slug}', function($slug) {
    require_once BASE_PATH . '/controllers/PageController.php';
    setLanguage(DEFAULT_LANGUAGE);
    (new PageController())->show($slug);
});

$router->get('/{slug}/{lang}', function($slug, $lang) {
    require_once BASE_PATH . '/controllers/PageController.php';
    (new PageController())->show($slug, $lang);
});

// Click / Internal link tracking
$router->post('/track-click', fn() => (require_once BASE_PATH . '/controllers/PageController.php') && (new PageController())->trackClick());
$router->post('/track-internal-link', fn() => (require_once BASE_PATH . '/controllers/PageController.php') && (new PageController())->trackInternalLink());

/*
|------------------------------------------------------------------
| Admin Auth Routes
|------------------------------------------------------------------
*/
$router->get('/admin', function() {
    header("Location: " . (!empty($_SESSION['user_id']) ? "/admin/dashboard" : "/admin/login"));
    exit;
});

$router->get('/admin/login', fn() => requireAdminController('AuthController', 'showLogin'));
$router->post('/admin/login', fn() => requireAdminController('AuthController', 'login'));
$router->get('/admin/logout', fn() => requireAdminController('AuthController', 'logout'));

/*
|------------------------------------------------------------------
| Admin Dashboard
|------------------------------------------------------------------
*/
$router->get('/admin/dashboard', fn() => requireAdminController('DashboardController', 'index'));

/*
|------------------------------------------------------------------
| Admin Page Management
|------------------------------------------------------------------
*/
$router->group('/admin/pages', function($router) {
    $router->get('', fn() => requirePageAdmin('index'));
    $router->get('/new', fn() => requirePageAdmin('edit'));
    $router->get('/edit/{id}', fn($id) => requirePageAdmin('edit', $id));
    $router->post('/save', fn() => requirePageAdmin('save'));
    $router->post('/delete', fn() => requirePageAdmin('delete'));
});

/*
|------------------------------------------------------------------
| Admin Rotation Management
|------------------------------------------------------------------
*/
$router->group('/admin/rotations', function($router) {
    $router->get('/manage/{pageId}', fn($pageId) => requireRotationAdmin('manage', null, $pageId));
    $router->get('/new/{pageId}', fn($pageId) => requireRotationAdmin('edit', null, $pageId));
    $router->get('/edit/{id}', fn($id) => requireRotationAdmin('edit', $id));
    $router->post('/save', fn() => requireRotationAdmin('save'));
    $router->post('/delete', fn() => requireRotationAdmin('delete'));
    $router->get('/overview', fn() => requireRotationAdmin('overview'));
    $router->post('/clone', fn() => requireRotationAdmin('clone'));
    $router->post('/bulk-action', fn() => requireRotationAdmin('bulkAction'));
    $router->post('/preview', fn() => requireRotationAdmin('preview'));
    $router->post('/bulk-upload', fn() => requireRotationAdmin('bulkUpload'));
    $router->get('/download-template', fn() => requireRotationAdmin('downloadTemplate'));
});

/*
|------------------------------------------------------------------
| Admin FAQ Management
|------------------------------------------------------------------
*/
$router->group('/admin/faqs', function($router) {
    $router->get('', fn() => requireFAQAdmin('index'));
    $router->get('/new', fn() => requireFAQAdmin('edit'));
    $router->get('/edit/{id}', fn($id) => requireFAQAdmin('edit', $id));
    $router->post('/save', fn() => requireFAQAdmin('save'));
    $router->post('/delete', fn() => requireFAQAdmin('delete'));
    $router->post('/bulk-upload', fn() => requireFAQAdmin('bulkUpload'));
    $router->get('/download-template', fn() => requireFAQAdmin('downloadTemplate'));
});

/*
|------------------------------------------------------------------
| Admin Link Widget
|------------------------------------------------------------------
*/
$router->group('/admin/link-widget', function($router) {
    $router->get('/manage/{pageId}', fn($pageId) => require_once BASE_PATH . '/controllers/admin/LinkWidgetController.php' && (new LinkWidgetController())->manage($pageId));
    $router->post('/add', fn() => require_once BASE_PATH . '/controllers/admin/LinkWidgetController.php' && (new LinkWidgetController())->addLink());
    $router->post('/remove', fn() => require_once BASE_PATH . '/controllers/admin/LinkWidgetController.php' && (new LinkWidgetController())->removeLink());
    $router->post('/reorder', fn() => require_once BASE_PATH . '/controllers/admin/LinkWidgetController.php' && (new LinkWidgetController())->reorder());
    $router->post('/toggle', fn() => require_once BASE_PATH . '/controllers/admin/LinkWidgetController.php' && (new LinkWidgetController())->toggleWidget());
});


/*
|------------------------------------------------------------------
| Admin Media Management
|------------------------------------------------------------------
*/
$router->group('/admin/media', function($router) {
    $router->get('', fn() => requireMediaAdmin('index'));
    $router->post('/upload', fn() => requireMediaAdmin('upload'));
    $router->post('/delete', fn() => requireMediaAdmin('delete'));
    $router->post('/bulk-upload', fn() => requireMediaAdmin('bulkUpload'));
});

/*
|------------------------------------------------------------------
| Admin Analytics
|------------------------------------------------------------------
*/
$router->group('/admin/analytics', function($router) {
    $router->get('', fn() => requireAnalytics('index'));
    $router->get('/rotation', fn() => requireAnalytics('rotationAnalytics'));
    $router->get('/crawl', fn() => requireAnalytics('crawlAnalysis'));
    $router->get('/page/{slug}', fn($slug) => requireAnalytics('pageDetail', $slug));
    $router->get('/export', fn() => requireAnalytics('export'));
    $router->get('/navigation', fn() => requireAnalytics('navigationAnalytics'));
});

/*
|------------------------------------------------------------------
| Admin SEO
|------------------------------------------------------------------
*/
$router->group('/admin/seo', function($router) {
    $router->get('', fn() => requireSEO('index'));
    $router->post('/save', fn() => requireSEO('save'));
    $router->get('/sitemap', fn() => require_once BASE_PATH . '/controllers/SitemapController.php' && (new SitemapController())->adminPanel());
    $router->post('/sitemap/ping', fn() => require_once BASE_PATH . '/controllers/SitemapController.php' && (new SitemapController())->pingSearchEngines());
});

/*
|------------------------------------------------------------------
| Admin Preview
|------------------------------------------------------------------
*/
$router->group('/admin/preview', function($router) {
    $router->get('/{id}', fn($id) => requireAdminController('PreviewController', 'show', $id));
    $router->get('/{id}/content', fn($id) => requireAdminController('PreviewController', 'getPreviewContent', $id));
});

/*
|------------------------------------------------------------------
| Sitemap & SEO Public
|------------------------------------------------------------------
*/
$router->get('/sitemap.xml', fn() => require_once BASE_PATH . '/controllers/SitemapController.php' && (new SitemapController())->generateXML());
$router->get('/robots.txt', fn() => require_once BASE_PATH . '/controllers/SitemapController.php' && (new SitemapController())->generateRobotsTxt());


$router->notFound(fn() => $router->error(404));

$router->dispatch();

/*
|------------------------------------------------------------------
| Helper functions for cleaner admin controller calls
|------------------------------------------------------------------
*/
function requireAdminController($controller, $method, $arg = null) {
    require_once BASE_PATH . "/controllers/admin/{$controller}.php";
    $c = new $controller();
    $arg !== null ? $c->$method($arg) : $c->$method();
}

function requirePageAdmin($method, $id = null) {
    require_once BASE_PATH . '/controllers/admin/PageAdminController.php';
    $c = new PageAdminController();
    $id !== null ? $c->$method($id) : $c->$method();
}

function requireRotationAdmin($method, $id = null, $pageId = null) {
    require_once BASE_PATH . '/controllers/admin/RotationAdminController.php';
    $c = new RotationAdminController();
    if ($pageId !== null) $c->$method($pageId);
    elseif ($id !== null) $c->$method($id);
    else $c->$method();
}

function requireFAQAdmin($method, $id = null) {
    require_once BASE_PATH . '/controllers/admin/FAQAdminController.php';
    $c = new FAQAdminController();
    $id !== null ? $c->$method($id) : $c->$method();
}

function requireMediaAdmin($method) {
    require_once BASE_PATH . '/controllers/admin/MediaController.php';
    (new MediaController())->$method();
}

function requireAnalytics($method, $arg = null) {
    require_once BASE_PATH . '/controllers/admin/AnalyticsController.php';
    $c = new AnalyticsController();
    $arg !== null ? $c->$method($arg) : $c->$method();
}

function requireSEO($method) {
    require_once BASE_PATH . '/controllers/admin/SEOController.php';
    (new SEOController())->$method();
}


