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
use Jax\Database;
use Jax\DatabaseUtils;
use Jax\DatabaseUtils\SQLite;
use Jax\Date;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Model;
use Jax\Models\Page as ModelsPage;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Page;
use Jax\Page\Calendar;
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
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
#[CoversClass('Jax\App')]
#[CoversClass('Jax\Attributes\Column')]
#[CoversClass('Jax\Attributes\ForeignKey')]
#[CoversClass('Jax\Attributes\Key')]
#[CoversClass('Jax\BBCode')]
#[CoversClass('Jax\BotDetector')]
#[CoversClass('Jax\Config')]
#[CoversClass('Jax\Database')]
#[CoversClass('Jax\DatabaseUtils')]
#[CoversClass('Jax\DatabaseUtils\SQLite')]
#[CoversClass('Jax\Date')]
#[CoversClass('Jax\DebugLog')]
#[CoversClass('Jax\DomainDefinitions')]
#[CoversClass('Jax\IPAddress')]
#[CoversClass('Jax\Jax')]
#[CoversClass('Jax\Model')]
#[CoversClass('Jax\Modules\PrivateMessage')]
#[CoversClass('Jax\Modules\Shoutbox')]
#[CoversClass('Jax\Page')]
#[CoversClass('Jax\Page\Calendar')]
#[CoversClass('Jax\Page\TextRules')]
#[CoversClass('Jax\Request')]
#[CoversClass('Jax\RequestStringGetter')]
#[CoversClass('Jax\Router')]
#[CoversClass('Jax\ServiceConfig')]
#[CoversClass('Jax\Session')]
#[CoversClass('Jax\Template')]
#[CoversClass('Jax\TextFormatting')]
#[CoversClass('Jax\User')]
#[CoversFunction('Jax\pathjoin')]
final class CustomPageTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testLoadCustomPage(): void
    {
        $pageModel = new ModelsPage();
        $pageModel->act = 'custompage';
        $pageModel->page = 'Hello World';
        $pageModel->insert();

        $this->actingAs('admin');

        $page = $this->go('?act=custompage');

        DOMAssert::assertSelectEquals('#page', 'Hello World', 1, $page);
    }
}
