<?php

declare(strict_types=1);

use DI\Container;
use Jax\API;

require_once dirname(__DIR__) . '/Jax/autoload.php';

$container = new Container();
$container->get(API::class)->render();
