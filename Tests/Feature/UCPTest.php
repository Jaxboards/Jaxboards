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
use Jax\Modules\PrivateMessage;
use Jax\Modules\Shoutbox;
use Jax\Page;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\Routes\UCP;
use Jax\Routes\UCP\Inbox;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

use function array_keys;
use function in_array;
use function password_hash;
use function password_verify;

use const PASSWORD_DEFAULT;

/**
 * @internal
 */
#[CoversClass(UCP::class)]
#[CoversClass(App::class)]
#[CoversClass(FileSystem::class)]
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
final class UCPTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testUCPNotePad(): void
    {
        $this->actingAs('member', ['ucpnotepad' => 'pikachu']);

        $page = $this->go('/ucp');

        DOMAssert::assertSelectEquals('#notepad', 'pikachu', 1, $page);
    }

    public function testUCPNotePadSave(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['path' => '/ucp'],
            post: ['ucpnotepad' => 'howdy'],
        ));

        DOMAssert::assertSelectEquals('#notepad', 'howdy', 1, $page);
    }

    public function testSignature(): void
    {
        $this->actingAs('member', ['sig' => 'I like tacos']);

        $page = $this->go('/ucp/signature');

        DOMAssert::assertSelectEquals('#changesig', 'I like tacos', 1, $page);
    }

    public function testSignatureChange(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['path' => '/ucp', 'what' => 'signature'],
            post: ['changesig' => 'I made jaxboards'],
        ));

        DOMAssert::assertSelectEquals('#changesig', 'I made jaxboards', 1, $page);
    }

    public function testEmail(): void
    {
        $this->actingAs('member', ['email' => 'jaxboards@jaxboards.com']);

        $page = $this->go('/ucp/email');

        DOMAssert::assertSelectEquals('strong', 'jaxboards@jaxboards.com', 1, $page);
    }

    public function testEmailChange(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['path' => '/ucp', 'what' => 'email'],
            post: ['email' => 'jaxboards@jaxboards.com', 'submit' => 'true'],
        ));

        $this->assertStringContainsString('Email settings updated.', $page);

        $this->assertEquals('jaxboards@jaxboards.com', Member::selectOne(2)->email);
    }

    public function testChangePassword(): void
    {
        $this->actingAs('member', [
            'pass' => password_hash('oldpass', PASSWORD_DEFAULT),
        ]);

        $page = $this->go(new Request(
            get: ['path' => '/ucp', 'what' => 'pass'],
            post: [
                'curpass' => 'oldpass',
                'newpass1' => 'newpass',
                'newpass2' => 'newpass',
                'passchange' => 'true',
            ],
        ));

        $this->assertStringContainsString('Password changed.', $page);
        $this->assertTrue(password_verify('newpass', Member::selectOne(2)->pass));
    }

    public function testChangePasswordIncorrectCurrentPassword(): void
    {
        $this->actingAs('member', [
            'pass' => password_hash('oldpass', PASSWORD_DEFAULT),
        ]);

        $page = $this->go(new Request(
            get: ['path' => '/ucp', 'what' => 'pass'],
            post: [
                'curpass' => 'wrong',
                'newpass1' => 'newpass',
                'newpass2' => 'newpass',
                'passchange' => 'true',
            ],
        ));

        DOMAssert::assertSelectEquals('.error', 'The password you entered is incorrect.', 1, $page);
        $this->assertFalse(password_verify('newpass', Member::selectOne(2)->pass));
    }

    public function testProfileForm(): void
    {
        $this->actingAs('member');

        $page = $this->go('/ucp/profile');

        foreach (array_keys($this->getProfileFormData()) as $field) {
            match (true) {
                $field === 'submit' => '',

                in_array($field, [
                    'dob_month',
                    'dob_day',
                    'dob_year',
                    'gender',
                ], true) => DOMAssert::assertSelectCount("select[name={$field}]", 1, $page),

                $field === 'about' => DOMAssert::assertSelectCount("textarea[name={$field}]", 1, $page),

                default => DOMAssert::assertSelectCount("input[name={$field}]", 1, $page),
            };
        }
    }

    public function testProfileChange(): void
    {
        $this->actingAs('member');

        $formData = $this->getProfileFormData();

        $page = $this->go(new Request(
            get: ['path' => '/ucp', 'what' => 'profile'],
            post: $formData,
        ));

        $this->assertStringContainsString('Profile successfully updated.', $page);

        $member = Member::selectOne(2);
        $this->assertEquals('DisplayName', $member->displayName);
        $this->assertEquals('Full Name', $member->full_name);
        $this->assertEquals('User Title', $member->usertitle);
        $this->assertEquals('About me', $member->about);
        $this->assertEquals('Location', $member->location);
        $this->assertEquals('male', $member->gender);
        $this->assertEquals('Skype', $member->contactSkype);
        $this->assertEquals('Discord', $member->contactDiscord);
        $this->assertEquals('YIM', $member->contactYIM);
        $this->assertEquals('MSN', $member->contactMSN);
        $this->assertEquals('GoogleChat', $member->contactGoogleChat);
        $this->assertEquals('AIM', $member->contactAIM);
        $this->assertEquals('Youtube', $member->contactYoutube);
        $this->assertEquals('Steam', $member->contactSteam);
        $this->assertEquals('Twitter', $member->contactTwitter);
        $this->assertEquals('BlueSky', $member->contactBlueSky);
        $this->assertEquals('http://google.com', $member->website);

        $birthdate = $this->container->get(Date::class)
            ->datetimeAsCarbon($member->birthdate)
        ;

        $this->assertEquals(1, $birthdate->month);
        $this->assertEquals(1, $birthdate->day);
        $this->assertEquals(2_000, $birthdate->year);
    }

    public function testAvatarSettings(): void
    {
        $this->actingAs('member');

        $page = $this->go('/ucp/avatar');

        DOMAssert::assertSelectCount('.avatar img[src="/Service/Themes/Default/avatars/default.gif"]', 1, $page);
    }

    public function testAvatarSettingsSave(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['path' => '/ucp', 'what' => 'avatar'],
            post: ['changedava' => 'http://jaxboards.com'],
        ));

        DOMAssert::assertSelectCount('.avatar img[src="http://jaxboards.com"]', 1, $page);
    }

    public function testSoundSettings(): void
    {
        $this->actingAs('member');

        $page = $this->go('/ucp/sounds');

        DOMAssert::assertSelectCount('input[name=soundShout][checked]', 1, $page);
    }

    public function testSoundSettingsSave(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['path' => '/ucp', 'what' => 'sounds'],
            post: [
                // clear all checkboxes
                'submit' => 'true',
            ],
        ));

        DOMAssert::assertSelectCount('input[name=soundShout][checked]', 0, $page);
    }

    public function testBoardCustomization(): void
    {
        $this->actingAs('member');

        $page = $this->go('/ucp/board');

        DOMAssert::assertSelectCount('select[name=skin]', 1, $page);
        DOMAssert::assertSelectCount('input[name=usewordfilter][checked]', 1, $page);
        DOMAssert::assertSelectCount('input[name=wysiwyg][checked]', 1, $page);
    }

    public function testBoardCustomizationSave(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['path' => '/ucp', 'what' => 'board'],
            post: [
                'skin' => '1',
                // clear all checkboxes
                'submit' => 'true',
            ],
        ));

        DOMAssert::assertSelectCount('select[name=skin]', 1, $page);
        DOMAssert::assertSelectCount('input[name=usewordfilter][checked]', 0, $page);
        DOMAssert::assertSelectCount('input[name=wysiwyg][checked]', 0, $page);
    }

    /**
     * @return array<string,string>
     */
    private function getProfileFormData(): array
    {
        return [
            'displayName' => 'DisplayName',
            'full_name' => 'Full Name',
            'usertitle' => 'User Title',
            'about' => 'About me',
            'location' => 'Location',
            'gender' => 'male',
            'dob_month' => '1',
            'dob_day' => '1',
            'dob_year' => '2000',
            'contactSkype' => 'Skype',
            'contactDiscord' => 'Discord',
            'contactYIM' => 'YIM',
            'contactMSN' => 'MSN',
            'contactGoogleChat' => 'GoogleChat',
            'contactAIM' => 'AIM',
            'contactYoutube' => 'Youtube',
            'contactSteam' => 'Steam',
            'contactTwitter' => 'Twitter',
            'contactBlueSky' => 'BlueSky',
            'website' => 'http://google.com',
            'submit' => 'Save Profile Settings',
        ];
    }
}
