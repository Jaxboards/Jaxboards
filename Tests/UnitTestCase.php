<?php

declare(strict_types=1);

namespace Tests;

use DI\Container;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * @internal
 */
abstract class UnitTestCase extends PHPUnitTestCase {
    protected Container $container;

    public function __construct(string $name) {
        $this->container = new Container();
        parent::__construct($name);
    }
}
