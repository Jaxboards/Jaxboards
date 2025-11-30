<?php

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * @internal
 */
#[CoversNothing]
abstract class TestCase extends PHPUnitTestCase {}
