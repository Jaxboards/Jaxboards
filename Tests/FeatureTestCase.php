<?php

declare(strict_types=1);

namespace Tests;

use Jax\App;
use Jax\Constants\Groups;
use Jax\Database\Database;
use Jax\Database\Utils as DatabaseUtils;
use Jax\Models\Member;
use Jax\Request;
use Jax\Router;
use Jax\Session as JaxSession;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversNothing;

use function DI\autowire;
use function is_string;
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

    public function go(
        Request|string|null $request = null,
        string $pageClass = App::class,
    ): string {
        if (is_string($request)) {
            $parsed = parse_url($request);
            parse_str($parsed['query'] ?? '', $getParameters);
            $getParameters['path'] = ltrim($parsed['path'] ?? '', '/');
            $request = new Request(
                get: $getParameters,
            );
        }

        if ($request instanceof Request) {
            $this->container->set(Request::class, $request);
        }

        return $this->container->get($pageClass)->render() ?? '';
    }

    public function assertRedirect(
        string $name,
        array $params = [],
        ?string $page = null,
    ): void {
        $location = $this->container->get(Router::class)->url($name, $params) ?? $name;
        $this->assertStringContainsString("Location: {$location}", $page);
    }

    /**
     * Sets up an authenticated user for testing purposes.
     *
     * @param array<mixed> $memberOverrides  Overrides to properties of the Member model
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
                    $member->birthdate = $database->date();
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
