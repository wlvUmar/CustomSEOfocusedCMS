<?php
// path: ./config/routes/admin.php
// Admin routes. Expects $router (Router) in scope; loaded by public/index.php.
/** @var Router $router */

// Admin Auth
$router->get('/admin', function() {
    header("Location: " . BASE_URL . (!empty($_SESSION['user_id']) ? "/admin/dashboard" : "/admin/login"));
    exit;
});

$router->get('/admin/login', function() {
    ControllerFactory::admin('AuthController', 'showLogin');
});
$router->post('/admin/login', function() {
    ControllerFactory::admin('AuthController', 'login');
});
$router->get('/admin/logout', function() {
    ControllerFactory::admin('AuthController', 'logout');
});

// Admin Dashboard
$router->get('/admin/dashboard', function() {
    ControllerFactory::admin('DashboardController', 'index');
});

// Admin Page Management
$router->group('/admin/pages', function($router) {
    $router->get('/', function() { ControllerFactory::pageAdmin('index'); });
    $router->get('/new', function() { ControllerFactory::pageAdmin('edit'); });
    $router->get('/edit/{id}', function($id) { ControllerFactory::pageAdmin('edit', $id); });
    $router->post('/save', function() { ControllerFactory::pageAdmin('save'); });
    $router->post('/delete', function() { ControllerFactory::pageAdmin('delete'); });
    $router->post('/ai-edit', function() { ControllerFactory::pageAdmin('aiEdit'); });
    $router->post('/ai-chat', function() { ControllerFactory::pageAdmin('aiChat'); });
    $router->get('/revisions/{id}', function($id) { ControllerFactory::pageAdmin('revisions', $id); });
    $router->post('/restore-revision', function() { ControllerFactory::pageAdmin('restoreRevision'); });
});

// Admin Rotation Management
$router->group('/admin/rotations', function($router) {
    $router->get('/manage/{pageId}', function($pageId) { ControllerFactory::rotation('manage', pageId: $pageId); });
    $router->get('/new/{pageId}', function($pageId) { ControllerFactory::rotation('edit', pageId: $pageId); });
    $router->get('/edit/{id}', function($id) { ControllerFactory::rotation('edit', id: $id); });
    $router->post('/save', function() { ControllerFactory::rotation('save'); });
    $router->post('/delete', function() { ControllerFactory::rotation('delete'); });
    $router->get('/overview', function() { ControllerFactory::rotation('overview'); });
    $router->post('/clone', function() { ControllerFactory::rotation('clone'); });
    $router->post('/bulk-action', function() { ControllerFactory::rotation('bulkAction'); });
    $router->post('/preview', function() { ControllerFactory::rotation('preview'); });
    $router->post('/bulk-upload', function() { ControllerFactory::rotation('bulkUpload'); });
    $router->get('/download-template', function() { ControllerFactory::rotation('downloadTemplate'); });
});

// Admin FAQ Management
$router->group('/admin/faqs', function($router) {
    $router->get('/', function() { ControllerFactory::faq('index'); });
    $router->get('/new', function() { ControllerFactory::faq('edit'); });
    $router->get('/edit/{id}', function($id) { ControllerFactory::faq('edit', $id); });
    $router->post('/save', function() { ControllerFactory::faq('save'); });
    $router->post('/delete', function() { ControllerFactory::faq('delete'); });
    $router->post('/bulk-upload', function() { ControllerFactory::faq('bulkUpload'); });
    $router->get('/download-template', function() { ControllerFactory::faq('downloadTemplate'); });
});

// Admin Internal Links Management
$router->group('/admin/internal-links', function($router) {
    $router->get('/', function() { ControllerFactory::internalLinks('index'); });
    $router->get('/manage/{pageId}', function($pageId) { ControllerFactory::internalLinks('managePage', $pageId); });
    $router->post('/auto-connect', function() { ControllerFactory::internalLinks('autoConnect'); });
    $router->post('/bulk-action', function() { ControllerFactory::internalLinks('bulkAction'); });
});

// Admin Link Widget (legacy - kept for backward compatibility)
$router->group('/admin/link-widget', function($router) {
    $router->get('/manage/{pageId}', function($pageId) {
        ControllerFactory::linkWidget('manage', $pageId);
    });

    $router->post('/add', function() {
        ControllerFactory::linkWidget('addLink');
    });

    $router->post('/remove', function() {
        ControllerFactory::linkWidget('removeLink');
    });

    $router->post('/reorder', function() {
        ControllerFactory::linkWidget('reorder');
    });

    $router->post('/toggle', function() {
        ControllerFactory::linkWidget('toggleWidget');
    });
});

// Admin Media Management
$router->group('/admin/media', function($router) {
    $router->get('/', function() { ControllerFactory::media('index'); });
    $router->get('/list', function() { ControllerFactory::media('listJson'); });
    $router->post('/upload', function() { ControllerFactory::media('upload'); });
    $router->post('/delete', function() { ControllerFactory::media('delete'); });
    $router->post('/bulk-upload', function() { ControllerFactory::media('bulkUpload'); });
    $router->post('/attach', function() { ControllerFactory::media('attachToPage'); });
    $router->post('/regenerate-variants', function() { ControllerFactory::media('regenerateVariants'); });
    $router->post('/detach', function() { ControllerFactory::media('detachFromPage'); });
    $router->get('/info', function() { ControllerFactory::media('getMediaInfo'); });
    $router->get('/attachment', function() { ControllerFactory::media('getAttachment'); });
    $router->post('/bulk-action', function() { ControllerFactory::media('bulkAction'); });
});

