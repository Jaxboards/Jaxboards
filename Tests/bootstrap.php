<?php

declare(strict_types=1);

use DG\BypassFinals;

require_once dirname(__DIR__) . '/vendor/autoload.php';
BypassFinals::enable();
BypassFinals::allowPaths(['*/Jax/*']);
BypassFinals::setCacheDirectory(dirname(__DIR__) . '/.cache/.phpunit.cache');
