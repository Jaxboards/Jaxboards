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

use function json_decode;

/**
 * @internal
 */
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
#[CoversClass(ModPosts::class)]
#[CoversClass(ModTopics::class)]
#[CoversClass(Model::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(Page::class)]
#[CoversClass(ModControls::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(Router::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(Session::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
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

    public function testAddPostToModerate(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['act' => 'modcontrols', 'do' => 'modp', 'pid' => '1'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['softurl'], $json);
        $this->assertContainsEquals(['modcontrols_postsync', '', '1'], $json);
    }

    public function testAddTopicToModerate(): void
    {
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['act' => 'modcontrols', 'do' => 'modt', 'tid' => '1'],
            server: ['HTTP_X_JSACCESS' => JSAccess::ACTING->value],
        ));

        $json = json_decode($page, true);

        $this->assertContainsEquals(['softurl'], $json);
        $this->assertContainsEquals(['modcontrols_postsync', '', '1'], $json);
    }

    public function testLoadModControlsJS(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=modcontrols&do=load');

        $this->assertStringContainsString('modcontrols', $page);
    }
}
