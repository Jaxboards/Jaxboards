<?php

declare(strict_types=1);

namespace Tests;

use DI\Container;
use Jax\Config;
use Jax\ServiceConfig;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

use function DI\autowire;

/**
 * @internal
 */
#[CoversNothing]
final class TestCase extends PHPUnitTestCase
{
    private readonly Container $container;

    public function __construct(string $name)
    {
        $this->container = new Container();

        $this->container->set(Config::class, autowire()->constructorParameter('boardConfig', [
            'boardoffline' => '0',
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
