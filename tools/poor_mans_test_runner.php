<?php

declare(strict_types=1);

namespace Tools;

use DI\Container;
use Jax\FileUtils;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

use function array_filter;
use function array_map;
use function dirname;
use function glob;
use function str_starts_with;

use const PHP_EOL;

require_once dirname(__DIR__) . '/Jax/autoload.php';
$container = new Container();

$testFiles = glob(dirname(__DIR__) . '/**/*Test.php');

$passingTests = 0;
$failingTests = 0;

foreach ($testFiles as $testFile) {
    $classPath = $container->get(FileUtils::class)->toClassPath($testFile);
    $class = $container->get($classPath);

    $reflection = new ReflectionClass($classPath);
    $testMethods = array_filter(
        array_map(static fn($method) => $method->name, $reflection->getMethods(ReflectionMethod::IS_PUBLIC)),
        static fn($methodName): bool => !str_starts_with((string) $methodName, '_'),
    );

    echo "--- {$classPath} ---" . PHP_EOL;

    foreach ($testMethods as $testMethod) {
        try {
            $class->{$testMethod}();
            echo "{$testMethod}: Pass" . PHP_EOL;
            ++$passingTests;
        } catch (Throwable $e) {
            echo "{$testMethod}: FAILED: {$e->getMessage()}" . PHP_EOL;
            ++$failingTests;
        }
    }

    echo PHP_EOL;
}

echo "Pass: {$passingTests} Fail: {$failingTests}" . PHP_EOL;

exit($failingTests !== 0 ? 1 : 0);
