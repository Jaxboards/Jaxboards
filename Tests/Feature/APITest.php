<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\BBCode\Games;
use Jax\App;
use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\BBCode;
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
use Jax\Routes\API;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\FeatureTestCase;

use function json_decode;

/**
 * @internal
 */
#[CoversClass(API::class)]
#[CoversClass(Column::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(Key::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(Games::class)]
#[CoversClass(Config::class)]
#[CoversClass(Database::class)]
#[CoversClass(DatabaseUtils::class)]
#[CoversClass(SQLite::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Lodash::class)]
#[CoversClass(Model::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(User::class)]
#[CoversClass(BotDetector::class)]
#[CoversClass(Page::class)]
#[CoversClass(Router::class)]
#[CoversClass(Session::class)]
#[CoversClass(Template::class)]
#[CoversClass(App::class)]
#[CoversClass(Date::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(Hooks::class)]
#[CoversClass(WebHooks::class)]
final class APITest extends FeatureTestCase
{
    public function testSearchMembers(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/api/searchmembers?term=admin');

        self::assertEquals([[1], ['Admin']], json_decode($page, true));
    }

    public function testEmotes(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/api/emotes');

        self::assertContains(':)', json_decode($page, true)[0]);
    }
}
