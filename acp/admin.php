<?php

declare(strict_types=1);

use ACP\App;
use DI\Container;
use Jax\DomainDefinitions;

if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', dirname(__DIR__));
}

/**
 * Admin login.
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */

// Load composer dependencies.
require_once JAXBOARDS_ROOT . '/jax/autoload.php';
$container = new Container();

$container->get(DomainDefinitions::class)->defineConstants();

$container->get(App::class)->render();
