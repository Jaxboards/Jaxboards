<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use DI\Container;
use Jax\User;

$container = new Container();


$encodedForumFlags = base64_decode('AAEAPgADABgABAAYAAUAGAAGAD8=');
$decoded = [
    1 => ['upload' => false, 'reply' => true, 'start' => true, 'read' => true, 'view' => true, 'poll' => true],
    3 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
    4 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
    5 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
    6 => ['upload' => true, 'reply' => true, 'start' => true, 'read' => true, 'view' => true, 'poll' => true],
];

$tests = [];
// $tests['getForumPermissionWithInteger'] = static function () use ($container): void {
// $user = $container->get(User::class);
// $allOn = 0b11111111;
// $expected = ['poll' => true, 'read' => true, 'reply' => true, 'start' => true, 'upload' => true, 'view' => true];
// $result = $user->getForumPerms($allOn);
// $diff = array_diff_assoc($expected, $result);
// assert($diff === [], 'Expected value differs: ' . json_encode($diff));
// };
$tests['getForumPermissionAsAdmin'] = static function () use ($container, $encodedForumFlags): void {
    $user = $container->get(User::class);

    $user->userPerms = ['can_poll' => true, 'can_post' => true, 'can_post_topics' => true, 'can_attach' => true];
    $user->userData = ['group_id' => 2];

    $expected = ['poll' => true, 'read' => true, 'reply' => true, 'start' => true, 'upload' => true, 'view' => true];
    $result = $user->getForumPerms($encodedForumFlags);
    $diff = array_diff_assoc($expected, $result);

    assert($diff === [], 'Expected value differs: ' . json_encode($diff));
};

$tests['getForumPermissionAsGuest'] = static function () use ($container, $encodedForumFlags): void {
    $user = $container->get(User::class);

    $user->userPerms = ['can_post' => true];
    $user->userData = ['group_id' => 3];

    $expected = ['poll' => false, 'read' => true, 'reply' => false, 'start' => false, 'upload' => false, 'view' => true];
    $result = $user->getForumPerms($encodedForumFlags);
    $diff = array_diff_assoc($expected, $result);

    assert($diff === [], 'Expected value differs: ' . json_encode($diff));
};

$tests['getForumPermissionAsBanned'] = static function () use ($container, $encodedForumFlags): void {
    $user = $container->get(User::class);

    $user->userPerms = ['can_post' => true];
    $user->userData = ['group_id' => 4];

    $expected = ['poll' => false, 'read' => true, 'reply' => false, 'start' => false, 'upload' => false, 'view' => true];
    $result = $user->getForumPerms($encodedForumFlags);
    $diff = array_diff_assoc($expected, $result);

    assert($diff === [], 'Expected value differs: ' . json_encode($diff));
};

// Poor man's test runner XD
foreach ($tests as $testName => $test) {
    echo $testName;
    $test();
    echo ': Passed' . PHP_EOL;
}
