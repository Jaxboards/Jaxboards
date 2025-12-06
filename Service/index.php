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
use Jax\FileUtils;
use Jax\Page\ServiceSignup;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$container = new Container();
$fileUtils = $container->get(FileUtils::class);
if (!$fileUtils->exists(dirname(__DIR__) . '/config.php')) {
    echo 'Jaxboards not installed!';
} else {
    $container->get(ServiceSignup::class)->render();
}
