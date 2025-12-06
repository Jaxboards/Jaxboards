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
use Jax\FileSystem;
use Jax\Page\ServiceSignup;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$container = new Container();
$fileSystem = $container->get(FileSystem::class);
if (!$fileSystem->getFileInfo('config.php')->isFile()) {
    echo 'Jaxboards not installed!';
} else {
    $container->get(ServiceSignup::class)->render();
}
