<?php
// path: ./core/ControllerFactory.php
// Centralized controller instantiation for route closures.
// Replaces the require*() helpers formerly defined in public/index.php.

class ControllerFactory {
    private static function make(string $file, string $class): object {
        if (!class_exists($class)) {
            require_once BASE_PATH . '/' . ltrim($file, '/');
        }
        return new $class();
    }

    public static function admin(string $controller, string $method, mixed $arg = null): void {
        $c = self::make("controllers/admin/{$controller}.php", $controller);
        $arg !== null ? $c->$method($arg) : $c->$method();
    }

    public static function pageAdmin(string $method, mixed $id = null): void {
        $c = self::make('controllers/admin/PageAdminController.php', 'PageAdminController');
        $id !== null ? $c->$method($id) : $c->$method();
    }

    public static function rotation(string $action, mixed $id = null, mixed $pageId = null): void {
        $c = self::make('controllers/admin/RotationAdminController.php', 'RotationAdminController');
        if ($pageId !== null) $c->$action($pageId);
        elseif ($id !== null) $c->$action($id);
        else $c->$action();
    }

    public static function faq(string $method, mixed $id = null): void {
        $c = self::make('controllers/admin/FAQAdminController.php', 'FAQAdminController');
        $id !== null ? $c->$method($id) : $c->$method();
    }

    public static function internalLinks(string $method, mixed $arg = null): void {
        $c = self::make('controllers/admin/InternalLinksController.php', 'InternalLinksController');
        $arg !== null ? $c->$method($arg) : $c->$method();
    }

    public static function linkWidget(string $method, mixed $arg = null): void {
        $c = self::make('controllers/admin/LinkWidgetController.php', 'LinkWidgetController');
        $arg !== null ? $c->$method($arg) : $c->$method();
    }

    public static function media(string $method): void {
        self::make('controllers/admin/MediaController.php', 'MediaController')->$method();
    }

    public static function analytics(string $method, mixed $arg = null): void {
        $c = self::make('controllers/admin/AnalyticsController.php', 'AnalyticsController');
        $arg !== null ? $c->$method($arg) : $c->$method();
    }

    public static function seo(string $method): void {
        self::make('controllers/admin/SEOController.php', 'SEOController')->$method();
    }

    public static function components(string $method, mixed $arg = null): void {
        $c = self::make('controllers/admin/ComponentsController.php', 'ComponentsController');
        $arg !== null ? $c->$method($arg) : $c->$method();
    }

    public static function schema(string $method): void {
        self::make('controllers/admin/SchemaController.php', 'SchemaController')->$method();
    }

    public static function articleAdmin(string $method, mixed $id = null): void {
        $c = self::make('controllers/admin/ArticleAdminController.php', 'ArticleAdminController');
        $id !== null ? $c->$method($id) : $c->$method();
    }

    public static function sitemap(string $method): void {
        self::make('controllers/SitemapController.php', 'SitemapController')->$method();
    }

    public static function track(string $method): void {
        self::make('controllers/PageController.php', 'PageController')->$method();
    }

    public static function publicPage(string $slug, ?string $lang = null): void {
        if ($lang === null) {
            setLanguage(DEFAULT_LANGUAGE);
            self::make('controllers/PageController.php', 'PageController')->show($slug);
        } else {
            self::make('controllers/PageController.php', 'PageController')->show($slug, $lang);
        }
    }

    public static function article(string $id, ?string $lang = null): void {
        if ($lang === null) {
            setLanguage(DEFAULT_LANGUAGE);
            self::make('controllers/ArticleController.php', 'ArticleController')->show($id);
        } else {
            self::make('controllers/ArticleController.php', 'ArticleController')->show($id, $lang);
        }
    }

    public static function bot(string $method, mixed $arg = null): void {
        $c = self::make('controllers/BotController.php', 'BotController');
        $arg !== null ? $c->$method($arg) : $c->$method();
    }

    public static function seoApi(string $method, mixed $arg = null): void {
        $c = self::make('controllers/Api/SeoApiController.php', 'SeoApiController');
        $arg !== null ? $c->$method($arg) : $c->$method();
    }
}
