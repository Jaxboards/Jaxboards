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
use Jax\Hooks;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Lodash;
use Jax\Models\Post;
use Jax\Models\RatingNiblet;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Modules\WebHooks;
use Jax\Page;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\Routes\Badges;
use Jax\Routes\Topic;
use Jax\Routes\Topic\Poll;
use Jax\Routes\Topic\Reactions;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use Jax\UsersOnline;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
#[CoversClass(Reactions::class)]
#[CoversClass(App::class)]
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
#[CoversClass(FileSystem::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Lodash::class)]
#[CoversClass(Model::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(Hooks::class)]
#[CoversClass(WebHooks::class)]
#[CoversClass(Page::class)]
#[CoversClass(Badges::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(Topic::class)]
#[CoversClass(Poll::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(Router::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(Session::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(User::class)]
#[CoversClass(UsersOnline::class)]
final class ReactionsTest extends FeatureTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->insertRatingNiblets();
    }

    public function testReactionsInTopic(): void
    {
        $this->actingAs('admin');

        $page = $this->go('/topic/1');
        $url = $this->container->get(Router::class)->url('topic', ['id' => '1', 'ratepost' => '1', 'niblet' => '1']);

        DOMAssert::assertSelectCount(".postrating a[href^='{$url}']", 1, $page);
        DOMAssert::assertSelectCount('.postrating img[src="image"][title="title"]', 1, $page);
    }

    public function testReactionsReactToPost(): void
    {
        $this->actingAs('admin');

        $this->go('/topic/1?ratepost=1&niblet=1');

        static::assertEquals(json_encode(['1' => [1]]), Post::selectOne(1)->rating);
    }

    public function testListReactions(): void
    {
        $this->actingAs('admin');

        $post = Post::selectOne(1);
        $post->rating = json_encode(['1' => [1]], JSON_THROW_ON_ERROR);
        $post->update();

        $page = $this->go(new Request(get: ['path' => 'topic/1', 'listrating' => '1'], server: [
            'HTTP_X_JSACCESS' => JSAccess::UPDATING->value,
        ]));

        $json = json_decode($page);

        static::assertContainsEquals(['preventNavigation'], $json);
        static::assertSame('listrating', $json[1][0]);
        static::assertSame(1, $json[1][1]);
        static::assertStringContainsString('Admin', $json[1][2]);
    }

    private function insertRatingNiblets(): void
    {
        $ratingNiblet = new RatingNiblet();
        $ratingNiblet->img = 'image';
        $ratingNiblet->title = 'title';
        $ratingNiblet->insert();
    }
}
