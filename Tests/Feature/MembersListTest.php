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
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Modules\WebHooks;
use Jax\Page;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\Routes\Members;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
#[CoversClass(App::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(Games::class)]
#[CoversClass(BotDetector::class)]
#[CoversClass(Column::class)]
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
#[CoversClass(Members::class)]
#[CoversClass(Lodash::class)]
#[CoversClass(Model::class)]
#[CoversClass(Page::class)]
#[CoversClass(PrivateMessage::class)]
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
#[CoversClass(User::class)]
final class MembersListTest extends FeatureTestCase
{
    public function testViewMembers(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/members');

        // Breadcrumbs
        DOMAssert::assertSelectEquals('#path a', 'Example Forums', 1, $page);
        DOMAssert::assertSelectEquals('#path a', 'Members', 1, $page);

        DOMAssert::assertSelectEquals('#memberlist .title', 'Members', 1, $page);
        DOMAssert::assertSelectEquals('#memberlist tr:nth-child(2) td:nth-child(2) .user1', 'Admin', 1, $page);
        DOMAssert::assertSelectEquals('#memberlist tr:nth-child(2) td:nth-child(3)', '#1', 1, $page);
        DOMAssert::assertSelectEquals('#memberlist tr:nth-child(2) td:nth-child(4)', '0', 1, $page);
        DOMAssert::assertSelectEquals('#memberlist tr:nth-child(2) td:nth-child(5)', 'a minute ago', 1, $page);
    }
}
