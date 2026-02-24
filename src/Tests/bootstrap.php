<?php

declare(strict_types=1);

use DG\BypassFinals;

$root = dirname(__DIR__, 2);
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
BypassFinals::enable();
BypassFinals::allowPaths(['*/Jax/*']);
BypassFinals::setCacheDirectory(dirname(__DIR__) . '/.cache/.phpunit.cache');
