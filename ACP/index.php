<?php

declare(strict_types=1);

use ACP\Page\Login;
use DI\Container;

// Load composer dependencies.
require_once dirname(__DIR__) . '/autoload.php';

$container = new Container();
$container->get(Login::class)->render();
