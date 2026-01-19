<?php

declare(strict_types=1);

namespace Tests;

use DI\Container;
use Jax\Config;
use Jax\FileSystem;
use Jax\ServiceConfig;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

use function DI\autowire;

/**
 * @internal
 */
#[CoversNothing]
abstract class TestCase extends PHPUnitTestCase
{
    protected Container $container;

    /**
     * Set up for tests, runs before each test.
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

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

        $this->setServiceConfig();
        $this->setBoardConfig();
    }

    protected function setServiceConfig($config = []): void
    {
        $this->container->set(
            ServiceConfig::class,
            autowire()->constructorParameter('config', [
                'badnamechars' => "@[^\\w' ?]@",
                'boardname' => 'Example Forums',
                'domain' => 'jaxboards.com',
                'mail_from' => 'Example Forums <no-reply@jaxboards.com>',
                'prefix' => 'jaxboards',
                'service' => false,
                'sql_driver' => 'sqliteMemory',
                'sql_db' => 'jaxboards',
                'sql_host' => '127.0.0.1',
                'sql_username' => 'root',
                'sql_password' => '',
                'sql_prefix' => 'jaxboards_',
                'timetologout' => 900,
                ...$config,
            ]),
        );
    }

    protected function setBoardConfig($config = []): void
    {
        $this->container->set(
            Config::class,
            autowire()->constructorParameter('boardConfig', [
                'boardoffline' => 0,
                'badgesEnabled' => 1,
                'birthdays' => 1,
                'emotepack' => 'keshaemotes',
                'offlinetext' => 'The board is offline!',
                'reactions' => 1,
                'shoutbox' => 1,
                'shoutbox_num' => 10,
                'timetoidle' => 300,
                'timetologout' => 900,
                'usedisplayname' => 1,
                ...$config,
            ]),
        );
    }
}