// Admin Product Requests (Telegram integration)
$router->get('/admin/requests', function() { ControllerFactory::admin('RequestAdminController', 'index'); });
$router->get('/admin/requests/{id}', function($id) { ControllerFactory::admin('RequestAdminController', 'show', $id); });
$router->post('/admin/requests/approve', function() { ControllerFactory::admin('RequestAdminController', 'approve'); });
$router->post('/admin/requests/reject', function() { ControllerFactory::admin('RequestAdminController', 'reject'); });
$router->post('/admin/requests/delete', function() { ControllerFactory::admin('RequestAdminController', 'delete'); });

// Admin AI Studio (agent loop over the admin via tools)
$router->get('/admin/ai-studio', function() { ControllerFactory::admin('AiStudioController', 'index'); });
$router->post('/admin/ai-studio/run', function() { ControllerFactory::admin('AiStudioController', 'run'); });
$router->post('/admin/ai-studio/cancel/{id}', function($id) { ControllerFactory::admin('AiStudioController', 'cancel', $id); });
$router->get('/admin/ai-studio/queue/{id}', function($id) { ControllerFactory::admin('AiStudioController', 'queueStatus', $id); });
$router->get('/admin/ai-studio/models', function() { ControllerFactory::admin('AiStudioController', 'models'); });
$router->get('/admin/ai-studio/sessions', function() { ControllerFactory::admin('AiStudioController', 'sessions'); });
$router->get('/admin/ai-studio/session/{id}', function($id) { ControllerFactory::admin('AiStudioController', 'session', $id); });
$router->post('/admin/ai-studio/session/{id}/delete', function($id) { ControllerFactory::admin('AiStudioController', 'deleteSession', $id); });
$router->get('/admin/ai-studio/gsc-status', function() { ControllerFactory::admin('GscAuthController', 'status'); });
$router->get('/admin/ai-studio/gsc-auth', function() { ControllerFactory::admin('GscAuthController', 'authorize'); });
$router->get('/admin/ai-studio/gsc-callback', function() { ControllerFactory::admin('GscAuthController', 'callback'); });
$router->post('/admin/ai-studio/gsc-disconnect', function() { ControllerFactory::admin('GscAuthController', 'disconnect'); });

// Admin Change Log
$router->get('/admin/audit-log', function() { ControllerFactory::admin('AuditLogController', 'index'); });

// Admin Analytics
// SEO Dashboard (Search Console + on-page audit). Not under /admin/seo: that path is SEO Settings.
$router->get('/admin/search-console', function() { ControllerFactory::admin('SeoDashboardController', 'index'); });
$router->get('/admin/search-console/data', function() { ControllerFactory::admin('SeoDashboardController', 'data'); });

$router->group('/admin/analytics', function($router) {
    $router->get('/', function() { ControllerFactory::analytics('index'); });
    $router->get('/rotation', function() { ControllerFactory::analytics('rotationAnalytics'); });
    $router->get('/crawl', function() { ControllerFactory::analytics('crawlAnalysis'); });
    $router->get('/page/{slug}', function($slug) { ControllerFactory::analytics('pageDetail', $slug); });
    $router->get('/export', function() { ControllerFactory::analytics('export'); });
    $router->get('/navigation', function() { ControllerFactory::analytics('navigationAnalytics'); });
    $router->get('/getData', function() { ControllerFactory::analytics('getData'); });
});

// Admin SEO
$router->group('/admin/seo', function($router) {
    $router->get('/', function() { ControllerFactory::seo('index'); });
    $router->post('/save', function() { ControllerFactory::seo('save'); });
    $router->get('/sitemap', function() {
        ControllerFactory::sitemap('adminPanel');
    });
});

// Admin Components (DB-canonical library, file-generated)
$router->group('/admin/components', function($router) {
    $router->get('/', function() { ControllerFactory::components('index'); });
    $router->get('/edit/{slug}', function($slug) { ControllerFactory::components('edit', $slug); });
    $router->get('/revision', function() { ControllerFactory::components('revision'); });
    $router->post('/save', function() { ControllerFactory::components('save'); });
    $router->post('/preview', function() { ControllerFactory::components('preview'); });
    $router->post('/restore', function() { ControllerFactory::components('restore'); });
    $router->post('/rebuild', function() { ControllerFactory::components('rebuild'); });
});

// Admin Schemas
$router->group('/admin/schemas', function($router) {
    $router->get('/', function() { ControllerFactory::schema('index'); });
    $router->post('/save', function() { ControllerFactory::schema('save'); });
    $router->post('/delete', function() { ControllerFactory::schema('delete'); });
    $router->post('/bulk-import', function() { ControllerFactory::schema('bulkImport'); });
});

// Admin Articles
$router->group('/admin/articles', function($router) {
    $router->get('/', function() { ControllerFactory::articleAdmin('index'); });
    $router->get('/new', function() { ControllerFactory::articleAdmin('edit'); });
    $router->get('/edit/{id}', function($id) { ControllerFactory::articleAdmin('edit', $id); });
    $router->post('/save', function() { ControllerFactory::articleAdmin('save'); });
    $router->post('/delete', function() { ControllerFactory::articleAdmin('delete'); });
    $router->post('/toggle-publish', function() { ControllerFactory::articleAdmin('togglePublish'); });
});

// Backwards-compatible route: handle POSTs accidentally sent to /seo/save (without /admin)
$router->post('/seo/save', function() { ControllerFactory::seo('save'); });

$router->group('/admin/preview', function($router) {
    $router->get('/{id}', function($id) { ControllerFactory::admin('PreviewController', 'show', $id); });
    $router->get('/{id}/content', function($id) { ControllerFactory::admin('PreviewController', 'getPreviewContent', $id); });
});
