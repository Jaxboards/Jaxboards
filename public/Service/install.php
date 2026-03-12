<?php

/**
 * Service install file, for installing a new Jaxboards service.
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */

declare(strict_types=1);

use DI\Container;
use Jax\Routes\ServiceInstall;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$container = new Container();

echo $container->get(ServiceInstall::class)->render();
