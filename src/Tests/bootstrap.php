<?php

declare(strict_types=1);

use DG\BypassFinals;

$root = dirname(dirname(__DIR__));
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
BypassFinals::enable();
BypassFinals::allowPaths(['*/Jax/*']);
BypassFinals::setCacheDirectory(dirname(__DIR__) . '/.cache/.phpunit.cache');
