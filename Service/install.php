<?php

declare(strict_types=1);

use DI\Container;
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

echo $container->get(ServiceInstall::class)->render();
