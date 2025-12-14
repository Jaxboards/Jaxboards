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
use Jax\IPAddress;
use Jax\Jax;
use Jax\Models\Session as ModelsSession;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Page;
use Jax\Page\Badges;
use Jax\Page\Topic;
use Jax\Page\Topic\Poll;
use Jax\Page\Topic\Reactions;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\RSSFeed;
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
#[CoversClass(Badges::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(BotDetector::class)]
#[CoversClass(Column::class)]
#[CoversClass(Config::class)]
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
#[CoversClass(Poll::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Reactions::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(Router::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(Session::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(SQLite::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(Topic::class)]
#[CoversClass(User::class)]
#[CoversClass(UsersOnline::class)]
#[CoversClass(RSSFeed::class)]
final class TopicTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->insertBotViewingTopic();
    }

    public function testViewTopicAsAdmin(): void
    {
        $this->actingAs('admin', ['sig' => 'I like tacos']);

        $page = $this->go('/topic/1');

        // Breadcrumbs
        DOMAssert::assertSelectEquals('#path li a', 'Example Forums', 1, $page);
        DOMAssert::assertSelectEquals('#path li a', 'Category', 1, $page);
        DOMAssert::assertSelectEquals('#path li a', 'Forum', 2, $page);
        DOMAssert::assertSelectEquals('#path li a', 'Welcome to Jaxboards!', 1, $page);

        DOMAssert::assertSelectRegExp(
            '#page .box .title',
            '/Welcome to Jaxboards!, Your support is appreciated./',
            1,
            $page,
        );

        DOMAssert::assertSelectEquals('#pid_1 .username', 'Admin', 1, $page);
        DOMAssert::assertSelectEquals('#pid_1 .signature', 'I like tacos', 1, $page);

        DOMAssert::assertSelectRegExp('#pid_1 .post_content', '/only a matter of time/', 1, $page);

        DOMAssert::assertSelectRegExp('#pid_1 .userstats', '/Status: Online!/', 1, $page);
        DOMAssert::assertSelectRegExp('#pid_1 .userstats', '/Group: Admin/', 1, $page);
        DOMAssert::assertSelectRegExp('#pid_1 .userstats', '/Member: #1/', 1, $page);

        DOMAssert::assertSelectEquals('#statusers .userGoogleBot', 'GoogleBot', 1, $page);
    }

    public function testTopicUpdate(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['path' => 'topic/1'],
            server: ['HTTP_X_JSACCESS' => JSAccess::UPDATING->value],
        ));

        $json = json_decode($page, true);

        // TODO: Test that there are new posts
        $this->assertEquals($json[0][0], 'onlinelist');
        $this->assertEquals($json[0][1][0][0], 'GoogleBot');
    }

    public function testQuickReplyWindow(): void
    {
        $this->actingAs('admin', sessionOverrides: ['multiquote' => 1]);

        $page = $this->go(new Request(
            get: ['path' => 'topic/1', 'qreply' => '1'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['softurl'], $json);
        $window = array_find($json, static fn($item): bool => $item[0] === 'window');

        DOMAssert::assertSelectRegExp(
            '.topic-reply-form textarea[name="postdata"]',
            '/\[quote=Admin\]Now,/',
            1,
            $window[1]['content'],
        );
    }

    public function testTopicRSSFeed(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/topic/1?fmt=RSS');

        DOMAssert::assertSelectEquals('title', 'Welcome to Jaxboards!', 1, $page);
        DOMAssert::assertSelectRegExp('item description', '/only a matter of time/', 1, $page);
    }

    private function insertBotViewingTopic(): void
    {
        $database = $this->container->get(Database::class);
        // Insert a bot viewing the topic
        $session = new ModelsSession();
        $session->id = 'GoogleBot';
        $session->lastAction = $database->datetime();
        $session->lastUpdate = $database->datetime();
        $session->isBot = 1;
        $session->uid = 1;
        $session->location = 'vt1';
        $session->insert();
    }
}
