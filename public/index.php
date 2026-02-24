<?php

declare(strict_types=1);

use DI\Container;
use Jax\App;

// Load composer dependencies.
require_once dirname(__DIR__) . '/vendor/autoload.php';

$container = new Container();
echo $container->get(App::class)->render();
