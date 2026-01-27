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
use Jax\ContactDetails;
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
use Jax\Models\Activity as ModelsActivity;
use Jax\Models\ProfileComment;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Modules\WebHooks;
use Jax\Page;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\Routes\Badges;
use Jax\Routes\UserProfile;
use Jax\Routes\UserProfile\Activity;
use Jax\Routes\UserProfile\Comments;
use Jax\Routes\UserProfile\ProfileTabs;
use Jax\RSSFeed;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

use function array_find;
use function json_decode;

/**
 * @internal
 */
#[CoversClass(Activity::class)]
#[CoversClass(App::class)]
#[CoversClass(FileSystem::class)]
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
#[CoversClass(Lodash::class)]
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
#[CoversClass(Hooks::class)]
#[CoversClass(WebHooks::class)]
#[CoversClass(SQLite::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(User::class)]
#[CoversClass(UserProfile::class)]
final class ProfileTest extends FeatureTestCase
{
    public function testViewMissingUser(): void
    {
        $page = $this->go('/profile/5');

        DOMAssert::assertSelectEquals(
            '#page .error',
            "Sorry, this user doesn't exist.",
            1,
            $page,
        );
    }

    public function testViewUserProfile(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/profile/1');

        // Breadcrumbs
        DOMAssert::assertSelectEquals(
            '#path li a',
            'Example Forums',
            1,
            $page,
        );
        DOMAssert::assertSelectEquals(
            '#path li a',
            "Admin's profile",
            1,
            $page,
        );

        DOMAssert::assertSelectEquals(
            '.leftbar .username .moderate',
            'Edit',
            1,
            $page,
        );
        DOMAssert::assertSelectEquals('.leftbar .username', 'Admin', 1, $page);

        DOMAssert::assertSelectEquals(
            '#pfbox',
            'This user has yet to do anything noteworthy!',
            1,
            $page,
        );
    }

    public function testViewProfileRSSFeed(): void
    {
        $this->insertActivities();

        $this->actingAs('admin');

        $page = $this->go('/profile/1/activity?fmt=RSS');

        self::assertStringContainsString(
            "<title>Admin's recent activity</title>",
            $page,
        );
        self::assertStringContainsString(
            '<link>https://jaxboards.com/profile/1</link>',
            $page,
        );
        self::assertStringContainsString(
            '<description>Admin made friends with Admin</description>',
            $page,
        );
        self::assertStringContainsString(
            '<description>Prince is now known as Admin</description>',
            $page,
        );
        self::assertStringContainsString(
            '<description>Admin posted in topic Post</description>',
            $page,
        );
        self::assertStringContainsString(
            '<description>Admin created new topic Topic</description>',
            $page,
        );
        self::assertStringContainsString(
            "<description>Admin commented on Admin's profile</description>",
            $page,
        );
    }

    public function testViewUserProfileActivityNoActivity(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/profile/1/activity');

        DOMAssert::assertSelectEquals(
            '#pfbox',
            'This user has yet to do anything noteworthy!',
            1,
            $page,
        );
    }

    public function testViewUserProfileActivitySomeActivity(): void
    {
        $this->insertActivities();

        $this->actingAs('admin');

        $page = $this->go('/profile/1/activity');

        DOMAssert::assertSelectRegExp(
            '.buddy_add',
            '/Admin.*made friends with.*Admin/',
            1,
            $page,
        );
        DOMAssert::assertSelectRegExp(
            '.profile_name_change',
            '/Prince.*is now known as.*Admin/',
            1,
            $page,
        );
        DOMAssert::assertSelectRegExp(
            '.new_post',
            '/Admin.*posted in topic.*Post/',
            1,
            $page,
        );
        DOMAssert::assertSelectRegExp(
            '.new_topic',
            '/Admin.*created new topic.*Topic/',
            1,
            $page,
        );
        DOMAssert::assertSelectRegExp(
            '.profile_comment',
            '/Admin.*commented on.*Admin/',
            1,
            $page,
        );
    }

