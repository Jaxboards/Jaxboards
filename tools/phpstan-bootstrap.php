<?php

/**
 * Bootstrap file for phpstan and rector.
 *
 * Ensures that custom autoloading and other mechanisms are in palce to aid the
 * static analysis
 */

// phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
$CFG = [];

// always load configuration
require dirname(__DIR__) . '/config.default.php';
