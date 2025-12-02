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
use Jax\Page\Topic\Poll;
use Jax\Page\Topic\Reactions;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;
use Jax\UsersOnline;
use Jax\Page\Topic;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
#[CoversClass(App::class)]
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
#[CoversFunction('Jax\pathjoin')]
final class TopicTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testViewTopicAsAdmin(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=vt1');

        // Breadcrumbs
        DOMAssert::assertSelectEquals('#path li a', 'Example Forums', 1, $page);
        DOMAssert::assertSelectEquals('#path li a', 'Category', 1, $page);
        DOMAssert::assertSelectEquals('#path li a', 'Forum', 2, $page);
        DOMAssert::assertSelectEquals('#path li a', 'Welcome to Jaxboards!', 1, $page);

        DOMAssert::assertSelectRegExp('#page .box .title', '/Welcome to Jaxboards!, Your support is appreciated./', 1, $page);

        DOMAssert::assertSelectEquals('#pid_1 .username', 'Admin', 1, $page);
        DOMAssert::assertSelectEquals('#pid_1 .signature', 'I like tacos', 1, $page);

        DOMAssert::assertSelectRegExp('#pid_1 .post_content', '/only a matter of time/', 1, $page);

        DOMAssert::assertSelectRegExp('#pid_1 .userstats', '/Status: Online!/', 1, $page);
        DOMAssert::assertSelectRegExp('#pid_1 .userstats', '/Group: Admin/', 1, $page);
        DOMAssert::assertSelectRegExp('#pid_1 .userstats', '/Member: #1/', 1, $page);
    }
}
