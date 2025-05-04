<?php

declare(strict_types=1);

namespace Jax;

use Exception;

use function dirname;
use function file_exists;
use function in_array;
use function spl_autoload_register;
use function str_replace;

require_once dirname(__DIR__) . '/vendor/autoload.php';

spl_autoload_register(static function ($className): void {
    $relativeClassPath = '/' . str_replace('\\', '/', $className) . '.php';
    $classPath = dirname(__DIR__) . $relativeClassPath;

    if (!file_exists($classPath)) {
        if (
            in_array(
                $relativeClassPath,
                [
                    // rector attempts to load these classes errorneously and
                    // errors are safe to ignore here
                    '/PHPUnit/Framework/TestCase.php',
                    '/null.php',
                ],
                true,
            )
        ) {
            return;
        }

        throw new Exception("Error loading class: {$classPath}");
    }

    require_once $classPath;
});
