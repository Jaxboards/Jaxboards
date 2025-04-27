<?php

declare(strict_types=1);

use ACP\Page\Login;
use DI\Container;

/**
 * Admin login.
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */

// Load composer dependencies.
require_once dirname(__DIR__) . '/Jax/autoload.php';

$container = new Container();
$container->get(Login::class)->render();
