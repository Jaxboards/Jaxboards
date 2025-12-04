<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\Page\LogReg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
#[CoversClass(LogReg::class)]
#[CoversClass('Jax\App')]
#[CoversClass('Jax\Attributes\Column')]
#[CoversClass('Jax\Attributes\ForeignKey')]
#[CoversClass('Jax\Attributes\Key')]
#[CoversClass('Jax\BBCode')]
#[CoversClass('Jax\BotDetector')]
#[CoversClass('Jax\Config')]
#[CoversClass('Jax\Database')]
#[CoversClass('Jax\Date')]
#[CoversClass('Jax\DatabaseUtils')]
#[CoversClass('Jax\DatabaseUtils\SQLite')]
#[CoversClass('Jax\DebugLog')]
#[CoversClass('Jax\DomainDefinitions')]
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

#[CoversFunction('Jax\pathjoin')]
final class LogRegTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testRegistrationForm(): void
    {
        $this->actingAs('guest');

        $page = $this->go('?act=logreg1');

        DOMAssert::assertSelectEquals('.box.register', 'Registration', 1, $page);
        DOMAssert::assertSelectCount('input[name=name]', 1, $page);
        DOMAssert::assertSelectCount('input[name=display_name]', 1, $page);
        DOMAssert::assertSelectCount('input[name=pass1]', 1, $page);
        DOMAssert::assertSelectCount('input[name=pass2]', 1, $page);
        DOMAssert::assertSelectCount('input[name=email]', 1, $page);
    }

    public function testLogout(): void
    {
        $this->actingAs('member');

        $page = $this->go('?act=logreg2');

        DOMAssert::assertSelectEquals('.success', 'Logged out successfully', 1, $page);
        DOMAssert::assertSelectEquals('.box.login', 'Login', 1, $page);
    }

    public function testLoginForm(): void
    {
        $this->actingAs('member');

        $page = $this->go('?act=logreg3');

        DOMAssert::assertSelectEquals('.box.login', 'Login', 1, $page);
        DOMAssert::assertSelectCount('input[name=user]', 1, $page);
        DOMAssert::assertSelectCount('input[name=pass]', 1, $page);
    }

    public function testForgotPasswordForm(): void
    {
        $this->actingAs('member');

        $page = $this->go('?act=logreg6');

        DOMAssert::assertSelectEquals('.box.login', 'Forgot Password', 1, $page);
        DOMAssert::assertSelectCount('input[name=user]', 1, $page);
    }
}
