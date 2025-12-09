<?php

declare(strict_types=1);

namespace Tests\Unit\Jax;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\BBCode;
use Jax\Config;
use Jax\Database\Database;
use Jax\Database\Utils as DatabaseUtils;
use Jax\Database\Adapters\SQLite;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Database\Model;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\ServiceConfig;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use Tests\UnitTestCase;

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
#[CoversClass(TextRules::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(User::class)]
#[Small]
final class TextFormattingTest extends UnitTestCase
{
    private TextFormatting $textFormatting;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->assertEquals(
            <<<'HTML'
                <div class="bbcode code ">hello</div><br>
                <img src='emoticons/keshaemotes/smile.gif' alt=':)' /><br>
                world
                HTML,
            $result,
        );
    }

    public function testLinkify(): void
    {
        $this->assertEquals($this->textFormatting->linkify('http://google.com'), '[url=http://google.com]http://google.com[/url]');
    }
}
