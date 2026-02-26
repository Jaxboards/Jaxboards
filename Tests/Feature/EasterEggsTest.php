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
use Jax\Constants\JSAccess;
use Jax\Database\Adapters\SQLite;
use Jax\Database\Database;
use Jax\Database\Model;
use Jax\Database\Utils as DatabaseUtils;
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
use Jax\Routes\Asteroids;
use Jax\Routes\Earthbound;
use Jax\Routes\Katamari;
use Jax\Routes\Rainbow;
use Jax\Routes\Solitaire;
use Jax\Routes\Spin;
use Jax\Routes\Tardis;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use Tests\FeatureTestCase;

use function json_decode;

/**
 * @internal
 */
#[CoversClass(Asteroids::class)]
#[CoversClass(Katamari::class)]
#[CoversClass(Earthbound::class)]
#[CoversClass(Rainbow::class)]
#[CoversClass(Solitaire::class)]
#[CoversClass(Tardis::class)]
#[CoversClass(App::class)]
#[CoversClass(Column::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(Key::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(Games::class)]
#[CoversClass(BotDetector::class)]
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
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(Hooks::class)]
#[CoversClass(WebHooks::class)]
#[CoversClass(Page::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(Router::class)]
#[CoversFunction('Jax\routes')]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(Session::class)]
#[CoversClass(Spin::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(User::class)]
final class EasterEggsTest extends FeatureTestCase
{
    public function testAsteroids(): void
    {
        $page = $this->go(new Request(get: ['path' => '/asteroids'], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);

        static::assertSame('loadscript', $json[0][0]);
    }

    public function testKatamari(): void
    {
        $page = $this->go(new Request(get: ['path' => '/katamari'], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);

        static::assertSame('loadscript', $json[0][0]);
    }

    public function testEarthbound(): void
    {
        $page = $this->go(new Request(get: ['path' => '/katamari'], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);

        static::assertSame('loadscript', $json[0][0]);
    }

    public function testRainbow(): void
    {
        $page = $this->go(new Request(get: ['path' => '/katamari'], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);

        static::assertSame('loadscript', $json[0][0]);
    }

    public function testSolitaire(): void
    {
        $page = $this->go(new Request(get: ['path' => '/solitaire'], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);

        static::assertSame('loadscript', $json[0][0]);
    }

    public function testTardis(): void
    {
        $page = $this->go(new Request(get: ['path' => '/tardis'], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);

        static::assertSame('script', $json[0][0]);
    }

    public function testSpin(): void
    {
        $page = $this->go(new Request(get: ['path' => '/spin'], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);

        static::assertSame('script', $json[0][0]);
    }
}
