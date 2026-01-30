<?php

declare(strict_types=1);

namespace Tests\Unit\Jax;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\BBCode;
use Jax\Config;
use Jax\Database\Adapters\SQLite;
use Jax\Database\Database;
use Jax\Database\Model;
use Jax\Database\Utils as DatabaseUtils;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\ServiceConfig;
use Jax\Template;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use Tests\UnitTestCase;

use function DI\autowire;

/**
 * @internal
 */
#[CoversClass(TextFormatting::class)]
#[CoversClass(Column::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(Key::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(Config::class)]
#[CoversClass(Database::class)]
#[CoversClass(DatabaseUtils::class)]
#[CoversClass(SQLite::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Model::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(User::class)]
#[Small]
final class TextFormattingTest extends UnitTestCase
{
    private TextFormatting $textFormatting;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->set(
            Request::class,
            autowire()->constructorParameter('server', [
                'HTTP_HOST' => 'jaxboards.com',
            ]),
        );

        // Router is used for URL generation, we don't care to test that here
        $this->container->set(Router::class, self::createStub(Router::class));

        $databaseUtils = $this->container->get(DatabaseUtils::class);
        $databaseUtils->install();

        $this->textFormatting = $this->container->get(TextFormatting::class);
    }

    public function testTheWorks(): void
    {
        $result = $this->textFormatting->theWorks(
            <<<'TEXT'
                [code]hello[/code]
                :)
                world
                TEXT,
        );

        self::assertEquals(
            <<<'HTML'
                <div class="bbcode code ">hello</div><br>
                <img src="/emoticons/keshaemotes/smile.gif" data-emoji=":)" alt=":)"><br>
                world
                HTML,
            $result,
        );
    }

    #[DataProvider('linkifyDataProvider')]
    public function testLinkify(string $input, string $expectation): void
    {
        self::assertEquals(
            $expectation,
            $this->textFormatting->linkify($input),
        );
    }

    public static function linkifyDataProvider(): array
    {
        return [
            ['http://google.com', '[url=http://google.com]http://google.com[/url]'],
            ['http://jaxboards.com/topic/1', '[url=/topic/1]Topic #1[/url]'],
            ['http://jaxboards.com/topic/3?findpost=33&pid=33', '[url=/topic/3?findpost=33&pid=33]Post #33[/url]'],
            [
                "nbsp\u{a0}http://google.com",
                "nbsp\u{a0}[url=http://google.com]http://google.com[/url]",
            ],
        ];
    }

    public function testTextOnly(): void
    {
        self::assertEquals(
            $this->textFormatting->textOnly(
                '[b][i][u][quote=Sean]content[/quote][/u][/i][/b]',
            ),
            'content',
        );
    }

    public function testVideoify(): void
    {
        self::assertEquals(
            $this->textFormatting->videoify('check out this video https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
            'check out this video [video]https://www.youtube.com/watch?v=dQw4w9WgXcQ[/video]'
        );
    }
}
