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
use Jax\Constants\JSAccess;
use Jax\Database\Adapters\SQLite;
use Jax\Database\Database;
use Jax\Database\Model;
use Jax\Database\Utils as DatabaseUtils;
use Jax\Date;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\GeoLocate;
use Jax\Hooks;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Lodash;
use Jax\Models\Forum;
use Jax\Models\Post;
use Jax\Models\Session as ModelsSession;
use Jax\Models\Topic;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Modules\WebHooks;
use Jax\Page;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\Routes\ModControls;
use Jax\Routes\ModControls\ModPosts;
use Jax\Routes\ModControls\ModTopics;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

use function array_find;
use function array_key_exists;
use function implode;
use function inet_pton;
use function json_decode;
use function json_encode;

/**
 * @internal
 */
#[CoversClass(App::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(Games::class)]
#[CoversClass(BotDetector::class)]
#[CoversClass(Column::class)]
#[CoversClass(Config::class)]
#[CoversClass(Database::class)]
#[CoversClass(DatabaseUtils::class)]
#[CoversClass(Date::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(Forum::class)]
#[CoversClass(GeoLocate::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Key::class)]
#[CoversClass(Lodash::class)]
#[CoversClass(ModControls::class)]
#[CoversClass(Model::class)]
#[CoversClass(ModPosts::class)]
#[CoversClass(ModTopics::class)]
#[CoversClass(Page::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(Router::class)]
#[CoversFunction('Jax\routes')]
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
final class ModControlsTest extends FeatureTestCase
{
    public function testModCPIndexAdmin(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/modcontrols');

        DOMAssert::assertSelectEquals('.modcppage', 'Choose an option on the left.', 1, $page);
    }

    public function testModCPIndexMember(): void
    {
        $this->actingAs('member');

        $page = $this->go('/modcontrols');

        DOMAssert::assertSelectCount('.modcppage', 0, $page);
        $this->assertRedirect('index', [], $page);
    }

    public function testEditMember(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/modcontrols/emem');

        DOMAssert::assertSelectCount('input[name=mname]', 1, $page);
    }

    public function testEditMemberMemberProvided(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(get: ['path' => '/modcontrols/emem'], post: [
            'mid' => '1',
            'submit' => 'showform',
        ]));

