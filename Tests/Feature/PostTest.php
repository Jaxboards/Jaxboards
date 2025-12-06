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
use Jax\FileUtils;
use Jax\Hooks;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Model;
use Jax\Models\Post as ModelsPost;
use Jax\Models\Topic;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Page;
use Jax\Page\Post;
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
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
#[CoversClass(Post::class)]
#[CoversClass(App::class)]
#[CoversClass(FileUtils::class)]
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
#[CoversClass(Hooks::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Model::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(Page::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(Router::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(Session::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(User::class)]
final class PostTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testPostNewTopic(): void
    {
        $this->actingAs('member');

        $page = $this->go('?act=post&fid=1');

        DOMAssert::assertSelectCount('input[name=ttitle]', 1, $page);
        DOMAssert::assertSelectCount('input[name=tdesc]', 1, $page);
        DOMAssert::assertSelectCount('textarea[name=postdata]', 1, $page);
    }

    public function testPostNewTopicSubmit(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['act' => 'post', 'fid' => '1'],
            post: [
                'act' => 'post',
                'how' => 'newtopic',
                'fid' => '1',
                'tid' => '',
                'ttitle' => 'Topic title',
                'tdesc' => 'Topic description',
                'postdata' => 'Post data',
                'submit' => 'Post New Topic',
            ],
        ));

        $this->assertRedirect('?act=vt2&getlast=1', $page);
        $topic = Topic::selectOne(2);
        $post = ModelsPost::selectOne(2);

        $this->assertEquals('Topic title', $topic->title);
        $this->assertEquals('Topic description', $topic->subtitle);
        $this->assertEquals('Post data', $post->post);
    }

    public function testPostReply(): void
    {
        $this->actingAs('member');

        $page = $this->go('?act=post&tid=1');

        DOMAssert::assertSelectCount('input[name=ttitle]', 0, $page);
        DOMAssert::assertSelectCount('input[name=tdesc]', 0, $page);
        DOMAssert::assertSelectCount('textarea[name=postdata]', 1, $page);
    }

    public function testPostReplySubmit(): void
    {
        $this->actingAs('member');

        // Create a callback to test the post hook
        $postHookCalled = true;
        $postHookPost = null;
        $this->container->get(Hooks::class)
            ->addListener('post', static function ($postHookPostArg) use (&$postHookCalled, &$postHookPost): void {
                $postHookCalled = true;
                $postHookPost = $postHookPostArg;
            })
        ;


        $page = $this->go(new Request(
            get: ['act' => 'post', 'tid' => '1'],
            post: [
                'act' => 'post',
                'how' => 'fullpost',
                'fid' => '',
                'tid' => '1',
                'ttitle' => '',
                'tdesc' => '',
                'postdata' => 'Post data',
                'submit' => 'Post New Topic',
            ],
        ));

        $this->assertRedirect('?act=vt1&getlast=1', $page);
        $topic = Topic::selectOne(2);
        $post = ModelsPost::selectOne(2);

        $this->assertNull($topic);
        $this->assertEquals('Post data', $post->post);
        $this->assertTrue($postHookCalled);
        $this->assertEquals($post->asArray(), $postHookPost?->asArray());
    }
}
