<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\Page\Search;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
#[CoversClass(Search::class)]
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
#[CoversClass('Jax\FileSystem')]
#[CoversClass('Jax\ForumTree')]
#[CoversClass('Jax\IPAddress')]
#[CoversClass('Jax\Jax')]
#[CoversClass('Jax\Model')]
#[CoversClass('Jax\Modules\PrivateMessage')]
#[CoversClass('Jax\Modules\Shoutbox')]
#[CoversClass('Jax\Page')]
#[CoversClass('Jax\Page\TextRules')]
#[CoversClass('Jax\Request')]
#[CoversClass('Jax\RequestStringGetter')]
#[CoversClass('Jax\Router')]
#[CoversClass('Jax\ServiceConfig')]
#[CoversClass('Jax\Session')]
#[CoversClass('Jax\Template')]
#[CoversClass('Jax\TextFormatting')]
#[CoversClass('Jax\User')]
final class SearchTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testViewSearchForm(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=search');

        DOMAssert::assertSelectCount('input[name=searchterm]', 1, $page);
        DOMAssert::assertSelectEquals('select[name=fids] option[value=1]', 'Forum', 1, $page);
        DOMAssert::assertSelectCount('input[name=datestart]', 1, $page);
        DOMAssert::assertSelectCount('input[name=dateend]', 1, $page);
        DOMAssert::assertSelectCount('input[name=mid]', 1, $page);
    }
}
