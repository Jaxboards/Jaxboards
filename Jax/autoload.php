<?php

declare(strict_types=1);

namespace Jax;

use Exception;

use function dirname;
use function file_exists;
use function function_exists;
use function spl_autoload_register;
use function str_replace;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Polyfills
if (!function_exists('array_any')) {
    // Only exists in 8.4 :(
    function array_any(array $array, callable $callback): bool
    {
        foreach ($array as $v) {
            if ($callback($v)) {
                return true;
            }
        }

        return false;
    }
}

spl_autoload_register(static function ($className): void {
    $classPath = dirname(__DIR__) . '/' . str_replace('\\', '/', $className) . '.php';

    if (!file_exists($classPath)) {
        throw new Exception("Error loading class: {$classPath}");
    }

    require_once $classPath;
});
