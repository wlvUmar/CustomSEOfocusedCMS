<?php
// path: ./core/Autoloader.php
// Minimal PSR-4 style autoloader (02-architecture #6) — keeps string require fallback for BC.

class Autoloader {
    private static array $map = [
        'PageSectionsHelper' => 'models/ai/tools/PageSectionsHelper.php',
        'PromptDoctrine'     => 'models/ai/PromptDoctrine.php',
        // Add more as god object splits: 'PageReadTools' => '...', etc.
    ];

    public static function register(): void {
        spl_autoload_register([self::class, 'load'], true, true);
    }

    public static function load(string $class): bool {
        // Simple map lookup
        if (isset(self::$map[$class])) {
            $path = BASE_PATH . '/' . self::$map[$class];
            if (is_file($path)) { require_once $path; return true; }
        }
        // Fallback: try models/<Class>.php and core/<Class>.php
        $candidates = [
            BASE_PATH . '/models/' . $class . '.php',
            BASE_PATH . '/core/' . $class . '.php',
            BASE_PATH . '/models/ai/tools/' . $class . '.php',
            BASE_PATH . '/models/ai/' . $class . '.php',
        ];
        foreach ($candidates as $p) if (is_file($p)) { require_once $p; return true; }
        return false;
    }
}
