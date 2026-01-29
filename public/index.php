<?php
ob_start();
require_once __DIR__ . '/../config/init.php';
require_once '../core/Database.php';
require_once '../core/Router.php';
require_once '../core/Controller.php';
require_once '../core/helpers.php';

$router = new Router();

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

// Admin Auth
$router->get('/admin', function() {
    header("Location: " . BASE_URL . (!empty($_SESSION['user_id']) ? "/admin/dashboard" : "/admin/login"));
    exit;
});

$router->get('/admin/login', function() {
    requireAdminController('AuthController', 'showLogin');
});
$router->post('/admin/login', function() {
    requireAdminController('AuthController', 'login');
});
$router->get('/admin/logout', function() {
    requireAdminController('AuthController', 'logout');
});

// Admin Dashboard
$router->get('/admin/dashboard', function() {
    requireAdminController('DashboardController', 'index');
});

// Admin Page Management
$router->group('/admin/pages', function($router) {
    $router->get('/', function() { requirePageAdmin('index'); });
    $router->get('/new', function() { requirePageAdmin('edit'); });
    $router->get('/edit/{id}', function($id) { requirePageAdmin('edit', $id); });
    $router->post('/save', function() { requirePageAdmin('save'); });
    $router->post('/delete', function() { requirePageAdmin('delete'); });
});

// Admin Rotation Management
$router->group('/admin/rotations', function($router) {
    $router->get('/manage/{pageId}', function($pageId) { requireRotationAdmin('manage', null, $pageId); });
    $router->get('/new/{pageId}', function($pageId) { requireRotationAdmin('edit', null, $pageId); });
    $router->get('/edit/{id}', function($id) { requireRotationAdmin('edit', $id); });
    $router->post('/save', function() { requireRotationAdmin('save'); });
    $router->post('/delete', function() { requireRotationAdmin('delete'); });
    $router->get('/overview', function() { requireRotationAdmin('overview'); });
    $router->post('/clone', function() { requireRotationAdmin('clone'); });
    $router->post('/bulk-action', function() { requireRotationAdmin('bulkAction'); });
    $router->post('/preview', function() { requireRotationAdmin('preview'); });
    $router->post('/bulk-upload', function() { requireRotationAdmin('bulkUpload'); });
    $router->get('/download-template', function() { requireRotationAdmin('downloadTemplate'); });
});

// Admin FAQ Management
$router->group('/admin/faqs', function($router) {
    $router->get('/', function() { requireFAQAdmin('index'); });
    $router->get('/new', function() { requireFAQAdmin('edit'); });
    $router->get('/edit/{id}', function($id) { requireFAQAdmin('edit', $id); });
    $router->post('/save', function() { requireFAQAdmin('save'); });
    $router->post('/delete', function() { requireFAQAdmin('delete'); });
    $router->post('/bulk-upload', function() { requireFAQAdmin('bulkUpload'); });
    $router->get('/download-template', function() { requireFAQAdmin('downloadTemplate'); });
});

// Admin Internal Links Management
$router->group('/admin/internal-links', function($router) {
    $router->get('/', function() { requireInternalLinks('index'); });
    $router->get('/manage/{pageId}', function($pageId) { requireInternalLinks('managePage', $pageId); });
    $router->post('/auto-connect', function() { requireInternalLinks('autoConnect'); });
    $router->post('/bulk-action', function() { requireInternalLinks('bulkAction'); });
});

// Admin Link Widget (legacy - kept for backward compatibility)
$router->group('/admin/link-widget', function($router) {
    $router->get('/manage/{pageId}', function($pageId) {
        require_once BASE_PATH . '/controllers/admin/LinkWidgetController.php';
        $c = new LinkWidgetController();
        $c->manage($pageId);
    });

    $router->post('/add', function() {
        require_once BASE_PATH . '/controllers/admin/LinkWidgetController.php';
        $c = new LinkWidgetController();
        $c->addLink();
    });

    $router->post('/remove', function() {
        require_once BASE_PATH . '/controllers/admin/LinkWidgetController.php';
        $c = new LinkWidgetController();
        $c->removeLink();
    });

    $router->post('/reorder', function() {
        require_once BASE_PATH . '/controllers/admin/LinkWidgetController.php';
        $c = new LinkWidgetController();
        $c->reorder();
    });

    $router->post('/toggle', function() {
        require_once BASE_PATH . '/controllers/admin/LinkWidgetController.php';
        $c = new LinkWidgetController();
        $c->toggleWidget();
    });
});

