<?php

declare(strict_types=1);

use DI\Container;
use Jax\API;

if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', dirname(__DIR__));
}

require_once JAXBOARDS_ROOT . '/jax/autoload.php';

$container->get(API::class)->render();
