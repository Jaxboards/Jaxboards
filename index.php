<?php

declare(strict_types=1);


use DI\Container;
use Jax\App;

if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', __DIR__);
}

// Load composer dependencies.
require_once JAXBOARDS_ROOT . '/jax/autoload.php';

/*
 * Jaxboards. THE ULTIMATE 4UMS WOOOOOOO
 * By Sean John's son (2007 @ 4 AM).
 *
 * PHP Version 8
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */

$container->get(App::class)->render();
