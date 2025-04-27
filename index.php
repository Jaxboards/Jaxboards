<?php

declare(strict_types=1);

use DI\Container;
use Jax\App;

// Load composer dependencies.
require_once __DIR__ . '/Jax/autoload.php';

/**
 * Jaxboards. THE ULTIMATE 4UMS WOOOOOOO
 * By Sean John's son (2007 @ 4 AM).
 *
 * PHP Version 8
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */
$container = new Container();
$container->get(App::class)->render();
