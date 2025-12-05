<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../core/Database.php';
require_once '../core/Router.php';
require_once '../core/Controller.php';
require_once '../core/helpers.php';
session_start();

$router = new Router();

// Public routes
$router->get('/', function() {
    require_once BASE_PATH . '/controllers/PageController.php';
    $controller = new PageController();
    $controller->show('home');
});

$router->get('/{slug}', function($slug) {
    require_once BASE_PATH . '/controllers/PageController.php';
    $controller = new PageController();
    $controller->show($slug);
});

$router->get('/{slug}/{lang}', function($slug, $lang) {
    require_once BASE_PATH . '/controllers/PageController.php';
    $controller = new PageController();
    $controller->show($slug, $lang);
});

// Admin routes
//  
//  

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

