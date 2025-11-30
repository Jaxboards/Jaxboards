<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

spl_autoload_register(static function ($className): void {
    $classPath = __DIR__ . '/' . str_replace('\\', '/', $className) . '.php';

    if (!file_exists($classPath)) {
        return;
    }

    require_once $classPath;
});
