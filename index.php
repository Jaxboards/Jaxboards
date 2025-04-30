<?php

declare(strict_types=1);

use DI\Container;
use Jax\App;

// Load composer dependencies.
require_once __DIR__ . '/Jax/autoload.php';

$container = new Container();
$container->get(App::class)->render();
