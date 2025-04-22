<?php

declare(strict_types=1);
if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', dirname(__DIR__));
}

require_once JAXBOARDS_ROOT . '/vendor/autoload.php';

spl_autoload_register(static function ($className): void {
    $classPath = JAXBOARDS_ROOT . match (true) {
        str_starts_with($className, 'ACP\\') => '/' . strtolower(str_replace('\\', '/', $className)) . '.php',
        str_starts_with($className, 'Page\\') => '/inc/page/' . str_replace('Page\\', '', $className) . '.php',
        default => "/inc/classes/{$className}.php",
    };

    if (!file_exists($classPath)) {
        return;
    }

    require_once $classPath;
});
