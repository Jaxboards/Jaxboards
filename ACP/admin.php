<?php

declare(strict_types=1);

use ACP\App;
use DI\Container;

// Load composer dependencies.
require_once dirname(__DIR__) . '/Jax/autoload.php';

$container = new Container();
$container->get(App::class)->render();
