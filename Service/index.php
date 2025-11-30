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
use Jax\Page\ServiceSignup;

require_once dirname(__DIR__) . '/autoload.php';

if (!file_exists(dirname(__DIR__) . '/config.php')) {
    echo 'Jaxboards not installed!';
} else {
    $container = new Container();
    $container->get(ServiceSignup::class)->render();
}
