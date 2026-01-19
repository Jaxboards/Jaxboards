<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\App;
use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\BBCode;
use Jax\BotDetector;
use Jax\Config;
use Jax\Constants\JSAccess;
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
use Jax\Routes\BoardOffline;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use Jax\UserOnline;
use Jax\UsersOnline;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
#[CoversClass(App::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(BotDetector::class)]
#[CoversClass(BoardOffline::class)]
#[CoversClass(Column::class)]
#[CoversClass(Config::class)]
#[CoversClass(Database::class)]
#[CoversClass(DatabaseUtils::class)]
#[CoversClass(Date::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(BoardIndex::class)]
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
#[CoversClass(Hooks::class)]
#[CoversClass(WebHooks::class)]
#[CoversClass(SQLite::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(User::class)]
#[CoversClass(UsersOnline::class)]
#[CoversClass(UserOnline::class)]
final class BoardIndexTest extends FeatureTestCase
{
    public function testViewForumIndexAsAdmin(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/');

        DOMAssert::assertSelectEquals('#userbox .welcome', 'Admin', 1, $page);

        DOMAssert::assertSelectEquals('#cat_1 .title', 'Category', 1, $page);

        DOMAssert::assertSelectEquals(
            '#fid_1 .description',
            'Your very first forum!',
            1,
            $page,
        );
        DOMAssert::assertSelectEquals(
            '#fid_1_lastpost',
            'Welcome to Jaxboards!',
            1,
            $page,
        );

        DOMAssert::assertSelectEquals(
            '#stats .content',
            '1 User Online:',
            1,
            $page,
        );
        DOMAssert::assertSelectEquals('#statusers .user1', 'Admin', 1, $page);
        DOMAssert::assertSelectCount('#statusers .user1.birthday', 1, $page);
        DOMAssert::assertSelectEquals(
            '#stats .userstoday',
            '1 User Online Today:',
            1,
            $page,
        );
    }

    public function testBoardIndexUpdate(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            server: ['HTTP_X_JSACCESS' => JSAccess::UPDATING->value],
        ));

        self::assertStringContainsString('onlinelist', $page);
    }

    public function testDebugInfo(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            server: ['REMOTE_ADDR' => '::1'],
        ));

        DOMAssert::assertSelectCount('#debug', 1, $page);
    }

    public function testViewForumIndexAsBannedGroup(): void
    {
        $this->actingAs('banned');

        $page = $this->go('/');

        DOMAssert::assertSelectRegExp(
            '.error',
            "/You don't have permission to view the board./",
            1,
            $page,
        );
    }

    public function testViewForumIndexAsBannedIP(): void
    {
        // This test is a little weird.
        // We have to set the request up (to mock out the IP) before anything else gets initialized
        $request = new Request(
            get: ['path' => '/'],
            server: ['REMOTE_ADDR' => '1.2.3.4'],
        );
        $this->container->set(Request::class, $request);

        $this->actingAs('guest');
        $this->container->get(IPAddress::class)->ban('1.2.3.4');

        $page = $this->go($request);

        DOMAssert::assertSelectRegExp(
            '.error',
            "/You don't have permission to view the board./",
            1,
            $page,
        );
    }
}
