<?php

declare(strict_types=1);

use DI\Container;
use Jax\FileUtils;
use Jax\Page\ServiceInstall;

/**
 * Service install file, for installing a new JaxBoards service.
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */




require_once dirname(__DIR__) . '/vendor/autoload.php';

$container = new Container();
$fileUtils = $container->get(FileUtils::class);

if ($fileUtils->isFile(dirname(__DIR__) . '/config.php')) {
    echo 'Detected config.php at root. '
        . 'Jaxboards has already been installed. '
        . 'If you would like to reinstall, delete the root config.';
} else {
    $container->get(ServiceInstall::class)->render();
}