// Admin Media Management
$router->group('/admin/media', function($router) {
    $router->get('/', function() { requireMediaAdmin('index'); });
    $router->post('/upload', function() { requireMediaAdmin('upload'); });
    $router->post('/delete', function() { requireMediaAdmin('delete'); });
    $router->post('/bulk-upload', function() { requireMediaAdmin('bulkUpload'); });
    $router->post('/attach', function() { requireMediaAdmin('attachToPage'); });
    $router->post('/detach', function() { requireMediaAdmin('detachFromPage'); });
    $router->get('/info', function() { requireMediaAdmin('getMediaInfo'); });
    $router->get('/attachment', function() { requireMediaAdmin('getAttachment'); });
    $router->post('/bulk-action', function() { requireMediaAdmin('bulkAction'); });
});

// $router->group('/admin/media', function($router) {
//     $router->get('/', function() { requireMediaAdmin('index'); });
//     $router->post('/upload', function() { requireMediaAdmin('upload'); });
//     $router->post('/delete', function() { requireMediaAdmin('delete'); });
//     $router->post('/bulk-upload', function() { requireMediaAdmin('bulkUpload'); });
    
//     // NEW ROUTES for media-page management
//     $router->post('/attach-to-page', function() { requireMediaAdmin('attachToPage'); });
//     $router->post('/detach-from-page', function() { requireMediaAdmin('detachFromPage'); });
//     $router->get('/get-media-info', function() { requireMediaAdmin('getMediaInfo'); });
//     $router->post('/bulk-action', function() { requireMediaAdmin('bulkAction'); });
//     $router->post('/update-positions', function() { requireMediaAdmin('updatePositions'); });
// });

// Admin Analytics
$router->group('/admin/analytics', function($router) {
    $router->get('/', function() { requireAnalytics('index'); });
    $router->get('/rotation', function() { requireAnalytics('rotationAnalytics'); });
    $router->get('/crawl', function() { requireAnalytics('crawlAnalysis'); });
    $router->get('/page/{slug}', function($slug) { requireAnalytics('pageDetail', $slug); });
    $router->get('/export', function() { requireAnalytics('export'); });
    $router->get('/navigation', function() { requireAnalytics('navigationAnalytics'); });
    $router->get('/getData', function() { requireAnalytics('getData'); });
});

// Admin SEO
$router->group('/admin/seo', function($router) {
    $router->get('/', function() { requireSEO('index'); });
    $router->post('/save', function() { requireSEO('save'); });
    $router->get('/sitemap', function() {
        require_once BASE_PATH . '/controllers/SitemapController.php';
        (new SitemapController())->adminPanel();
    });

});

// Admin Schemas
$router->group('/admin/schemas', function($router) {
    $router->get('/', function() { requireSchemaAdmin('index'); });
    $router->post('/save', function() { requireSchemaAdmin('save'); });
    $router->post('/delete', function() { requireSchemaAdmin('delete'); });
    $router->post('/bulk-import', function() { requireSchemaAdmin('bulkImport'); });
});

// Admin Articles
$router->group('/admin/articles', function($router) {
    $router->get('/', function() { requireArticleAdmin('index'); });
    $router->get('/new', function() { requireArticleAdmin('edit'); });
    $router->get('/edit/{id}', function($id) { requireArticleAdmin('edit', $id); });
    $router->post('/save', function() { requireArticleAdmin('save'); });
    $router->post('/delete', function() { requireArticleAdmin('delete'); });
    $router->post('/toggle-publish', function() { requireArticleAdmin('togglePublish'); });
});

