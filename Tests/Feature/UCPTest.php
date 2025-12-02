<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\Models\Member;
use Jax\Request;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertStringContainsString;

/**
 * @internal
 *
 * @coversNothing
 */
final class UCPTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testUCPNotePad(): void
    {
        $this->actingAs('member');

        $page = $this->go('?act=ucp');

        DOMAssert::assertSelectEquals('#notepad', 'Personal notes go here.', 1, $page);
    }

    public function testUCPNotePadSave(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['act' => 'ucp'],
            post: ['ucpnotepad' => 'howdy'],
        ));

        DOMAssert::assertSelectEquals('#notepad', 'howdy', 1, $page);
    }

    public function testSignature(): void
    {
        $this->actingAs('member', ['sig' => 'I like tacos']);

        $page = $this->go('?act=ucp&what=signature');

        DOMAssert::assertSelectEquals('#changesig', 'I like tacos', 1, $page);
    }

    public function testSignatureChange(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['act' => 'ucp', 'what' => 'signature'],
            post: ['changesig' => 'I made jaxboards'],
        ));

        DOMAssert::assertSelectEquals('#changesig', 'I made jaxboards', 1, $page);
    }

    public function testEmailNoEmail(): void
    {
        $this->actingAs('member');

        $page = $this->go('?act=ucp&what=email');

        assertStringContainsString('Your current email: --none--', $page);
    }

    public function testEmail(): void
    {
        $this->actingAs('member', ['email' => 'jaxboards@jaxboards.com']);

        $page = $this->go('?act=ucp&what=email');

        assertStringContainsString('Your current email: <strong>jaxboards@jaxboards.com</strong>', $page);
    }

    public function testEmailChange(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['act' => 'ucp', 'what' => 'email'],
            post: ['email' => 'jaxboards@jaxboards.com', 'submit' => 'true'],
        ));

        assertStringContainsString('Email settings updated.', $page);

        assertEquals(Member::selectOne(2)->email, 'jaxboards@jaxboards.com');
    }
}
