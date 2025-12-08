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
use Jax\Database;
use Jax\DatabaseUtils;
use Jax\DatabaseUtils\SQLite;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Model;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Page;
use Jax\Page\Asteroids;
use Jax\Page\Earthbound;
use Jax\Page\Katamari;
use Jax\Page\Rainbow;
use Jax\Page\Solitaire;
use Jax\Page\Tardis;
use Jax\TextRules;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversClass;
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
#[CoversClass(Model::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(Page::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(Router::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(Session::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(User::class)]
final class EasterEggsTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testAsteroids(): void
    {
        $page = $this->go(new Request(
            get: ['act' => 'asteroids'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertEquals($json[0][0], 'loadscript');
    }

    public function testKatamari(): void
    {
        $page = $this->go(new Request(
            get: ['act' => 'katamari'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertEquals($json[0][0], 'loadscript');
    }

    public function testEarthbound(): void
    {
        $page = $this->go(new Request(
            get: ['act' => 'katamari'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertEquals($json[0][0], 'loadscript');
    }

    public function testRainbow(): void
    {
        $page = $this->go(new Request(
            get: ['act' => 'katamari'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertEquals($json[0][0], 'loadscript');
    }

    public function testSolitaire(): void
    {
        $page = $this->go(new Request(
            get: ['act' => 'solitaire'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertEquals($json[0][0], 'loadscript');
    }

    public function testTardis(): void
    {
        $page = $this->go(new Request(
            get: ['act' => 'tardis'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertEquals($json[0][0], 'script');
    }
}
