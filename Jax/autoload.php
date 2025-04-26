<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;

use function define;
use function defined;
use function dirname;
use function file_exists;
use function mb_strtolower;
use function spl_autoload_register;
use function str_replace;

if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', dirname(__DIR__));
}

require_once JAXBOARDS_ROOT . '/vendor/autoload.php';

spl_autoload_register(static function ($className): void {

    $classPath = mb_strtolower(JAXBOARDS_ROOT . match (true) {
        default => '/' . str_replace('\\', '/', $className) . '.php',
    });

    if (!file_exists($classPath)) {
        return;
    }

    require_once $classPath;
});

$container = new Container();
