<?php

namespace Tests\Feature;

use Jax\Models\Message;
use Jax\Request;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

class InboxTest extends FeatureTestCase
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
