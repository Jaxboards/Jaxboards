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
use Jax\Date;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Model;
use Jax\Models\Activity;
use Jax\Models\Member;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Page;
use Jax\Page\BuddyList;
use Jax\Page\TextRules;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;
use Jax\UsersOnline;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use Tests\FeatureTestCase;

use function array_find;
use function json_decode;

/**
 * @internal
 */
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
#[CoversClass(Date::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Model::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(Page::class)]
#[CoversClass(BuddyList::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(Router::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(Session::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(User::class)]
#[CoversClass(UsersOnline::class)]
#[CoversFunction('Jax\pathjoin')]
final class BuddyListTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testBuddyList(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['act' => 'buddylist'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['softurl'], $json);

        $window = array_find($json, static fn($cmd): bool => $cmd[0] === 'window');
        $this->assertEquals('buddylist', $window[1]['id']);
        $this->assertEquals('Buddies', $window[1]['title']);
    }

    public function testAddBuddy(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['act' => 'buddylist', 'add' => '1'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['softurl'], $json);

        $window = array_find($json, static fn($cmd): bool => $cmd[0] === 'window');
        $this->assertEquals('buddylist', $window[1]['id']);
        $this->assertEquals('Buddies', $window[1]['title']);
        $this->assertStringContainsString('Admin', $window[1]['content']);

        $activity = Activity::selectOne();
        $this->assertEquals('buddy_add', $activity->type);
        $this->assertEquals(1, $activity->uid);
        $this->assertEquals(1, $activity->affectedUser);
    }

    public function testRemoveBuddy(): void
    {
        $this->actingAs('admin', ['friends' => '1']);

        $page = $this->go(new Request(
            get: ['act' => 'buddylist', 'remove' => '1'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['softurl'], $json);

        $window = array_find($json, static fn($cmd): bool => $cmd[0] === 'window');
        $this->assertEquals('buddylist', $window[1]['id']);
        $this->assertEquals('Buddies', $window[1]['title']);
        $this->assertStringNotContainsString('Admin', $window[1]['content']);

        $member = Member::selectOne(1);
        $this->assertEquals($member->friends, '');
    }
}
