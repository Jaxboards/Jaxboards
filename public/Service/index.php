<?php

/**
 * Service signup file, for users to create their own JaxBoards forum.
 *
 * PHP Version 8
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */

declare(strict_types=1);

use DI\Container;
use Jax\Routes\ServiceSignup;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$container = new Container();
echo $container->get(ServiceSignup::class)->render();
