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
use Jax\FileSystem;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Model;
use Jax\Models\Message;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Page;
use Jax\Page\TextRules;
use Jax\Page\UCP;
use Jax\Page\UCP\Inbox;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
#[CoversClass(Inbox::class)]
#[CoversClass(App::class)]
#[CoversClass(FileSystem::class)]
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

    public function testInboxComposeMessage(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['act' => 'ucp', 'what' => 'inbox', 'view' => 'compose'],
            post: [
                'submit' => '1',
                'mid' => '1',
                'title' => 'Hello there',
                'message' => 'How have you been?',
            ],
        ));

        DOMAssert::assertSelectEquals('#ucppage', 'Message successfully delivered.', 1, $page);
        $message = Message::selectOne(1);
        $this->assertEquals('Hello there', $message->title);
        $this->assertEquals('How have you been?', $message->message);
        $this->assertEquals(1, $message->from);
        $this->assertEquals(1, $message->to);
    }

    public function testInboxFlagMessage(): void
    {
        $this->insertMessage();

        $this->actingAs('admin');

        $page = $this->go('?act=ucp&what=inbox&flag=1&tog=1');

        $this->assertRedirect('?act=ucp&what=inbox', $page);

        $message = Message::selectOne(1);
        $this->assertEquals(1, $message->flag);
    }

    public function testInboxUnflagMessage(): void
    {
        $this->insertMessage(['flag' => 1]);

        $this->actingAs('admin');

        $page = $this->go('?act=ucp&what=inbox&flag=1&tog=0');

        $this->assertRedirect('?act=ucp&what=inbox', $page);

        $message = Message::selectOne(1);
        $this->assertEquals(0, $message->flag);
    }

    private function insertMessage(?array $messageProperties = []): void
    {
        $message = new Message([
            'from' => 1,
            'to' => 1,
            'title' => 'Test Message',
            'message' => 'This is a test message.',
            ...$messageProperties,
        ]);

        $message->insert();
    }
}
