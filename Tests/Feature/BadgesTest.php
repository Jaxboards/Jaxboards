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
use Jax\ContactDetails;
use Jax\Database\Adapters\SQLite;
use Jax\Database\Database;
use Jax\Database\Utils as DatabaseUtils;
use Jax\Date;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\Hooks;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Lodash;
use Jax\Models\Badge;
use Jax\Models\BadgeAssociation;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Modules\WebHooks;
use Jax\Page;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\Routes\Badges;
use Jax\Routes\Topic;
use Jax\Routes\Topic\Poll;
use Jax\Routes\Topic\Reactions;
use Jax\Routes\UserProfile;
use Jax\Routes\UserProfile\Activity;
use Jax\Routes\UserProfile\Comments;
use Jax\Routes\UserProfile\ProfileTabs;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use Jax\UsersOnline;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
#[CoversClass(Activity::class)]
#[CoversClass(App::class)]
#[CoversClass(Badge::class)]
#[CoversClass(Badges::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(BotDetector::class)]
#[CoversClass(Column::class)]
#[CoversClass(Comments::class)]
#[CoversClass(Config::class)]
#[CoversClass(ContactDetails::class)]
#[CoversClass(Database::class)]
#[CoversClass(DatabaseUtils::class)]
#[CoversClass(Date::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Key::class)]
#[CoversClass(Lodash::class)]
#[CoversClass(Page::class)]
#[CoversClass(Poll::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(ProfileTabs::class)]
#[CoversClass(Reactions::class)]
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
#[CoversClass(Topic::class)]
#[CoversClass(User::class)]
#[CoversClass(UserProfile::class)]
#[CoversClass(UsersOnline::class)]
final class BadgesTest extends FeatureTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testBadgesInProfileNoBadgesYet(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/profile/1/badges');

        DOMAssert::assertSelectEquals('#pfbox', 'No badges yet!', 1, $page);
    }

    public function testBadgesInProfileHasBadges(): void
    {
        $this->actingAs('admin');
        $this->awardBadgeToAdmin();

        $page = $this->go('/profile/1/badges');

        DOMAssert::assertSelectCount(
            '.badge .badge-image img[src=imagePath]',
            1,
            $page,
        );
        DOMAssert::assertSelectEquals(
            '.badge .badge-title',
            'Badge Title',
            1,
            $page,
        );
        DOMAssert::assertSelectEquals(
            '.badge .description',
            'Badge Description',
            1,
            $page,
        );
        DOMAssert::assertSelectRegExp(
            '.badge .reason',
            '/Award Reason/',
            1,
            $page,
        );
        DOMAssert::assertSelectEquals(
            '.badge .award-date .autodate',
            'a minute ago',
            1,
            $page,
        );
    }

    public function testBadgesInTopicView(): void
    {
        $this->actingAs('admin');
        $this->awardBadgeToAdmin();

        $page = $this->go('/topic/1');

        DOMAssert::assertSelectCount(
            '#pid_1 .badges img[src="imagePath"][title="Badge Title"]',
            1,
            $page,
        );
    }

    public function testBadgesListView(): void
    {
        $this->actingAs('admin');
        $this->awardBadgeToAdmin();

        $page = $this->go('/badges?badgeId=1');

        DOMAssert::assertSelectCount(
            '.badge-image img[src="imagePath"][title="Badge Title"]',
            1,
            $page,
        );
        DOMAssert::assertSelectEquals('.badge-title', 'Badge Title', 1, $page);
        DOMAssert::assertSelectEquals(
            '.badge .description',
            'Badge Description',
            1,
            $page,
        );
        DOMAssert::assertSelectEquals('.badges .user1', 'Admin', 1, $page);
        DOMAssert::assertSelectEquals(
            '.badges .reason',
            'Award Reason',
            1,
            $page,
        );
        DOMAssert::assertSelectEquals(
            '.badges .award-date .autodate',
            'a minute ago',
            1,
            $page,
        );
    }

    private function awardBadgeToAdmin(): void
    {
        $badge = new Badge();
        $badge->badgeTitle = 'Badge Title';
        $badge->description = 'Badge Description';
        $badge->imagePath = 'imagePath';
        $badge->insert();

        $database = $this->container->get(Database::class);
        $badgeAssociation = new BadgeAssociation();
        $badgeAssociation->awardDate = $database->datetime();
        $badgeAssociation->reason = 'Award Reason';
        $badgeAssociation->user = 1;
        $badgeAssociation->badge = $badge->id;
        $badgeAssociation->insert();
    }
}
