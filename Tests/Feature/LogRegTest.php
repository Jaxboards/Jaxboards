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
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Page;
use Jax\Page\LogReg;
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
#[CoversClass(LogReg::class)]
#[CoversClass(App::class)]
#[CoversClass(Column::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(Key::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(BotDetector::class)]
#[CoversClass(Config::class)]
#[CoversClass(Database::class)]
#[CoversClass(Date::class)]
#[CoversClass(DatabaseUtils::class)]
#[CoversClass(SQLite::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Model::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(Page::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(Router::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(Session::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(User::class)]

#[CoversFunction('Jax\pathjoin')]
final class LogRegTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testRegistrationForm(): void
    {
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
