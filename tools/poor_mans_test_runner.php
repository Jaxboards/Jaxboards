<?php

namespace Tools;

use \DI\Container;
use Reflection;
use ReflectionClass;
use ReflectionMethod;

$root = dirname(__DIR__);

require_once $root . '/Jax/autoload.php';
$container = new Container();

$testFiles = glob($root . '/**/*Test.php');

foreach ($testFiles as $testFile) {
    $classPath = str_replace([$root, '.php', '/'], ['', '', '\\'], $testFile);
    $class = $container->get($classPath);

    $reflection = new ReflectionClass($classPath);
    $testMethods = array_filter(
        array_map(fn($method) => $method->name, $reflection->getMethods(ReflectionMethod::IS_PUBLIC)),
        fn($methodName) => !str_starts_with($methodName, '_'),
    );

    echo "--- {$classPath} ---". PHP_EOL;
    foreach ($testMethods as $testMethod) {
        try {
            $class->{$testMethod}();
            echo "{$testMethod}: Pass" . PHP_EOL;
        } catch (\Throwable $e) {
            echo "{$testMethod}: FAILED: {$e->getMessage()}" . PHP_EOL;
        }
    }
    echo PHP_EOL;
}
