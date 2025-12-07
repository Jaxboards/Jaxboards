<?php

declare(strict_types=1);

namespace Tests;

use Jax\App;
use Jax\Constants\Groups;
use Jax\Database;
use Jax\DatabaseUtils;
use Jax\Models\Member;
use Jax\Request;
use Jax\Session as JaxSession;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversNothing;

use function DI\autowire;
use function parse_str;
use function parse_url;
use function password_hash;

use const PASSWORD_DEFAULT;

/**
 * @internal
 */
#[CoversNothing]
abstract class FeatureTestCase extends TestCase
{
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

    public function assertRedirect(string $location, string $page): void
    {
        $this->assertStringContainsString("Location: {$location}", $page);
    }

    /**
     * Sets up an authenticated user for testing purposes
     * @param array<mixed> $memberOverrides Overrides to properties of the Member model
     * @param array<mixed> $sessionOverrides Overrides to properties of the Session variables
     */
    public function actingAs(
        Member|string $member,
        array $memberOverrides = [],
        array $sessionOverrides = [],
    ): void {
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
                    $member->pass = password_hash('password', PASSWORD_DEFAULT);
                    $member->groupID = Groups::Admin->value;

                    break;

                case 'member':
                default:
                    $member->id = 2;
                    $member->name = 'Member';
                    $member->displayName = 'Member';
                    $member->groupID = Groups::Member->value;

                    break;
            }

            foreach ($memberOverrides as $property => $value) {
                $member->{$property} = $value;
            }

            $member->insert();
        }

        $this->container->set(
            User::class,
            autowire()->constructorParameter('member', $member),
        );

        $this->container->set(
            JaxSession::class,
            autowire()->constructorParameter('session', ['uid' => $member->id, ...$sessionOverrides]),
        );
    }

    private function setupDB(): void
    {
        $databaseUtils = $this->container->get(DatabaseUtils::class);
        $databaseUtils->install();
    }
}
