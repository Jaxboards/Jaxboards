<?php

declare(strict_types=1);

use DI\Container;
use Jax\API;
use Jax\DomainDefinitions;

if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', dirname(__DIR__));
}

require_once JAXBOARDS_ROOT . '/jax/autoload.php';
$container = new Container();

$container->get(DomainDefinitions::class)->defineConstants();
$container->get(API::class)->render();
