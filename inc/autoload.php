<?php

declare(strict_types=1);
if (!defined('JAXBOARDS_ROOT')) {
    exit(1);
}

require_once JAXBOARDS_ROOT . '/vendor/autoload.php';

spl_autoload_register(static function ($className): void {
    $classPath = JAXBOARDS_ROOT . "/inc/classes/{$className}.php";

    if (!file_exists($classPath)) {
        return;
    }

    require_once $classPath;
});
