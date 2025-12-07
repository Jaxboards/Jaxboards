<?php

declare(strict_types=1);

namespace Tests;

use DI\Container;
use Jax\Config;
use Jax\FileSystem;
use Jax\ServiceConfig;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

use function DI\autowire;

/**
 * @internal
 */
#[CoversNothing]
abstract class TestCase extends PHPUnitTestCase
{
    protected readonly Container $container;

    public function __construct(string $name)
    {
        $this->container = new Container();

        // Prevent test suite from mutating files
        $this->container->set(
            FileSystem::class,
            $this->getMockBuilder(FileSystem::class)
                ->onlyMethods([
                    'copy',
                    'copyDirectory',
                    'mkdir',
                    'putContents',
                    'removeDirectory',
                    'rename',
                    'unlink',
                ])
                ->getMock(),
        );

        $this->container->set(Config::class, autowire()->constructorParameter('boardConfig', [
            'boardoffline' => '0',
            'birthdays' => '1',
            'emotepack' => 'keshaemotes',
            'offlinetext' => 'The board is offline!',
            'shoutbox' => '1',
            'shoutbox_num' => '10',
            'timetoidle' => '300',
            'timetologout' => '900',
            'usedisplayname' => '1',
        ]));

        $this->container->set(ServiceConfig::class, autowire()->constructorParameter('config', [
            'badnamechars' => "@[^\\w' ?]@",
            'boardname' => 'Example Forums',
            'domain' => 'example.com',
            'mail_from' => 'Example Forums <no-reply@example.com>',
            'prefix' => 'jaxboards',
            'service' => false,
            'sql_driver' => 'sqliteMemory',
            'sql_db' => 'jaxboards',
            'sql_host' => '127.0.0.1',
            'sql_username' => 'root',
            'sql_password' => '',
            'sql_prefix' => 'jaxboards_',
            'timetologout' => 900,
        ]));

        return parent::__construct($name);
    }
}
