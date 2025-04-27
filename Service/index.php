<?php

declare(strict_types=1);

use DI\Container;
use Jax\Page\ServiceSignup;

/*
 * Service signup file, for users to create their own JaxBoards forum.
 *
 * PHP Version 8
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */
if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', dirname(__DIR__));
}

if (!defined('SERVICE_ROOT')) {
    define('SERVICE_ROOT', __DIR__);
}

require_once JAXBOARDS_ROOT . '/Jax/autoload.php';

if (!file_exists(JAXBOARDS_ROOT . '/config.php')) {
    echo 'Jaxboards not installed!';
} else {
    $container = new Container();
    $container->get(ServiceSignup::class)->render();
}
