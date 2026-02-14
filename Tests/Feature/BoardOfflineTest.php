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
use Jax\Database\Utils;
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
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\FeatureTestCase;

/**
 * @internal
 */
#[CoversClass(App::class)]
#[CoversClass(Column::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(Key::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(Games::class)]
#[CoversClass(BotDetector::class)]
#[CoversClass(Config::class)]
#[CoversClass(SQLite::class)]
#[CoversClass(Database::class)]
#[CoversClass(Lodash::class)]
#[CoversClass(Model::class)]
#[CoversClass(Utils::class)]
#[CoversClass(Date::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(Hooks::class)]
#[CoversClass(WebHooks::class)]
#[CoversClass(Page::class)]
#[CoversClass(BoardOffline::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(Router::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(Session::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(User::class)]
#[CoversClass(BoardIndex::class)]
#[CoversClass(UserOnline::class)]
#[CoversClass(UsersOnline::class)]
final class BoardOfflineTest extends FeatureTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->setBoardConfig([
            'boardoffline' => true,
            'offlinetext' => 'pikachu',
        ]);
    }

    public function testViewBoardWhenBoardOfflineAsMember(): void
    {
        $this->actingAs('member');

        $page = $this->go('/');

        static::assertStringContainsString("You don't have permission to view the board.", $page);
        static::assertStringContainsString('pikachu', $page);
    }

    public function testViewBoardWhenBoardOfflineAsAdmin(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/');

        static::assertStringNotContainsString("You don't have permission to view the board.", $page);
    }
}
