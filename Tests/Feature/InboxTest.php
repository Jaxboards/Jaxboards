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
use Jax\Mailer;
use Jax\Models\Message;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Modules\WebHooks;
use Jax\Page;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\Routes\UCP;
use Jax\Routes\UCP\Inbox;
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
#[CoversClass(Inbox::class)]
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
#[CoversClass(Mailer::class)]
#[CoversClass(Model::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(Hooks::class)]
#[CoversClass(WebHooks::class)]
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

        $page = $this->go('/ucp/inbox');

        static::assertStringContainsString('No messages.', $page);
    }

    public function testInboxWithMessage(): void
    {
        $this->insertMessage();

        $this->actingAs('admin');

        $page = $this->go('/ucp/inbox');
        $url = $this->container->get(Router::class)->url(
            'inbox',
            ['view' => '1'],
        );

        DOMAssert::assertSelectEquals(
            ".unread a[href^='{$url}']",
            'Test Message',
            1,
            $page,
        );
    }

    public function testInboxViewMessage(): void
    {
        $this->insertMessage();

        $this->actingAs('admin');

        $page = $this->go('/ucp/inbox?view=1');

        DOMAssert::assertSelectEquals(
            '.message',
            'This is a test message.',
            1,
            $page,
        );
    }

    public function testInboxReplyMessage(): void
    {
        $this->insertMessage();

        $this->actingAs('admin');

        $page = $this->go('/ucp/inbox?page=Reply&messageid=1');

        DOMAssert::assertSelectRegExp(
            '#message',
            '/\[quote=Admin\]This is a test message.\[\/quote\]/',
            1,
            $page,
        );
    }

    public function testInboxDeleteMessage(): void
    {
        $this->insertMessage();

        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['path' => '/ucp/inbox'],
            post: ['dmessage' => ['1']],
        ));

        static::assertStringContainsString('No messages.', $page);
    }

    public function testInboxComposeMessage(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['path' => '/ucp/inbox', 'view' => 'compose'],
            post: [
                'submit' => '1',
                'mid' => '1',
                'title' => 'Hello there',
                'message' => 'How have you been?',
            ],
        ));

        DOMAssert::assertSelectEquals(
            '#ucppage',
            'Message successfully delivered.',
            1,
            $page,
        );
        $message = Message::selectOne(1);
        static::assertSame('Hello there', $message->title);
        static::assertSame('How have you been?', $message->message);
        static::assertSame(1, $message->from);
        static::assertSame(1, $message->to);
    }

    public function testInboxFlagMessage(): void
    {
        $this->insertMessage();

        $this->actingAs('admin');

        $page = $this->go('/ucp/inbox?flag=1&tog=1');

        $this->assertRedirect('inbox', [], $page);

        $message = Message::selectOne(1);
        static::assertSame(1, $message->flag);
    }

    public function testInboxUnflagMessage(): void
    {
        $this->insertMessage(['flag' => 1]);

        $this->actingAs('admin');

        $page = $this->go('/ucp/inbox?flag=1&tog=0');

        $this->assertRedirect('inbox', [], $page);

        $message = Message::selectOne(1);
        static::assertSame(0, $message->flag);
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
