<?php
// path: ./config/routes/api.php
// API routes (Telegram Bot Integration). Expects $router (Router) in scope; loaded by public/index.php.
/** @var Router $router */

$router->post('/api/bot/requests', function() {
    ControllerFactory::bot('createRequest');
});

$router->post('/api/bot/users', function() {
    ControllerFactory::bot('upsertUser');
});

$router->get('/api/bot/users/{telegram_id}', function($telegram_id) {
    ControllerFactory::bot('getUser', $telegram_id);
});

$router->post('/api/bot/users/{telegram_id}/phone', function($telegram_id) {
    ControllerFactory::bot('savePhone', $telegram_id);
});

$router->get('/api/bot/users/{telegram_id}/phone', function($telegram_id) {
    ControllerFactory::bot('getPhone', $telegram_id);
});

$router->post('/api/bot/mappings', function() {
    ControllerFactory::bot('createMapping');
});

$router->get('/api/bot/mappings', function() {
    ControllerFactory::bot('listMappings');
});

$router->get('/api/bot/mappings/{request_id}', function($request_id) {
    ControllerFactory::bot('getMapping', $request_id);
});

$router->post('/api/bot/mappings/{request_id}/notified', function($request_id) {
    ControllerFactory::bot('markMappingNotified', $request_id);
});

$router->post('/api/bot/mappings/{request_id}/claim', function($request_id) {
    ControllerFactory::bot('claimMappingNotification', $request_id);
});

$router->post('/api/bot/mappings/{request_id}/release', function($request_id) {
    ControllerFactory::bot('releaseMappingNotification', $request_id);
});

$router->post('/api/bot/access-token', function() {
    ControllerFactory::bot('createAccessToken');
});

$router->get('/api/bot/requests/{id}', function($id) {
    ControllerFactory::bot('getRequest', $id);
});

$router->get('/api/health', function() {
    echo "OK";
});

// Read-only SEO operator API (Bearer tokens, seo_api_tokens). Spec: docs/seo-operator-setup.md §1.
$router->get('/api/seo/v1/pages', function() {
    ControllerFactory::seoApi('pages');
});

$router->get('/api/seo/v1/page/{slug}', function($slug) {
    ControllerFactory::seoApi('page', $slug);
});

$router->get('/api/seo/v1/stats/top-pages', function() {
    ControllerFactory::seoApi('topPages');
});

$router->get('/api/seo/v1/gsc/summary', function() {
    ControllerFactory::seoApi('gscSummary');
});
