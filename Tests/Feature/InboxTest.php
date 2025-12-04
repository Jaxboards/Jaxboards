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
use Jax\Page\TextRules;
use Jax\Page\UCP;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;
use Jax\Models\Message;
use Jax\Page\UCP\Inbox;
use Jax\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

#[CoversClass(Inbox::class)]
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
#[CoversClass(TextRules::class)]
#[CoversClass(UCP::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(Router::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(Session::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(User::class)]
#[CoversFunction('Jax\pathjoin')]
final class InboxTest extends FeatureTestCase
{
    public function testInboxNoMessages(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=ucp&what=inbox');

        $this->assertStringContainsString('No messages.', $page);
    }

    public function testInboxWithMessage(): void
    {
        $this->insertMessage();

        $this->actingAs('admin');

        $page = $this->go('?act=ucp&what=inbox');

        DOMAssert::assertSelectEquals('.unread a[href^="?act=ucp&what=inbox&view=1"]', 'Test Message', 1, $page);
    }

    public function testInboxViewMessage(): void
    {
        $this->insertMessage();

        $this->actingAs('admin');

        $page = $this->go('?act=ucp&what=inbox&view=1');

        DOMAssert::assertSelectEquals('.message', 'This is a test message.', 1, $page);
    }

    public function testInboxReplyMessage(): void
    {
        $this->insertMessage();

        $this->actingAs('admin');

        $page = $this->go('?act=ucp&what=inbox&page=Reply&messageid=1');

        DOMAssert::assertSelectRegExp('#message', '/\[quote=Admin\]This is a test message.\[\/quote\]/', 1, $page);
    }

    public function testInboxDeleteMessage(): void
    {
        $this->insertMessage();

        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['act' => 'ucp', 'what' => 'inbox'],
            post: ['dmessage' => ['1']],
        ));

        $this->assertStringContainsString('No messages.', $page);
    }

    private function insertMessage(): void
    {
        $message = new Message();
        $message->from = 1;
        $message->to = 1;
        $message->title = 'Test Message';
        $message->message = 'This is a test message.';
        $message->insert();
    }
}
