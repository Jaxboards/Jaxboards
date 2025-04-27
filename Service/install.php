<?php

use DI\Container;
use Jax\Page\ServiceInstall;

/*
 * Service install file, for installing a new JaxBoards service.
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */


if (file_exists(dirname(__DIR__) . '/config.php')) {
    echo 'Detected config.php at root. Jaxboards has already been installed. If you would like to reinstall, delete the root config.';

    exit(1);
}

require_once dirname(__DIR__) . '/Jax/autoload.php';
$container = new Container();

$container->get(ServiceInstall::class)->render();
