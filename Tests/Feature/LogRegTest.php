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
use Jax\Models\Member;
use Jax\Models\Stats;
use Jax\Models\Token;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Page;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\Routes\LogReg;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
#[CoversClass(LogReg::class)]
#[CoversClass(App::class)]
#[CoversClass(FileSystem::class)]
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
final class LogRegTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testRegistrationForm(): void
    {
        $page = $this->go('/register');

        DOMAssert::assertSelectEquals('.box.register', 'Registration', 1, $page);
        DOMAssert::assertSelectCount('input[name=name]', 1, $page);
        DOMAssert::assertSelectCount('input[name=display_name]', 1, $page);
        DOMAssert::assertSelectCount('input[name=pass1]', 1, $page);
        DOMAssert::assertSelectCount('input[name=pass2]', 1, $page);
        DOMAssert::assertSelectCount('input[name=email]', 1, $page);
    }

    public function testRegistration(): void
    {
        $page = $this->go(new Request(
            get: ['path' => 'register'],
            post: [
                'register' => 'true',
                'name' => 'Sean',
                'display_name' => 'Sean',
                'pass1' => 'password',
                'pass2' => 'password',
                'email' => 'test@test.com',
            ],
        ));

        $this->assertRedirect('index', [], $page);
        $this->assertEquals('Sean', Member::selectOne(1)->displayName);
        $this->assertEquals(1, Stats::selectOne()->last_register);
    }

    public function testLogout(): void
    {
        $this->actingAs('member');

        $page = $this->go('/logout');

        DOMAssert::assertSelectEquals('.success', 'Logged out successfully', 1, $page);
        DOMAssert::assertSelectEquals('.box.login', 'Login', 1, $page);
    }

    public function testLoginForm(): void
    {
        $this->actingAs('member');

        $page = $this->go('/login');

        DOMAssert::assertSelectEquals('.box.login', 'Login', 1, $page);
        DOMAssert::assertSelectCount('input[name=user]', 1, $page);
        DOMAssert::assertSelectCount('input[name=pass]', 1, $page);
    }

    public function testLogin(): void
    {
        // This just ensures the admin model is inserted
        $this->actingAs('admin');

        $page = $this->go(new Request(
            get: ['path' => '/login'],
            post: [
                'user' => 'Admin',
                'pass' => 'password',
            ],
        ));

        $this->assertRedirect('index', [], $page);

        // Ensure token inserted
        $token = Token::selectOne();
        $this->assertEquals(1, $token->uid);
        $this->assertEquals('login', $token->type);

        $request = $this->container->get(Request::class);
        $this->assertEquals($request->cookie('utoken'), $token->token);
    }

    public function testForgotPasswordForm(): void
    {
        $this->actingAs('member');

        $page = $this->go('/forgotPassword');

        DOMAssert::assertSelectEquals('.box.login', 'Forgot Password', 1, $page);
        DOMAssert::assertSelectCount('input[name=user]', 1, $page);
    }
}
