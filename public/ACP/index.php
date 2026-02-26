<?php

declare(strict_types=1);

use ACP\Routes\Login;
use DI\Container;

// Load composer dependencies.
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$container = new Container();
$container->get(Login::class)->route([]);
