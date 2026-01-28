<?php

declare(strict_types=1);

namespace Tests\Unit\Jax;

use Jax\BBCode;
use Jax\Database\Database;
use Jax\Database\Model;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\ServiceConfig;
use Jax\Template;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use Tests\UnitTestCase;

use function implode;
use function str_starts_with;

use const PHP_EOL;

/**
 * @internal
 */
#[CoversClass(BBCode::class)]
#[CoversClass(Database::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(Model::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(Template::class)]
#[Small]
final class BBCodeTest extends UnitTestCase
{
    private BBCode $bbCode;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Router is used for URL generation, we don't care to test that here
        $this->container->set(Router::class, self::createStub(Router::class));
        $this->bbCode = $this->container->get(BBCode::class);
    }

    public function testGetURLS(): void
    {
        self::assertEqualsCanonicalizing(
            [
                'http://cnn.com',
                'http://twitch.com',
            ],
            $this->bbCode->getURLs(<<<'BBCODE'
                [url]http://cnn.com[/url]

                http://foxnews.com

                [url=http://twitch.com]http://google.com[/url]
                BBCODE),
        );
    }

    #[DataProvider('bbcodeToHTMLDataProvider')]
    public function testToHTML(string $input, string $output): void
    {
        if (str_starts_with($output, '/')) {
            self::assertMatchesRegularExpression(
                $output,
                $this->bbCode->toHTML($input),
            );

            return;
        }

        self::assertEquals(
            $output,
            $this->bbCode->toHTML($input),
        );
    }

    #[DataProvider('bbcodeToMarkdownDataProvider')]
    public function testToMarkdown(string $input, string $output): void
    {
        self::assertEquals(
            $output,
            $this->bbCode->toMarkdown($input),
        );
    }

    /**
     * @return array<array{string,string}>
     */
    public static function bbcodeToHTMLDataProvider(): array
    {
        return [
            [
                '[b]bold[/b]',
                '<strong>bold</strong>',
            ],
            [
                '[i]italic[/i]',
                '<em>italic</em>',
            ],
            [
                '[u]underline[/u]',
                '<span style="text-decoration:underline">underline</span>',
            ],
            [
                '[s]strikethrough[/s]',
                '<span style="text-decoration:line-through">strikethrough</span>',
            ],
            [
                '[color=red]red text[/color]',
                '<span style="color:red">red text</span>',
            ],
            [
                '[bg=#FFFF00]yellow background[/bg]',
                '<span style="background:#FFFF00">yellow background</span>',
            ],
            [
                '[font=Arial]Arial font[/font]',
                '<span style="font-family:Arial">Arial font</span>',
            ],
            [
                '[align=center]centered text[/align]',
                '<p style="text-align:center">centered text</p>',
            ],
            [
                '[url]http://example.com[/url]',
                '<a href="http://example.com">http://example.com</a>',
            ],
            [
                '[url]https://example.com[/url]',
                '<a href="https://example.com">https://example.com</a>',
            ],
            [
                '[url=http://example.com]Example[/url]',
                '<a href="http://example.com">Example</a>',
            ],
            [
                '[url=https://example.com]Example[/url]',
                '<a href="https://example.com">Example</a>',
            ],
            [
                '[url=/katamari]Katamari[/url]',
                '<a href="/katamari">Katamari</a>',
            ],
            [
                '[img]http://example.com/image.jpg[/img]',
                '<img src="http://example.com/image.jpg" title="" alt="" class="bbcodeimg">',
            ],
            [
                '[img=An image]http://example.com/image.jpg[/img]',
                '<img src="http://example.com/image.jpg" title="An image" alt="An image" class="bbcodeimg">',
            ],
            [
                '[h2]Header 2[/h2]',
                '<h2>Header 2</h2>',
            ],
            [
                '[spoiler]hidden text[/spoiler]',
                '<span class="spoilertext">hidden text</span>',
            ],
            [
                implode(PHP_EOL, [
                    '[ul]',
                    '*Item 1',
                    '*Item 2',
                    '[/ul]',
                ]),
                '<ul><li>Item 1</li><li>Item 2</li></ul>',
            ],
            [
                '[quote]quoted text[/quote]',
                "<div class='quote'>quoted text</div>",
            ],
            [
                '[quote=Sean]quoted text[/quote]',
                "<div class='quote'><div class='quotee'>Sean</div>quoted text</div>",
            ],
            [
                '[video]https://www.youtube.com/watch?v=dQw4w9WgXcQ[/video]',
                '/YouTube video player/',
            ],
        ];
    }


    /**
     * @return array<array{string,string}>
     */
    public static function bbcodeToMarkdownDataProvider(): array
    {
        return [
            [
                '[b]bold[/b]',
                '**bold**',
            ],
            [
                '[i]italic[/i]',
                '*italic*',
            ],
            [
                '[u]underline[/u]',
                '__underline__',
            ],
            [
                '[s]strikethrough[/s]',
                '~~strikethrough~~',
            ],
            [
                '[color=red]red text[/color]',
                'red text',
            ],
            [
                '[bg=#FFFF00]yellow background[/bg]',
                'yellow background',
            ],
            [
                '[font=Arial]Arial font[/font]',
                'Arial font',
            ],
            [
                '[align=center]centered text[/align]',
                'centered text',
            ],
            [
                '[url]http://example.com[/url]',
                '[http://example.com](http://example.com)',
            ],
            [
                '[url]https://example.com[/url]',
                '[https://example.com](https://example.com)',
            ],
            [
                '[url=http://example.com]Example[/url]',
                '[Example](http://example.com)',
            ],
            [
                '[url=https://example.com]Example[/url]',
                '[Example](https://example.com)',
            ],
            [
                '[url=/katamari]Katamari[/url]',
                '[Katamari](/katamari)',
            ],
            [
                '[img]http://example.com/image.jpg[/img]',
                '![](http://example.com/image.jpg)',
            ],
            [
                '[img=An image]http://example.com/image.jpg[/img]',
                '![An image](http://example.com/image.jpg)',
            ],
            [
                '[h2]Header 2[/h2]',
                "# Header 2\n",
            ],
            [
                '[spoiler]hidden text[/spoiler]',
                '||hidden text||',
            ],

            [
                "[attachment]5[/attachment]",
                ""
            ],
            [
                "[ul]*item\n*item 2[/ul]",
                "\n*item\n*item 2",
            ],
            [
                '[quote]quoted text[/quote]',
                "> quoted text",
            ],
            [
                '[quote=Sean]quoted text[/quote]',
                "> quoted text",
            ],

            [
                '[size=5]text[/size]',
                "text",
            ],
            [
                "[video]http://youtube.com[/video]",
                "\nhttp://youtube.com"
            ],
        ];
    }
}
