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
use Jax\Date;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\Hooks;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Lodash;
use Jax\Models\Activity;
use Jax\Models\Member;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Modules\WebHooks;
use Jax\Page;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\Routes\BuddyList;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use Jax\UsersOnline;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

use function array_find;
use function json_decode;

/**
 * @internal
 */
#[CoversClass(App::class)]
#[CoversClass(FileSystem::class)]
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
#[CoversClass(Date::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Lodash::class)]
#[CoversClass(Model::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(Hooks::class)]
#[CoversClass(WebHooks::class)]
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
final class BuddyListTest extends FeatureTestCase
{
    public function testBuddyList(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(get: ['path' => '/buddylist'], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);

        static::assertContainsEquals(['preventNavigation'], $json);

        $window = array_find($json, static fn($cmd): bool => $cmd[0] === 'window');
        static::assertSame('buddylist', $window[1]['id']);
        static::assertSame('Buddies', $window[1]['title']);
    }

    public function testAddBuddy(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(get: ['path' => '/buddylist', 'add' => '1'], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);

        static::assertContainsEquals(['preventNavigation'], $json);

        $window = array_find($json, static fn($cmd): bool => $cmd[0] === 'window');
        static::assertSame('buddylist', $window[1]['id']);
        static::assertSame('Buddies', $window[1]['title']);
        DOMAssert::assertSelectEquals('.contact .name', 'Admin', 1, $window[1]['content']);

        $activity = Activity::selectOne();
        static::assertSame('buddy_add', $activity->type);
        static::assertSame(1, $activity->uid);
        static::assertSame(1, $activity->affectedUser);
    }

    public function testRemoveBuddy(): void
    {
        $this->actingAs('admin', ['friends' => '1']);

        $page = $this->go(new Request(get: ['path' => '/buddylist', 'remove' => '1'], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);

        static::assertContainsEquals(['preventNavigation'], $json);

        $window = array_find($json, static fn($cmd): bool => $cmd[0] === 'window');
        static::assertSame('buddylist', $window[1]['id']);
        static::assertSame('Buddies', $window[1]['title']);
        DOMAssert::assertSelectEquals('.contact .name', 'Admin', 0, $window[1]['content']);

        $member = Member::selectOne(1);
        static::assertSame('', $member->friends);
    }

    public function testBlock(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(get: ['path' => '/buddylist', 'block' => '1'], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);

        static::assertContainsEquals(['preventNavigation'], $json);

        $window = array_find($json, static fn($cmd): bool => $cmd[0] === 'window');
        static::assertSame('buddylist', $window[1]['id']);
        static::assertSame('Buddies', $window[1]['title']);
        DOMAssert::assertSelectEquals('.contact .name', 'Admin', 1, $window[1]['content']);

        DOMAssert::assertSelectCount('.contact.blocked', 1, $window[1]['content']);

        $member = Member::selectOne(1);
        static::assertSame('1', $member->enemies);
    }

    public function testUnblock(): void
    {
        $this->actingAs('admin', ['enemies' => '1']);

        $page = $this->go(new Request(get: ['path' => '/buddylist', 'unblock' => '1'], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);

        static::assertContainsEquals(['preventNavigation'], $json);

        $window = array_find($json, static fn($cmd): bool => $cmd[0] === 'window');
        static::assertSame('buddylist', $window[1]['id']);
        static::assertSame('Buddies', $window[1]['title']);
        static::assertStringNotContainsString('Admin', $window[1]['content']);

        DOMAssert::assertSelectCount('.contact.blocked', 0, $window[1]['content']);

        $member = Member::selectOne(1);
        static::assertSame('', $member->enemies);
    }
}
