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
use Jax\Database\Adapters\SQLite;
use Jax\Database\Database;
use Jax\Database\Model;
use Jax\Database\Utils as DatabaseUtils;
use Jax\Date;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Models\Topic as ModelsTopic;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
#[CoversClass(App::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(Badges::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(BotDetector::class)]
#[CoversClass(Column::class)]
#[CoversClass(Config::class)]
#[CoversClass(Database::class)]
#[CoversClass(DatabaseUtils::class)]
#[CoversClass(Date::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Key::class)]
#[CoversClass(Model::class)]
#[CoversClass(Page::class)]
#[CoversClass(Poll::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Reactions::class)]
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
#[CoversClass(Topic::class)]
#[CoversClass(User::class)]
#[CoversClass(UsersOnline::class)]
final class PollTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testViewPollSingleChoice(): void
    {
        $this->actingAs('admin');

        $topic = $this->createPoll();

        $page = $this->go('/topic/' . $topic->id);

        DOMAssert::assertSelectCount('#poll form input[type="radio"]', 3, $page);
        DOMAssert::assertSelectEquals('#poll .title', 'What is your favorite pet?', 1, $page);
        DOMAssert::assertSelectEquals('label[for="poll_0"]', 'Dog', 1, $page);
        DOMAssert::assertSelectEquals('label[for="poll_1"]', 'Cat', 1, $page);
        DOMAssert::assertSelectEquals('label[for="poll_2"]', 'Fish', 1, $page);
    }

    public function testViewPollSingleChoiceVote(): void
    {
        $this->actingAs('admin');

        $topic = $this->createPoll();

        $page = $this->go(new Request(
            get: ['path' => "/topic/{$topic->id}"],
            post: ['choice' => '1', 'votepoll' => '1'],
        ));

        DOMAssert::assertSelectEquals('#poll .title', 'What is your favorite pet?', 1, $page);
        DOMAssert::assertSelectEquals('.numvotes', '4 votes (40%)', 1, $page);
        DOMAssert::assertSelectEquals('.numvotes', '3 votes (30%)', 2, $page);
        DOMAssert::assertSelectEquals('.totalvotes', 'Total Votes: 10', 1, $page);
    }

    public function testViewPollMultiChoice(): void
    {
        $this->actingAs('admin');

        $topic = $this->createPoll(['pollType' => 'multi']);

        $page = $this->go('/topic/' . $topic->id);

        DOMAssert::assertSelectCount('#poll form input[type="checkbox"]', 3, $page);
        DOMAssert::assertSelectEquals('#poll .title', 'What is your favorite pet?', 1, $page);
        DOMAssert::assertSelectEquals('label[for="poll_0"]', 'Dog', 1, $page);
        DOMAssert::assertSelectEquals('label[for="poll_1"]', 'Cat', 1, $page);
        DOMAssert::assertSelectEquals('label[for="poll_2"]', 'Fish', 1, $page);
    }

    public function testViewPollMultiChoiceVote(): void
    {
        $this->actingAs('admin');

        $topic = $this->createPoll(['pollType' => 'multi']);

        $page = $this->go(new Request(
            get: ['path' => 'topic/' . $topic->id],
            post: ['choice' => ['0', '1'], 'votepoll' => '1'],
        ));

        DOMAssert::assertSelectEquals('#poll .title', 'What is your favorite pet?', 1, $page);
        DOMAssert::assertSelectEquals('.numvotes', '4 votes (36.36%)', 2, $page);
        DOMAssert::assertSelectEquals('.numvotes', '3 votes (27.27%)', 1, $page);
        DOMAssert::assertSelectEquals('.totalvotes', 'Total Votes: 10', 1, $page);
    }

    private function createPoll($props = ['pollType' => 'single']): ModelsTopic
    {
        $topic = new ModelsTopic($props);
        $topic->op = 1;
        $topic->title = 'Favorite Pet Poll';
        $topic->fid = 1;
        $topic->pollQuestion = 'What is your favorite pet?';
        $topic->pollChoices = '["Dog","Cat","Fish"]';
        $topic->pollResults = '2,3,4;5,6,7;8,9,10';

        $topic->insert();

        return $topic;
    }
}