        DOMAssert::assertSelectCount('input[name=displayName][value=Admin]', 1, $page);
    }

    public function testEditMemberMemberProvidedSave(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(get: ['path' => '/modcontrols/emem'], post: [
            'mid' => '1',
            'displayName' => 'New Name',
            'signature' => 'New signature',
            'submit' => 'save',
        ]));

        DOMAssert::assertSelectEquals('.success', 'Profile information saved.', 1, $page);
        DOMAssert::assertSelectCount('input[name=displayName][value="New Name"]', 1, $page);
        DOMAssert::assertSelectEquals('textarea[name=signature]', 'New signature', 1, $page);
    }

    public function testIPTools(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/modcontrols/iptools');

        DOMAssert::assertSelectCount('input[name=ip]', 1, $page);
    }

    public function testIPToolsLookup(): void
    {
        $this->actingAs('admin', ['ip' => inet_pton('::1')]);

        $page = $this->go(new Request(get: ['path' => '/modcontrols/iptools'], post: ['ip' => '::1']));

        DOMAssert::assertSelectCount('input[type=text][name=ip][value="::1"]', 1, $page);
        DOMAssert::assertSelectEquals('span[style="color:#090"]', 'not banned', 1, $page);
        DOMAssert::assertSelectEquals('.modcppage .minibox .title', 'Users with this IP:', 1, $page);
        DOMAssert::assertSelectEquals('.modcppage .minibox .title', 'Last 5 shouts:', 0, $page);
        DOMAssert::assertSelectEquals('.modcppage .minibox .title', 'Last 5 posts:', 0, $page);
    }

    public function testOnlineSessions(): void
    {
        $this->actingAs('admin');

        $session = new ModelsSession();
        $session->useragent = 'Firefox';
        $session->ip = inet_pton('64.233.160.0');
        $session->insert();

        $page = $this->go(new Request(get: ['path' => '/modcontrols/onlineSessions']));

        DOMAssert::assertSelectEquals('.onlinesessions td', 'Firefox', 1, $page);
        DOMAssert::assertSelectRegExp('.onlinesessions td', '/64.233.160.0/', 1, $page);
    }

    public function testAddOriginalPostToModerate(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(get: ['path' => '/modcontrols/modp', 'pid' => '1'], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);
        $sessionData = ModelsSession::selectOne();

        static::assertContainsEquals(['preventNavigation'], $json);
        static::assertContainsEquals(['modcontrols_postsync', '', '1'], $json);
        static::assertEquals(json_encode(['modtids' => '1']), $sessionData?->vars);
    }

    public function testAddPostReplyToModerate(): void
    {
        $this->actingAs('admin');

        $pid = (string) $this->insertReply()->id;

        $page = $this->go(new Request(get: ['path' => '/modcontrols/modp', 'pid' => $pid], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);
        $sessionData = ModelsSession::selectOne();

        static::assertContainsEquals(['preventNavigation'], $json);
        static::assertContainsEquals(['modcontrols_postsync', $pid, ''], $json);
        static::assertEquals(json_encode(['modpids' => $pid]), $sessionData?->vars);
    }

    public function testAddTopicToModerate(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(get: ['path' => '/modcontrols/modt', 'tid' => '1'], server: [
            'HTTP_X_JSACCESS' => JSAccess::ACTING->value,
        ]));

        $json = json_decode($page, true);
        $sessionData = ModelsSession::selectOne();

        static::assertContainsEquals(['preventNavigation'], $json);
        static::assertContainsEquals(['modcontrols_postsync', '', '1'], $json);
        static::assertEquals(json_encode(['modtids' => '1']), $sessionData?->vars);
    }

    public function testDeletePostsWithTrashcan(): void
    {
        $trashcanId = $this->insertForum(['trashcan' => 1])->id;
        $pid = $this->insertReply()->id;

        $this->actingAs('admin', sessionOverrides: ['modpids' => (string) $pid]);

        $page = $this->go(new Request(
            get: ['path' => '/modcontrols'],
            post: ['dop' => 'delete'],
            cookie: ['PHPSESSID' => 'paratest'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        static::assertContainsEquals(['removeel', '#pid_2'], $json);
        static::assertContainsEquals(['modcontrols_clearbox'], $json);
        static::assertContainsEquals(['location', '/topic/2'], $json, 'Mod redirected to trashcan topic');

        $trashcanTopic = Topic::selectOne(2);
        $post = Post::selectOne($pid);

        static::assertEquals($trashcanTopic?->fid, $trashcanId, 'New topic is created in trashcan');
        static::assertEquals($post?->tid, $trashcanTopic?->id, 'Post is moved to new trashcan topic');
    }

    public function testDeletePostsWithoutTrashcan(): void
    {
        $pid = $this->insertReply()->id;

        $this->actingAs('admin', sessionOverrides: ['modpids' => (string) $pid]);

        $page = $this->go(new Request(
            get: ['path' => '/modcontrols'],
            post: ['dop' => 'delete'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        static::assertContainsEquals(['removeel', '#pid_2'], $json);
        static::assertContainsEquals(['modcontrols_clearbox'], $json);

        static::assertNull(Post::selectOne($pid), 'Post is deleted');
    }

    public function testDeleteTopicWithoutTrashcan(): void
    {
        $this->actingAs('admin', sessionOverrides: ['modtids' => '1']);

        $page = $this->go(new Request(
            get: ['path' => '/modcontrols'],
            post: ['dot' => 'delete'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        static::assertContainsEquals(['modcontrols_clearbox'], $json);

        static::assertNull(Topic::selectOne(1), 'Topic is deleted');
        static::assertNull(Post::selectOne(1), 'Post is deleted');
    }

    public function testMovePostCommand(): void
    {
        $this->actingAs('admin', sessionOverrides: ['modpids' => '1']);

        $page = $this->go(new Request(
            get: ['path' => '/modcontrols'],
            post: ['dop' => 'move'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        static::assertContainsEquals(['modcontrols_move', '1'], $json);
    }

    public function testMoveTopicCommand(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['path' => '/modcontrols'],
            post: ['dot' => 'move'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        static::assertContainsEquals(['modcontrols_move', '0'], $json);
    }

    public function testMovePosts(): void
    {
        $pid = $this->insertReply()->id;
        $tid = $this->insertTopic()->id;

        $this->actingAs('admin', sessionOverrides: ['modpids' => (string) $pid]);

        $page = $this->go(new Request(
            get: ['path' => '/modcontrols'],
            post: ['dop' => 'moveto', 'id' => (string) $tid],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        static::assertContainsEquals(['modcontrols_clearbox'], $json);

        static::assertEquals(Post::selectOne($pid)?->tid, $tid, 'Post was moved');
    }

    public function testMoveTopics(): void
    {
        $fid = $this->insertForum()->id;

        $this->actingAs('admin', sessionOverrides: ['modtids' => '1']);

        $page = $this->go(new Request(
            get: ['path' => '/modcontrols'],
            post: ['dot' => 'moveto', 'id' => (string) $fid],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        static::assertContainsEquals(['modcontrols_clearbox'], $json);
        static::assertEquals(Topic::selectOne(1)?->fid, $fid, 'Topic was moved');
    }

    public function testMergeTopicsForm(): void
    {
        $topic = $this->insertTopic();

        $this->actingAs('admin', sessionOverrides: ['modtids' => '1']);

        $page = $this->go(new Request(
            get: ['path' => '/modcontrols'],
            post: ['dot' => 'merge', 'id' => (string) $topic->id],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $html = array_find($json, static fn($record): bool => array_key_exists(1, $record) && $record[1] === 'page')[2];

        static::assertStringContainsString('Which topic should the topics be merged into?', $html);
        DOMAssert::assertSelectCount('input[name="ot"][value="1"]', 1, $html);
    }

    public function testMergeTopicsDoMerge(): void
    {
        $topic = $this->insertTopic();

        $this->actingAs('admin', sessionOverrides: ['modtids' => implode(',', [1, $topic->id])]);

        $page = $this->go(new Request(
            get: ['path' => '/modcontrols'],
            post: [
                'dot' => 'merge',
                'ot' => (string) $topic->id,
            ],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        static::assertContainsEquals(['modcontrols_clearbox'], $json);

        $redirect = array_find($json, static fn($cmd): bool => $cmd[0] === 'location');
        static::assertStringContainsString(
            $this->container->get(Router::class)->url('topic', ['id' => $topic->id]),
            $redirect[1],
        );

        static::assertNull(Topic::selectOne(1), 'Original topic is deleted');
        static::assertEquals($topic->id, Post::selectOne(1)?->tid, 'OP moved to new topic');
        static::assertSame(1, Post::selectOne(1)?->newtopic, 'Older post becomes OP');
        static::assertSame(0, Post::selectOne(2)?->newtopic, 'Newer post gets demoted to reply');
    }

    public function testLockTopic(): void
    {
        $this->actingAs('admin', sessionOverrides: ['modtids' => '1']);

        $page = $this->go(new Request(
            get: ['path' => '/modcontrols'],
            post: ['dot' => 'lock'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        static::assertContainsEquals(['modcontrols_clearbox'], $json);

        static::assertSame(1, Topic::selectOne(1)?->locked);
    }

    public function testUnlockTopic(): void
    {
        $this->actingAs('admin', sessionOverrides: ['modtids' => '1']);

        // lock the topic
        $topic = Topic::selectOne(1);
        $topic->locked = 1;
        $topic->update();

        $page = $this->go(new Request(
            get: ['path' => '/modcontrols'],
            post: ['dot' => 'unlock'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        static::assertContainsEquals(['modcontrols_clearbox'], $json);

        static::assertSame(0, Topic::selectOne(1)?->locked);
    }

    public function testUnpinTopic(): void
    {
        $this->actingAs('admin', sessionOverrides: ['modtids' => '1']);

        $topic = Topic::selectOne(1);
        $topic->pinned = 1;
        $topic->update();

        $page = $this->go(new Request(
            get: ['path' => '/modcontrols'],
            post: ['dot' => 'unpin'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        static::assertContainsEquals(['modcontrols_clearbox'], $json);

        static::assertSame(0, Topic::selectOne(1)?->pinned);
    }

    public function testPinTopic(): void
    {
        $this->actingAs('admin', sessionOverrides: ['modtids' => '1']);

        $page = $this->go(new Request(
            get: ['path' => '/modcontrols'],
            post: ['dot' => 'pin'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        static::assertContainsEquals(['modcontrols_clearbox'], $json);

        static::assertSame(1, Topic::selectOne(1)?->pinned);
    }

    private function insertForum(array $forumProperties = []): Forum
    {
        $forum = new Forum($forumProperties);
        $forum->title = 'Other forum';
        $forum->insert();

        return $forum;
    }

    private function insertTopic($topicProperties = []): Topic
    {
        $topic = new Topic($topicProperties);
        $topic->insert();

        $post = new Post();
        $post->newtopic = 1;
        $post->tid = $topic->id;
        $post->post = 'OP';
        $post->insert();

        return $topic;
    }

    private function insertReply(): Post
    {
        $post = new Post();
        $post->author = 1;
        $post->post = 'reply';
        $post->tid = 1;
        $post->insert();

        return $post;
    }
}
