<?php

declare(strict_types=1);

namespace Tests;

use DI\Container;
use Jax\App;
use Jax\Constants\Groups;
use Jax\Database;
use Jax\DatabaseUtils;
use Jax\Models\Member;
use Jax\Request;
use Jax\ServiceConfig;
use Jax\Session as JaxSession;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

use function DI\autowire;
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

        return parent::__construct($name);
    }

    protected function setUp(): void
    {
        $this->setupDB();
        parent::setUp();
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

        return $this->container->get(App::class)->render() ?? '';
    }

    public function actingAs(Member|string $member): void
    {
        $database = $this->container->get(Database::class);

        if (! $member instanceof Member) {
            $memberName = $member;
            $member = new Member();
            $member->joinDate = $database->datetime();
            $member->lastVisit = $database->datetime();

            switch ($memberName) {
                case 'admin':
                    $member->id = 1;
                    $member->name = 'Admin';
                    $member->displayName = 'Admin';
                    $member->sig = 'I like tacos';
                    $member->groupID = Groups::Admin->value;

                    break;

                default:
                    $member->id = 2;
                    $member->name = 'Member';
                    $member->displayName = 'Member';
                    $member->groupID = Groups::Member->value;

                    break;
            }

            $member->insert();
        }

        $this->container->set(
            User::class,
            autowire()->constructorParameter('member', $member),
        );

        $this->container->set(
            JaxSession::class,
            autowire()->constructorParameter('session', ['uid' => $member->id]),
        );
    }

    private function setupDB(): void
    {
        $databaseUtils = $this->container->get(DatabaseUtils::class);
        $databaseUtils->install();
    }
}