    public function testViewUserProfilePosts(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/profile/1/posts');

        DOMAssert::assertSelectEquals(
            '#pfbox .post a',
            'Welcome to Jaxboards!',
            1,
            $page,
        );
    }

    public function testViewUserProfileTopics(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/profile/1/topics');

        DOMAssert::assertSelectEquals(
            '#pfbox a',
            'Welcome to Jaxboards!',
            1,
            $page,
        );
    }

    public function testViewUserProfileComments(): void
    {
        $comment = new ProfileComment();
        $comment->to = 1;
        $comment->from = 1;
        $comment->comment = 'This is a profile comment.';
        $comment->date = $this->container->get(Database::class)->datetime();
        $comment->insert();

        $this->actingAs('admin');

        $page = $this->go('/profile/1/comments');

        DOMAssert::assertSelectCount('textarea[name=comment]', 1, $page);
        DOMAssert::assertSelectRegExp(
            '.commenttext',
            '/This is a profile comment./',
            1,
            $page,
        );
    }

    public function testViewUserProfileCommentsAddCommentAsAdmin(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['path' => 'profile/1/comments'],
            post: ['comment' => 'This is a profile comment.'],
        ));

        DOMAssert::assertSelectRegExp(
            '.comment .username',
            '/Admin/',
            2,
            $page,
        );
        DOMAssert::assertSelectRegExp(
            '.commenttext',
            '/This is a profile comment./',
            1,
            $page,
        );
    }

    public function testViewUserProfileFriends(): void
    {
        $this->actingAs('admin', ['friends' => '1']);

        $page = $this->go('/profile/1/friends');

        DOMAssert::assertSelectEquals(
            '.contacts .contact .user1',
            'Admin',
            1,
            $page,
        );
    }

    public function testViewUserProfileAbout(): void
    {
        $aboutText = "I'm a strong single admin that don't need no members";
        $this->actingAs('admin', ['friends' => '1', 'about' => $aboutText]);

        $page = $this->go('/profile/1/about');

        DOMAssert::assertSelectRegExp('#pfbox', '/strong single/', 1, $page);
        DOMAssert::assertSelectRegExp('#pfbox', '/I like tacos/', 1, $page);
    }

    public function testViewUserProfileContactCard(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['path' => 'profile/1'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        self::assertContains(['preventNavigation'], $json);

        $window = array_find(
            $json,
            static fn($cmd): bool => $cmd[0] === 'window',
        );
        self::assertEquals('Contact Card', $window[1]['title']);
        self::assertStringContainsString(
            'Add Contact',
            $window[1]['content'],
        );
    }

    private function insertActivities(): void
    {
        $database = $this->container->get(Database::class);

        $activity = new ModelsActivity();
        $activity->uid = 1;
        $activity->type = 'profile_comment';
        $activity->affectedUser = 1;
        $activity->date = $database->datetime();
        $activity->insert();

        $activity = new ModelsActivity();
        $activity->uid = 1;
        $activity->tid = 1;
        $activity->type = 'new_topic';
        $activity->arg1 = 'Topic';
        $activity->date = $database->datetime();
        $activity->insert();

        $activity = new ModelsActivity();
        $activity->uid = 1;
        $activity->tid = 1;
        $activity->pid = 1;
        $activity->type = 'new_post';
        $activity->arg1 = 'Post';
        $activity->date = $database->datetime();
        $activity->insert();

        $activity = new ModelsActivity();
        $activity->uid = 1;
        $activity->type = 'profile_name_change';
        $activity->arg1 = 'Prince';
        $activity->arg2 = 'Admin';
        $activity->date = $database->datetime();
        $activity->insert();

        $activity = new ModelsActivity();
        $activity->uid = 1;
        $activity->type = 'buddy_add';
        $activity->affectedUser = 1;
        $activity->date = $database->datetime();
        $activity->insert();
    }
}
