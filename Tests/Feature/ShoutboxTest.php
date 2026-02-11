<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\App;
use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\BBCode;
use Jax\BBCode\Games;
use Jax\BotDetector;
use Jax\Config;
use Jax\Database\Adapters\SQLite;
use Jax\Database\Database;
use Jax\Database\Model;
use Jax\Database\Utils as DatabaseUtils;
use Jax\Date;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\Hooks;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Lodash;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Modules\WebHooks;
use Jax\Page;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\Routes\BoardIndex;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use Jax\UserOnline;
use Jax\UsersOnline;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

use function DI\autowire;

/**
 * @internal
 */
#[CoversClass(App::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(Games::class)]
#[CoversClass(BoardIndex::class)]
#[CoversClass(BotDetector::class)]
#[CoversClass(Column::class)]
#[CoversClass(Config::class)]
#[CoversClass(Database::class)]
#[CoversClass(DatabaseUtils::class)]
#[CoversClass(Date::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(Hooks::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Key::class)]
#[CoversClass(Lodash::class)]
#[CoversClass(Model::class)]
#[CoversClass(Page::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(Router::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(Session::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(SQLite::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(User::class)]
#[CoversClass(UserOnline::class)]
#[CoversClass(UsersOnline::class)]
#[CoversClass(WebHooks::class)]
final class ShoutboxTest extends FeatureTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Configure shoutbox to be enabled
        $this->container->set(
            Config::class,
            autowire()->constructorParameter(
                'boardConfig',
                ['shoutbox' => true],
            ),
        );
    }

    public function testUnauthShout(): void
    {
        $this->actingAs('guest');

        $page = $this->go(new Request(
            post: ['shoutbox_shout' => 'test'],
        ));

        DOMAssert::assertSelectEquals(
            '.error',
            'You must be logged in to shout!',
            1,
            $page,
        );
    }

    public function testAuthShout(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            post: ['shoutbox_shout' => 'hello world!'],
        ));

        DOMAssert::assertSelectEquals(
            '#shoutbox .shouts .shout .user2',
            'Member',
            1,
            $page,
        );
        DOMAssert::assertSelectEquals(
            '#shoutbox .shouts .shout',
            'hello world!',
            1,
            $page,
        );
    }

    public function testMeCommand(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            post: ['shoutbox_shout' => '/me did some stuff just now'],
        ));

        DOMAssert::assertSelectEquals(
            '#shoutbox .shouts .shout .user2',
            'Member',
            1,
            $page,
        );
        DOMAssert::assertSelectRegExp(
            '#shoutbox .shouts .shout.action',
            '/did some stuff just now/',
            1,
            $page,
        );
    }

    public function testViewAllShouts(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['module' => 'shoutbox'],
            post: ['shoutbox_shout' => 'Howdy partner!'],
        ));

        DOMAssert::assertSelectCount(
            '#shoutbox',
            1,
            $page,
        );

        DOMAssert::assertSelectEquals(
            '.sbhistory .shout',
            'Howdy partner!',
            1,
            $page,
        );
    }
}
