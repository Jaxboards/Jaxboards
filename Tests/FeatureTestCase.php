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
abstract class FeatureTestCase extends PHPUnitTestCase
{
    protected Container $container;

    public function __construct(string $name)
    {
        $this->container = new Container();

        return parent::__construct($name);
    }

    public function go(Request|string $request): string
    {
        if (!$request instanceof Request) {
            parse_str(parse_url($request)['query'], $getParameters);
            $request = new Request(
                get: $getParameters,
            );
        }

        $this->container->set(Request::class, $request);

        $this->container->set(ServiceConfig::class, new ServiceConfig([
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

        $databaseUtils = $this->container->get(DatabaseUtils::class);
        $databaseUtils->install();

        return $this->container->get(App::class)->render() ?? '';
    }
}
