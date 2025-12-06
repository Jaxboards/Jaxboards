<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\FileUtils;
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
use Jax\Page\Forum;
use Jax\Page\TextRules;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
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
#[CoversClass(App::class)]
#[CoversClass(FileUtils::class)]
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
#[CoversClass(Forum::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Key::class)]
#[CoversClass(Model::class)]
#[CoversClass(Page::class)]
#[CoversClass(PrivateMessage::class)]
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
#[CoversClass(User::class)]
#[CoversFunction('Jax\pathjoin')]
final class ForumIndexTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testViewForumIndexAsAdmin(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=vf1');

        DOMAssert::assertSelectEquals('#fid_1_listing .title', 'Forum', 1, $page, 'Forum title');
        DOMAssert::assertSelectEquals('#fr_1 .topic', 'Welcome to Jaxboards!', 1, $page, 'Topic Title');
        DOMAssert::assertSelectEquals('#fr_1 .topic', 'Your support is appreciated.', 1, $page, 'Topic Description');
        DOMAssert::assertSelectEquals('#fr_1 .item_1 .user1', 'Admin', 1, $page, 'Topic Author');
        DOMAssert::assertSelectEquals('#fr_1 .last_post .autodate', 'a minute ago', 1, $page, 'Last Post Date');
        DOMAssert::assertSelectEquals('#fr_1 .last_post .user1', 'Admin', 1, $page, 'Last Post Author');
    }
}
