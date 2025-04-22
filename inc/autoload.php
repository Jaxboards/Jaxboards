<?php
    if (!defined('JAXBOARDS_ROOT')) {
        exit(1);
    }

    require_once JAXBOARDS_ROOT . '/vendor/autoload.php';

    spl_autoload_register(function ($className) {
        $classPath = JAXBOARDS_ROOT . "/inc/classes/{$className}.php";

        if (file_exists($classPath)) {
            require_once $classPath;
        }
    });
?>
