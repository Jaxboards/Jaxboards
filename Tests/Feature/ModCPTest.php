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
use Jax\Constants\JSAccess;
use Jax\Database;
use Jax\DatabaseUtils;
use Jax\DatabaseUtils\SQLite;
use Jax\Date;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\IPAddress;
use Jax\Jax;
use Jax\ModControls\ModPosts;
use Jax\ModControls\ModTopics;
use Jax\Model;
use Jax\Models\Forum;
use Jax\Models\Post;
use Jax\Models\Session as ModelsSession;
use Jax\Models\Topic;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Page;
use Jax\Page\ModControls;
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

use function array_find;
use function array_key_exists;
use function implode;
use function json_decode;
use function serialize;

/**
 * @internal
 */
#[CoversClass(App::class)]
#[CoversClass(BBCode::class)]
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
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Key::class)]
#[CoversClass(ModControls::class)]
#[CoversClass(Model::class)]
#[CoversClass(ModPosts::class)]
#[CoversClass(ModTopics::class)]
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
final class ModCPTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testModCPIndexAdmin(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=modcontrols&do=cp');

        DOMAssert::assertSelectEquals('.modcppage', 'Choose an option on the left.', 1, $page);
    }

    public function testModCPIndexMember(): void
    {
        $this->actingAs('member');

        $page = $this->go('?act=modcontrols&do=cp');

        DOMAssert::assertSelectCount('.modcppage', 0, $page);
        $this->assertRedirect('?', $page);
    }

    public function testEditMember(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=modcontrols&do=emem');

        DOMAssert::assertSelectCount('input[name=mname]', 1, $page);
    }

    public function testEditMemberMemberProvided(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['act' => 'modcontrols', 'do' => 'emem'],
            post: ['mid' => '1', 'submit' => 'showform'],
        ));

        DOMAssert::assertSelectCount('input[name=displayName][value=Admin]', 1, $page);
    }

    public function testEditMemberMemberProvidedSave(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['act' => 'modcontrols', 'do' => 'emem'],
            post: [
                'mid' => '1',
                'displayName' => 'New Name',
                'signature' => 'New signature',
                'submit' => 'save',
            ],
        ));

        DOMAssert::assertSelectEquals('.success', 'Profile information saved.', 1, $page);
        DOMAssert::assertSelectCount('input[name=displayName][value="New Name"]', 1, $page);
        DOMAssert::assertSelectEquals('textarea[name=signature]', 'New signature', 1, $page);
    }

    public function testIPTools(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=modcontrols&do=iptools');

        DOMAssert::assertSelectCount('input[name=ip]', 1, $page);
    }

    public function testIPToolsLookup(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['act' => 'modcontrols', 'do' => 'iptools'],
            post: ['ip' => '::1'],
        ));

        DOMAssert::assertSelectCount('input[type=text][name=ip][value="::1"]', 1, $page);
        DOMAssert::assertSelectEquals('span[style="color:#090"]', 'not banned', 1, $page);
        DOMAssert::assertSelectEquals('.modcppage .minibox .title', 'Users with this IP:', 1, $page);
        DOMAssert::assertSelectEquals('.modcppage .minibox .title', 'Last 5 shouts:', 1, $page);
        DOMAssert::assertSelectEquals('.modcppage .minibox .title', 'Last 5 posts:', 1, $page);
        DOMAssert::assertSelectEquals('.modcppage .minibox .content', '--No Data--', 3, $page);
    }

    public function testAddOriginalPostToModerate(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['act' => 'modcontrols', 'do' => 'modp', 'pid' => '1'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);
        $sessionData = ModelsSession::selectOne();

        $this->assertContainsEquals(['softurl'], $json);
        $this->assertContainsEquals(['modcontrols_postsync', '', '1'], $json);
        $this->assertEquals(serialize(['modtids' => '1']), $sessionData->vars);
    }

    public function testAddPostReplyToModerate(): void
    {
        $this->actingAs('admin');

        $pid = (string) $this->insertReply();

        $page = $this->go(new Request(
            get: ['act' => 'modcontrols', 'do' => 'modp', 'pid' => $pid],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);
        $sessionData = ModelsSession::selectOne();

        $this->assertContainsEquals(['softurl'], $json);
        $this->assertContainsEquals(['modcontrols_postsync', $pid, ''], $json);
        $this->assertEquals(serialize(['modpids' => $pid]), $sessionData->vars);
    }

    public function testAddTopicToModerate(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['act' => 'modcontrols', 'do' => 'modt', 'tid' => '1'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);
        $sessionData = ModelsSession::selectOne();

        $this->assertContainsEquals(['softurl'], $json);
        $this->assertContainsEquals(['modcontrols_postsync', '', '1'], $json);
        $this->assertEquals(serialize(['modtids' => '1']), $sessionData->vars);
    }

    public function testDeletePostsWithoutTrashcan(): void
    {
        $pid = $this->insertReply();

        $this->actingAs(
            'admin',
            sessionOverrides: ['modpids' => (string) $pid],
        );

        $page = $this->go(new Request(
            post: ['act' => 'modcontrols', 'dop' => 'delete'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['removeel', '#pid_2'], $json);
        $this->assertContainsEquals(['modcontrols_clearbox'], $json);

        $this->assertNull(Post::selectOne($pid), 'Post is deleted');
    }

    public function testDeleteTopicWithoutTrashcan(): void
    {
        $this->actingAs(
            'admin',
            sessionOverrides: ['modtids' => '1'],
        );

        $page = $this->go(new Request(
            post: ['act' => 'modcontrols', 'dot' => 'delete'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['modcontrols_clearbox'], $json);

        $this->assertNull(Topic::selectOne(1), 'Topic is deleted');
        $this->assertNull(Post::selectOne(1), 'Post is deleted');
    }

    public function testMovePostCommand(): void
    {
        $this->actingAs(
            'admin',
            sessionOverrides: ['modpids' => '1'],
        );

        $page = $this->go(new Request(
            post: ['act' => 'modcontrols', 'dop' => 'move'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['modcontrols_move', '1'], $json);
    }

    public function testMoveTopicCommand(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            post: ['act' => 'modcontrols', 'dot' => 'move'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['modcontrols_move', '0'], $json);
    }

    public function testMovePosts(): void
    {
        $pid = $this->insertReply();
        $tid = $this->insertTopic();

        $this->actingAs(
            'admin',
            sessionOverrides: ['modpids' => (string) $pid],
        );

        $page = $this->go(new Request(
            post: ['act' => 'modcontrols', 'dop' => 'moveto', 'id' => (string) $tid],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['modcontrols_clearbox'], $json);

        $this->assertEquals(Post::selectOne($pid)->tid, $tid, 'Post was moved');
    }

    public function testMoveTopics(): void
    {
        $fid = $this->insertForum();

        $this->actingAs(
            'admin',
            sessionOverrides: ['modtids' => '1'],
        );

        $page = $this->go(new Request(
            post: ['act' => 'modcontrols', 'dot' => 'moveto', 'id' => (string) $fid],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['modcontrols_clearbox'], $json);
        $this->assertEquals(Topic::selectOne(1)->fid, $fid, 'Topic was moved');
    }

    public function testMergeTopicsForm(): void
    {
        $otherTid = $this->insertTopic();

        $this->actingAs(
            'admin',
            sessionOverrides: ['modtids' => '1'],
        );

        $page = $this->go(new Request(
            post: ['act' => 'modcontrols', 'dot' => 'merge', 'id' => (string) $otherTid],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $html = array_find(
            $json,
            static fn($record): bool => array_key_exists(1, $record) && $record[1] === 'page',
        )[2];

        $this->assertStringContainsString('Which topic should the topics be merged into?', $html);
        DOMAssert::assertSelectCount('input[name="ot"][value="1"]', 1, $html);
    }

    public function testMergeTopicsDoMerge(): void
    {
        $otherTid = $this->insertTopic();

        $this->actingAs(
            'admin',
            sessionOverrides: ['modtids' => implode(',', [1, $otherTid])],
        );

        $page = $this->go(new Request(
            post: [
                'act' => 'modcontrols',
                'dot' => 'merge',
                'ot' => (string) $otherTid,
            ],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['modcontrols_clearbox'], $json);

        $redirect = array_find($json, static fn($cmd): bool => $cmd[0] === 'location');
        $this->assertStringContainsString("?act=vt{$otherTid}", $redirect[1]);

        $this->assertNull(Topic::selectOne(1), 'Original topic is deleted');
        $this->assertEquals($otherTid, Post::selectOne(1)->tid, 'OP moved to new topic');
        $this->assertEquals(1, Post::selectOne(1)->newtopic, 'Older post becomes OP');
        $this->assertEquals(0, Post::selectOne(2)->newtopic, 'Newer post gets demoted to reply');
    }

    public function testLockTopic(): void
    {
        $this->actingAs(
            'admin',
            sessionOverrides: ['modtids' => '1'],
        );

        $page = $this->go(new Request(
            post: ['act' => 'modcontrols', 'dot' => 'lock'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['modcontrols_clearbox'], $json);

        $this->assertEquals(1, Topic::selectOne(1)->locked);
    }


    public function testUnlockTopic(): void
    {
        $this->actingAs(
            'admin',
            sessionOverrides: ['modtids' => '1'],
        );

        // lock the topic
        $topic = Topic::selectOne(1);
        $topic->locked = 1;
        $topic->update();

        $page = $this->go(new Request(
            post: ['act' => 'modcontrols', 'dot' => 'unlock'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['modcontrols_clearbox'], $json);

        $this->assertEquals(0, Topic::selectOne(1)->locked);
    }

    public function testUnpinTopic(): void
    {
        $this->actingAs(
            'admin',
            sessionOverrides: ['modtids' => '1'],
        );

        $topic = Topic::selectOne(1);
        $topic->pinned = 1;
        $topic->update();

        $page = $this->go(new Request(
            post: ['act' => 'modcontrols', 'dot' => 'unpin'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['modcontrols_clearbox'], $json);

        $this->assertEquals(0, Topic::selectOne(1)->pinned);
    }


    public function testPinTopic(): void
    {
        $this->actingAs(
            'admin',
            sessionOverrides: ['modtids' => '1'],
        );

        $page = $this->go(new Request(
            post: ['act' => 'modcontrols', 'dot' => 'pin'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['modcontrols_clearbox'], $json);

        $this->assertEquals(1, Topic::selectOne(1)->pinned);
    }

    public function testLoadModControlsJS(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=modcontrols&do=load');

        $this->assertStringContainsString('modcontrols', $page);
    }

    private function insertForum(): int
    {
        $forum = new Forum();
        $forum->title = 'Other forum';
        $forum->insert();

        return $forum->id;
    }

    private function insertTopic(): int
    {
        $topic = new Topic();
        $topic->insert();

        $post = new Post();
        $post->newtopic = 1;
        $post->tid = $topic->id;
        $post->post = 'OP';
        $post->insert();

        return $topic->id;
    }

    private function insertReply(): int
    {
        $post = new Post();
        $post->author = 1;
        $post->post = 'reply';
        $post->tid = 1;
        $post->insert();

        return $post->id;
    }
}
