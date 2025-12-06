<?php

declare(strict_types=1);

namespace Tests\Unit\Jax;

use Jax\BBCode;
use Jax\Database;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileUtils;
use Jax\Model;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\ServiceConfig;
use PHPUnit\Framework\Attributes\CoversClass;
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
#[CoversClass(FileUtils::class)]
#[CoversClass(Model::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(ServiceConfig::class)]
#[Small]
final class BBCodeTest extends UnitTestCase
{
    private BBCode $bbCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bbCode = $this->container->get(BBCode::class);
    }

    public function testToHTML(): void
    {
        $testCases = [
            '[b]bold[/b]' => '<strong>bold</strong>',
            '[i]italic[/i]' => '<em>italic</em>',
            '[u]underline[/u]' => '<span style="text-decoration:underline">underline</span>',
            '[s]strikethrough[/s]' => '<span style="text-decoration:line-through">strikethrough</span>',
            '[color=red]red text[/color]' => '<span style="color:red">red text</span>',
            '[bg=#FFFF00]yellow background[/bg]' => '<span style="background:#FFFF00">yellow background</span>',
            '[font=Arial]Arial font[/font]' => '<span style="font-family:Arial">Arial font</span>',
            '[align=center]centered text[/align]' => '<p style="text-align:center">centered text</p>',
            '[url]http://example.com[/url]' => '<a href="http://example.com">http://example.com</a>',
            '[url=http://example.com]Example[/url]' => '<a href="http://example.com">Example</a>',
            '[img]http://example.com/image.jpg[/img]' => '<img src="http://example.com/image.jpg" title="" alt="" class="bbcodeimg" />',
            '[img=An image]http://example.com/image.jpg[/img]' => '<img src="http://example.com/image.jpg" title="An image" alt="An image" class="bbcodeimg" />',
            '[h2]Header 2[/h2]' => '<h2>Header 2</h2>',
            '[spoiler]hidden text[/spoiler]' => '<span class="spoilertext">hidden text</span>',
            implode(PHP_EOL, [
                '[ul]',
                '*Item 1',
                '*Item 2',
                '[/ul]',
            ]) => '<ul><li>Item 1</li><li>Item 2</li></ul>',
            '[quote]quoted text[/quote]' => "<div class='quote'>quoted text</div>",
            '[quote=Sean]quoted text[/quote]' => "<div class='quote'><div class='quotee'>Sean</div>quoted text</div>",
            '[video]https://www.youtube.com/watch?v=dQw4w9WgXcQ[/video]' => '/YouTube video player/',
        ];

        foreach ($testCases as $input => $output) {
            if (str_starts_with($output, '/')) {
                $this->assertMatchesRegularExpression(
                    $output,
                    $this->bbCode->toHTML($input),
                );

                continue;
            }

            $this->assertEquals(
                $output,
                $this->bbCode->toHTML($input),
            );
        }
    }
}
