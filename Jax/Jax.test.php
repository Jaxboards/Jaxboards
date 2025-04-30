<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use DI\Container;
use Jax\Jax;

$container = new Container();

$encodedForumFlags = base64_decode('AAEAPgADABgABAAYAAUAGAAGAD8=');

$decoded = [
    1 => ['upload' => false, 'reply' => true, 'start' => true, 'read' => true, 'view' => true, 'poll' => true],
    3 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
    4 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
    5 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
    6 => ['upload' => true, 'reply' => true, 'start' => true, 'read' => true, 'view' => true, 'poll' => true]
];

$tests = [];
$tests['getForumPermissions'] = static function () use ($container, $encodedForumFlags, $decoded): void {
    $jax = $container->get(Jax::class);
    $result = $jax->parseForumPerms($encodedForumFlags);

    foreach (array_keys($decoded) as $groupId) {
        $diff = array_diff($decoded[$groupId], $result[$groupId]);
        assert($diff === [], "$groupId permissions differs: " . json_encode($diff));
    }
};

$tests['serializeForumPermissions'] = static function () use ($container, $encodedForumFlags, $decoded): void {
    $jax = $container->get(Jax::class);
    $result = $jax->serializeForumPerms($decoded);

    assert($result === $encodedForumFlags);
};

$tests['sanity'] = static function () use ($container, $encodedForumFlags, $decoded) {
    $jax = $container->get(Jax::class);
    assert($encodedForumFlags === $jax->serializeForumPerms($jax->parseForumPerms($encodedForumFlags)));
};

// Poor man's test runner XD
foreach ($tests as $testName => $test) {
    echo $testName;
    $test();
    echo ': Passed' . PHP_EOL;
}
