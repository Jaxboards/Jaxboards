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
use Jax\Models\Post as ModelsPost;
use Jax\Models\Topic;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Modules\WebHooks;
use Jax\OpenGraph;
use Jax\Page;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\Routes\API;
use Jax\Routes\Post;
use Jax\Routes\Post\CreateTopic;
use Jax\Routes\Post\CreateTopicInput;
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
#[CoversClass(API::class)]
#[CoversClass(App::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(Games::class)]
#[CoversClass(BotDetector::class)]
#[CoversClass(Column::class)]
#[CoversClass(Config::class)]
#[CoversClass(CreateTopic::class)]
#[CoversClass(CreateTopicInput::class)]
#[CoversClass(Database::class)]
#[CoversClass(DatabaseUtils::class)]
#[CoversClass(Date::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(Hooks::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Key::class)]
#[CoversClass(Lodash::class)]
#[CoversClass(Model::class)]
#[CoversClass(OpenGraph::class)]
#[CoversClass(Page::class)]
#[CoversClass(Post::class)]
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
#[CoversClass(WebHooks::class)]
final class PostTest extends FeatureTestCase
{
    public function testPostNewTopic(): void
    {
        $this->actingAs('member');

        $page = $this->go('/post?fid=1');

        DOMAssert::assertSelectCount('input[name=ttitle]', 1, $page);
        DOMAssert::assertSelectCount('input[name=tdesc]', 1, $page);
        DOMAssert::assertSelectCount('textarea[name=postdata]', 1, $page);
    }

    public function testPostNewTopicSubmit(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(get: ['path' => '/post', 'fid' => '1'], post: [
            'how' => 'newtopic',
            'fid' => '1',
            'tid' => '',
            'ttitle' => 'Topic title',
            'tdesc' => 'Topic description',
            'postdata' => 'Post data',
            'submit' => 'Post New Topic',
        ]));

        $this->assertRedirect('topic', ['id' => '2', 'getlast' => '1'], $page);
        $topic = Topic::selectOne(2);
        $post = ModelsPost::selectOne(2);

        static::assertSame('Topic title', $topic->title);
        static::assertSame('Topic description', $topic->subtitle);
        static::assertSame('Post data', $post->post);
    }

    public function testPostReply(): void
    {
        $this->actingAs('member', sessionOverrides: ['multiquote' => 1]);

        $page = $this->go('/post?tid=1&how=qreply');

        DOMAssert::assertSelectCount('input[name=ttitle]', 0, $page);
        DOMAssert::assertSelectCount('input[name=tdesc]', 0, $page);
        DOMAssert::assertSelectRegExp('textarea[name=postdata]', '/matter of time/', 1, $page);
    }

    public function testPostReplySubmit(): void
    {
        $this->actingAs('member');

        // Create a callback to test the post hook
        function hookStub($arg = null): ?ModelsPost
        {
            static $postHookPostArg;

            if ($arg !== null) {
                $postHookPostArg = $arg;
            }

            return $postHookPostArg;
        }

        $this->container->get(Hooks::class)->addListener('post', hookStub(...));

        $page = $this->go(new Request(get: ['path' => '/post', 'tid' => '1'], post: [
            'how' => 'fullpost',
            'fid' => '',
            'tid' => '1',
            'ttitle' => '',
            'tdesc' => '',
            'postdata' => 'Post data',
            'submit' => 'Post New Topic',
        ]));

        $this->assertRedirect('topic', ['id' => '1', 'getlast' => '1'], $page);
        $topic = Topic::selectOne(2);
        $post = ModelsPost::selectOne(2);

        static::assertNull($topic);
        static::assertSame('Post data', $post->post);
        static::assertEquals($post->asArray(), hookStub()?->asArray());
    }

    public function testPostReplySubmitPreview(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(get: ['path' => '/post', 'tid' => '1'], post: [
            'how' => 'fullpost',
            'fid' => '',
            'tid' => '1',
            'ttitle' => '',
            'tdesc' => '',
            'postdata' => 'Post data',
            'submit' => 'Preview',
        ]));

        DOMAssert::assertSelectEquals('#post-preview .title', 'Post Preview', 1, $page);
        DOMAssert::assertSelectEquals('#post-preview .content', 'Post data', 1, $page);
    }

    public function testEditOwnPostForm(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/post?how=edit&tid=1&pid=1');

        DOMAssert::assertSelectRegExp('textarea[name=postdata]', '/matter of time/', 1, $page);
    }

    public function testEditOwnPostFormSubmit(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(get: ['path' => '/post', 'how' => 'edit', 'tid' => '1', 'pid' => '1'], post: [
            'how' => 'edit',
            'fid' => '1',
            'tid' => '1',
            'ttitle' => 'updated title',
            'tdesc' => 'updated description',
            'postdata' => 'updated post',
            'submit' => 'Edit Topic',
        ]));

        $topic = Topic::selectOne(1);
        $post = ModelsPost::selectOne(1);

        static::assertSame('updated title', $topic->title);
        static::assertSame('updated description', $topic->subtitle);
        static::assertSame('updated post', $post->post);

        $this->assertRedirect('topic', ['id' => '1', 'findpost' => '1'], $page);
    }

    public function testAdminEditOtherPost(): void
    {
        $this->actingAs('admin');

        // Insert a post by another user
        $post = new ModelsPost();
        $post->tid = 1;
        $post->post = 'post';
        $post->author = 2;
        $post->insert();

        $page = $this->go('/post?how=edit&tid=1&pid=2');

        DOMAssert::assertSelectEquals('textarea[name=postdata]', 'post', 1, $page);
    }

    public function testMemberEditAdminPost(): void
    {
        $this->actingAs('member');

        // Insert a post by another user
        $post = new ModelsPost();
        $post->tid = 1;
        $post->post = 'post';
        $post->author = 1;
        $post->insert();

        $page = $this->go('/post?how=edit&tid=1&pid=2');

        DOMAssert::assertSelectEquals('.error', "You don't have permission to edit that post!", 1, $page);
        DOMAssert::assertSelectCount('textarea[name=postdata]', 0, $page);
    }
}