// Backwards-compatible route: handle POSTs accidentally sent to /seo/save (without /admin)
$router->post('/seo/save', function() { requireSEO('save'); });



$router->group('/admin/preview', function($router) {
    $router->get('/{id}', function($id) { requireAdminController('PreviewController', 'show', $id); });
    $router->get('/{id}/content', function($id) { requireAdminController('PreviewController', 'getPreviewContent', $id); });
});

/*
|--------------------------------------------------------------------------
| Public / SEO Routes
|--------------------------------------------------------------------------
*/
$router->get('/sitemap.xml', function() {
    require_once BASE_PATH . '/controllers/SitemapController.php';
    (new SitemapController())->generateSitemapIndex();
});
$router->get('/sitemap-pages.xml', function() {
    require_once BASE_PATH . '/controllers/SitemapController.php';
    (new SitemapController())->generatePagesSitemap();
});
$router->get('/sitemap-articles.xml', function() {
    require_once BASE_PATH . '/controllers/SitemapController.php';
    (new SitemapController())->generateArticlesSitemap();
});
$router->get('/robots.txt', function() {
    require_once BASE_PATH . '/controllers/SitemapController.php';
    (new SitemapController())->generateRobotsTxt();
});

// Click / Internal link tracking
$router->post('/track-click', function() {
    require_once BASE_PATH . '/controllers/PageController.php';
    (new PageController())->trackClick();
});
$router->post('/track-internal-link', function() {
    require_once BASE_PATH . '/controllers/PageController.php';
    (new PageController())->trackInternalLink();
});
$router->post('/track-phone-call', function() {
    require_once BASE_PATH . '/controllers/PageController.php';
    (new PageController())->trackPhoneCall();
});

// Redirect /main to /
$router->get('/main', function() {
    header("Location: " . BASE_URL . "/", true, 301);
    exit;
});

// Root route - show home page
$router->get('/', function() {
    require_once BASE_PATH . '/controllers/PageController.php';
    setLanguage(DEFAULT_LANGUAGE);
    (new PageController())->show('main');
});

// Article routes (must be before catch-all page routes)
$router->get('/articles/{id}/{lang}', function($id, $lang) {
    require_once BASE_PATH . '/controllers/ArticleController.php';
    (new ArticleController())->show($id, $lang);
});

$router->get('/articles/{id}', function($id) {
    require_once BASE_PATH . '/controllers/ArticleController.php';
    setLanguage(DEFAULT_LANGUAGE);
    (new ArticleController())->show($id);
});

// Catch-all public pages (always at the end)
$router->get('/{slug}/{lang}', function($slug, $lang) {
    require_once BASE_PATH . '/controllers/PageController.php';
    (new PageController())->show($slug, $lang);
});

$router->get('/{slug}', function($slug) {
    require_once BASE_PATH . '/controllers/PageController.php';
    setLanguage(DEFAULT_LANGUAGE);
    (new PageController())->show($slug);
});

// 404 handler
$router->notFound(function() { $router->error(404); });

// Dispatch router
$router->dispatch();


/*
|--------------------------------------------------------------------------
| Helper functions for cleaner admin controller calls
|--------------------------------------------------------------------------
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

function requireInternalLinks($method, $arg = null) {
    require_once BASE_PATH . '/controllers/admin/InternalLinksController.php';
    $c = new InternalLinksController();
    $arg !== null ? $c->$method($arg) : $c->$method();
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



function requireSchemaAdmin($method) {
    require_once BASE_PATH . '/controllers/admin/SchemaController.php';
    (new SchemaController())->$method();
}

function requireArticleAdmin($method, $id = null) {
    require_once BASE_PATH . '/controllers/admin/ArticleAdminController.php';
    $c = new ArticleAdminController();
    $id !== null ? $c->$method($id) : $c->$method();
}

ob_end_flush();
