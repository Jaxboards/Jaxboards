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
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;

use function DI\autowire;
use function getenv;
use function is_string;
use function ltrim;
use function parse_str;
use function parse_url;
use function password_hash;
use function session_id;

use const PASSWORD_DEFAULT;

/**
 * @internal
 */
#[CoversNothing]
abstract class FeatureTestCase extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        session_id('paratest-' . getenv('TEST_TOKEN'));

        $this->setupDB();
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
        $location = $this->container->get(Router::class)->url(
            $name,
            $params,
        ) ?? $name;
        static::assertStringContainsString("Location: {$location}", $page);
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
        $members = $this->insertMembers($memberOverrides);

        if (! $member instanceof Member) {
            $member = $members[$member];
        }

        $this->container->set(
            User::class,
            autowire()->constructorParameter('member', $member),
        );

        $this->container->set(
            JaxSession::class,
            autowire()->constructorParameter(
                'session',
                ['uid' => $member->id ?? null, ...$sessionOverrides],
            ),
        );
    }

    private function setupDB(): void
    {
        $databaseUtils = $this->container->get(DatabaseUtils::class);
        $databaseUtils->install();
    }

    /**
     * @param array<mixed> $memberOverrides
     *
     * @return array<Member>
     */
    private function insertMembers(array $memberOverrides = []): array
    {
        $database = $this->container->get(Database::class);

        $admin = new Member();
        $admin->id = 1;
        $admin->name = 'Admin';
        $admin->displayName = 'Admin';
        $admin->sig = 'I like tacos';
        $admin->pass = password_hash('password', PASSWORD_DEFAULT);
        $admin->birthdate = $database->date();
        $admin->groupID = Groups::Admin->value;

        $member = new Member();
        $member->id = 2;
        $member->name = 'Member';
        $member->displayName = 'Member';
        $member->groupID = Groups::Member->value;

        $banned = new Member();
        $banned->id = 4;
        $banned->name = 'Banned';
        $banned->displayName = 'Banned';
        $banned->groupID = Groups::Banned->value;

        $members = [
            'admin' => $admin,
            'banned' => $banned,
            'member' => $member,
        ];

        // override properties and insert the models
        foreach ($members as $member) {
            $member->joinDate = $database->datetime();
            $member->lastVisit = $database->datetime();
            foreach ($memberOverrides as $property => $value) {
                $member->{$property} = $value;
            }

            $member->insert();
        }

        $members['guest'] = null;

        return $members;
    }
}
