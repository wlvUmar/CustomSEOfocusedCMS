<?php
// path: ./config/routes/public.php
// Public / SEO routes. Expects $router (Router) in scope; loaded by public/index.php.
// Catch-all slug routes stay at the end of this file.
/** @var Router $router */

$router->get('/sitemap.xml', function() {
    ControllerFactory::sitemap('generateSitemapIndex');
});
$router->get('/sitemap-pages.xml', function() {
    ControllerFactory::sitemap('generatePagesSitemap');
});
$router->get('/sitemap-articles.xml', function() {
    ControllerFactory::sitemap('generateArticlesSitemap');
});
$router->get('/ads.txt', function() {
    ControllerFactory::sitemap('generateAdsTxt');
});

$router->get('/robots.txt', function() {
    ControllerFactory::sitemap('generateRobotsTxt');
});

// Click / Internal link tracking
$router->post('/track-click', function() {
    ControllerFactory::track('trackClick');
});
$router->post('/track-internal-link', function() {
    ControllerFactory::track('trackInternalLink');
});
$router->post('/track-phone-call', function() {
    ControllerFactory::track('trackPhoneCall');
});

// Redirect /main to /
$router->get('/main', function() {
    header("Location: " . BASE_URL . "/", true, 301);
    exit;
});
// Redirect /main/uz to /uz (canonical language homepage).
$router->get('/main/uz', function() {
    header("Location: " . BASE_URL . "/uz", true, 301);
    exit;
});

// Canonical Uzbek homepage route.
$router->get('/uz', function() {
    ControllerFactory::publicPage('main', 'uz');
});

// Root route - show home page
$router->get('/', function() {
    ControllerFactory::publicPage('main');
});

// Article routes (must be before catch-all page routes)
$router->get('/articles/{id}/{lang}', function($id, $lang) {
    ControllerFactory::article($id, $lang);
});

$router->get('/articles/{id}', function($id) {
    ControllerFactory::article($id);
});

// Catch-all public pages (always at the end)
$router->get('/{slug}/{lang}', function($slug, $lang) {
    ControllerFactory::publicPage($slug, $lang);
});

$router->get('/{slug}', function($slug) {
    ControllerFactory::publicPage($slug);
});
