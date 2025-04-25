<?php

declare(strict_types=1);

/*
 * Bootstrap file for phpstan and rector.
 *
 * Ensures that custom autoloading and other mechanisms are in palce to aid the
 * static analysis
 */

// phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
$CFG = [];

// always load configuration
require_once dirname(__DIR__) . '/config.default.php';

// load classes
$classFiles = glob(dirname(__DIR__) . '/jax/*.php');

// Exclude tests
$classFiles = array_filter($classFiles, static fn($fileName): bool => !str_contains((string) $fileName, '.test.'));

foreach ($classFiles as $classFile) {
    require_once $classFile;
}
