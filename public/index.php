<?php
ob_start();
require_once __DIR__ . '/../config/init.php';
require_once BASE_PATH . '/core/Database.php';
require_once BASE_PATH . '/core/Router.php';
require_once BASE_PATH . '/core/Controller.php';
require_once BASE_PATH . '/core/helpers.php';
require_once BASE_PATH . '/core/Url.php';
require_once BASE_PATH . '/models/analytics/RotationContent.php';
require_once BASE_PATH . '/models/analytics/MediaPlaceholderStore.php';
require_once BASE_PATH . '/services/TrackingService.php';
require_once BASE_PATH . '/services/ImageVariants.php';
require_once BASE_PATH . '/services/MediaRenderer.php';
require_once BASE_PATH . '/services/SeoEnhancer.php';
require_once BASE_PATH . '/core/ControllerFactory.php';

$router = new Router();

// api before public so the catch-all slug routes (end of public.php) register last.
require BASE_PATH . '/config/routes/admin.php';
require BASE_PATH . '/config/routes/api.php';
require BASE_PATH . '/config/routes/public.php';

// 404 handler
$router->notFound(function() use ($router) { $router->error(404); });

// Dispatch router
$router->dispatch();

if (ob_get_level() > 0) {
    ob_end_flush();
}
