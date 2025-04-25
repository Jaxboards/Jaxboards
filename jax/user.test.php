<?php

declare(strict_types=1);

require_once 'autoload.php';

use DI\Container;
use Jax\IPAddress;
use Jax\User;

use function DI\Create;

final class MockIPAddress extends IPAddress
{
    public function __construct() {}

    public function isBanned($ipAddress = false): bool
    {
        return false;
    }
}

$container = new Container();
$container->set(IPAddress::class, Create(MockIPAddress::class));

/*
 *          Use Global?     View        Read    Start   Reply   Upload  Polls
 * Member   X               Y           Y       Y       Y       N       Y
 * Admin    Y
 * Guest    X               Y           Y       N       N       N       N
 * Banned   X               Y           Y       N       N       N       N
 */
$encodedForumFlags = base64_decode('AAEAPgADABgABAAYAAUAGAAGAD8=');

$tests = [];
$tests['parseForumPermissionWithInteger'] = static function () use ($container): void {
    $user = $container->get(User::class);

    $allOn = 0b11111111;

    $expected = ['poll' => 1, 'read' => 1, 'reply' => 1, 'start' => 1, 'upload' => 1, 'view' => 1];
    $result = $user->parseForumPerms($allOn);
    $diff = array_diff_assoc($expected, $result);

    assert($diff === [], 'Expected value differs: ' . json_encode($diff));
};


$tests['parseForumPermissionAsAdmin'] = static function () use ($container, $encodedForumFlags): void {
    $user = $container->get(User::class);

    $user->userPerms = ['can_poll' => 1, 'can_post' => 1, 'can_post_topics' => 1, 'can_attach' => 1];
    $user->userData = ['group_id' => 2];

    $expected = ['poll' => 1, 'read' => 1, 'reply' => 1, 'start' => 1, 'upload' => 1, 'view' => 1];
    $result = $user->parseForumPerms($encodedForumFlags);
    $diff = array_diff_assoc($expected, $result);

    assert($diff === [], 'Expected value differs: ' . json_encode($diff));
};

$tests['parseForumPermissionAsGuest'] = static function () use ($container, $encodedForumFlags): void {
    $user = $container->get(User::class);

    $user->userPerms = ['can_post' => 1];
    $user->userData = ['group_id' => 3];

    $expected = ['poll' => 0, 'read' => 1, 'reply' => 0, 'start' => 0, 'upload' => 0, 'view' => 1];
    $result = $user->parseForumPerms($encodedForumFlags);
    $diff = array_diff_assoc($expected, $result);

    assert($diff === [], 'Expected value differs: ' . json_encode($diff));
};

$tests['parseForumPermissionAsBanned'] = static function () use ($container, $encodedForumFlags): void {
    $user = $container->get(User::class);

    $user->userPerms = ['can_post' => 1];
    $user->userData = ['group_id' => 4];

    $expected = ['poll' => 0, 'read' => 1, 'reply' => 0, 'start' => 0, 'upload' => 0, 'view' => 1];
    $result = $user->parseForumPerms($encodedForumFlags);
    $diff = array_diff_assoc($expected, $result);

    assert($diff === [], 'Expected value differs: ' . json_encode($diff));
};

// Poor man's test runner XD
foreach ($tests as $testName => $test) {
    echo $testName;
    $test();
    echo ': Passed' . PHP_EOL;
}
