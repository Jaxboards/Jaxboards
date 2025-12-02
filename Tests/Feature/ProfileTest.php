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
use Jax\Database;
use Jax\DatabaseUtils;
use Jax\DatabaseUtils\SQLite;
use Jax\Date;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Model;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Page;
use Jax\Page\Badges;
use Jax\Page\TextRules;
use Jax\Page\UserProfile;
use Jax\Page\UserProfile\Activity;
use Jax\Page\UserProfile\Comments;
use Jax\Page\UserProfile\ProfileTabs;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\RSSFeed;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
#[CoversClass(Activity::class)]
#[CoversClass(App::class)]
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
#[CoversClass(ForeignKey::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Key::class)]
#[CoversClass(Model::class)]
#[CoversClass(Page::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(ProfileTabs::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(Router::class)]
#[CoversClass(RSSFeed::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(Session::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(SQLite::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(User::class)]
#[CoversClass(UserProfile::class)]
#[CoversFunction('Jax\pathjoin')]
final class ProfileTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testViewMissingUser(): void
    {
        $page = $this->go('?act=vu5');

        DOMAssert::assertSelectEquals('#page .error', "Sorry, this user doesn't exist.", 1, $page);
    }

    public function testViewUserProfileAsAdmin(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=vu1');

        // Breadcrumbs
        DOMAssert::assertSelectEquals('#path li a', 'Example Forums', 1, $page);
        DOMAssert::assertSelectEquals('#path li a', "Admin's profile", 1, $page);

        DOMAssert::assertSelectEquals('.leftbar .username .moderate', 'Edit', 1, $page);
        DOMAssert::assertSelectEquals('.leftbar .username', 'Admin', 1, $page);

        DOMAssert::assertSelectEquals('#pfbox', 'This user has yet to do anything noteworthy!', 1, $page);
    }

    public function testViewProfileRSSFeed(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=vu1&page=activity&fmt=RSS');

        $this->assertStringContainsString("<title>Admin's recent activity</title>", $page);
        $this->assertStringContainsString('<link>//example.com?act=vu1</link>', $page);
    }
}
