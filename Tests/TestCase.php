<?php

declare(strict_types=1);

namespace Tests;

use DI\Container;
use Jax\App;
use Jax\DatabaseUtils;
use Jax\Request;
use Jax\ServiceConfig;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

use function parse_str;
use function parse_url;

/**
 * @internal
 */
#[CoversNothing]
abstract class TestCase extends PHPUnitTestCase
{
    public function go(string $path): string
    {
        parse_str(parse_url($path)['query'], $getParameters);

        $container = new Container([
            Request::class => new Request(
                get: $getParameters,
            ),
        ]);

        $container->set(ServiceConfig::class, new ServiceConfig([
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

        $databaseUtils = $container->get(DatabaseUtils::class);
        $databaseUtils->install();

        return $container->get(App::class)->render() ?? '';
    }
}
