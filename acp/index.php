<?php

declare(strict_types=1);

use ACP\Page\Login;
use DI\Container;

if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', dirname(__DIR__));
}

/*
 * Admin login.
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */

// Load composer dependencies.
require_once JAXBOARDS_ROOT . '/jax/autoload.php';
$container = new Container();

require_once JAXBOARDS_ROOT . '/domaindefinitions.php';

$container->get(Login::class)->render();
