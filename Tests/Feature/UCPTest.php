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
use Jax\Models\Member;
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Page;
use Jax\Page\TextRules;
use Jax\Page\UCP;
use Jax\Page\UCP\Inbox;
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

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertTrue;

/**
 * @internal
 */
#[CoversClass(UCP::class)]
#[CoversClass(App::class)]
#[CoversClass(Column::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(Key::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(BotDetector::class)]
#[CoversClass(Config::class)]
#[CoversClass(Database::class)]
#[CoversClass(DatabaseUtils::class)]
#[CoversClass(SQLite::class)]
#[CoversClass(Date::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Model::class)]
#[CoversClass(PrivateMessage::class)]
#[CoversClass(Shoutbox::class)]
#[CoversClass(Page::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(Inbox::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(Router::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(Session::class)]
#[CoversClass(Template::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(User::class)]
#[CoversFunction('Jax\pathjoin')]
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

    public function testChangePassword(): void
    {
        $this->actingAs('member', [
            'pass' => password_hash('oldpass', PASSWORD_DEFAULT)
        ]);

        $page = $this->go(new Request(
            get: ['act' => 'ucp', 'what' => 'pass'],
            post: [
                'curpass' => 'oldpass',
                'newpass1' => 'newpass',
                'newpass2' => 'newpass',
                'passchange' => 'true',
            ]
        ));

        assertStringContainsString('Password changed.', $page);
        assertTrue(password_verify('newpass', Member::selectOne(2)->pass));
    }

    public function testChangePasswordIncorrectCurrentPassword(): void
    {
        $this->actingAs('member', [
            'pass' => password_hash('oldpass', PASSWORD_DEFAULT)
        ]);

        $page = $this->go(new Request(
            get: ['act' => 'ucp', 'what' => 'pass'],
            post: [
                'curpass' => 'wrong',
                'newpass1' => 'newpass',
                'newpass2' => 'newpass',
                'passchange' => 'true',
            ]
        ));

        DOMAssert::assertSelectEquals('.error', 'The password you entered is incorrect.', 1, $page);
    }
}
