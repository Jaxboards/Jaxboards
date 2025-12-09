<?php

declare(strict_types=1);

use DI\Container;
use Jax\API;
use Jax\Database\Database;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$container = new Container();
// Init DB before API routes
$container->get(Database::class);
echo $container->get(API::class)->render();
