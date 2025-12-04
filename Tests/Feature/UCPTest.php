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

        $this->assertStringContainsString('Your current email: --none--', $page);
    }

    public function testEmail(): void
    {
        $this->actingAs('member', ['email' => 'jaxboards@jaxboards.com']);

        $page = $this->go('?act=ucp&what=email');

        $this->assertStringContainsString('Your current email: <strong>jaxboards@jaxboards.com</strong>', $page);
    }

    public function testEmailChange(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['act' => 'ucp', 'what' => 'email'],
            post: ['email' => 'jaxboards@jaxboards.com', 'submit' => 'true'],
        ));

        $this->assertStringContainsString('Email settings updated.', $page);

        $this->assertEquals(Member::selectOne(2)->email, 'jaxboards@jaxboards.com');
    }

    public function testChangePassword(): void
    {
        $this->actingAs('member', [
            'pass' => password_hash('oldpass', PASSWORD_DEFAULT),
        ]);

        $page = $this->go(new Request(
            get: ['act' => 'ucp', 'what' => 'pass'],
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
            get: ['act' => 'ucp', 'what' => 'pass'],
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

        $page = $this->go('?act=ucp&what=profile');

        foreach (array_keys($this->getProfileFormData()) as $field) {
            if ($field === 'submit') {
                continue;
            }

            if (in_array($field, ['dob_month', 'dob_day', 'dob_year', 'gender'])) {
                DOMAssert::assertSelectCount("select[name={$field}]", 1, $page);

                continue;
            }

            if ($field === 'about') {
                DOMAssert::assertSelectCount("textarea[name={$field}]", 1, $page);

                continue;
            }

            DOMAssert::assertSelectCount("input[name={$field}]", 1, $page);
        }
    }

    public function testProfileChange(): void
    {
        $this->actingAs('member');

        $formData = $this->getProfileFormData();

        $page = $this->go(new Request(
            get: ['act' => 'ucp', 'what' => 'profile'],
            post: $formData,
        ));

        $this->assertStringContainsString('Profile successfully updated.', $page);

        $member = Member::selectOne(2);
        $this->assertEquals($member->displayName, 'DisplayName');
        $this->assertEquals($member->full_name, 'Full Name');
        $this->assertEquals($member->usertitle, 'User Title');
        $this->assertEquals($member->about, 'About me');
        $this->assertEquals($member->location, 'Location');
        $this->assertEquals($member->gender, 'male');
        $this->assertEquals($member->contactSkype, 'Skype');
        $this->assertEquals($member->contactDiscord, 'Discord');
        $this->assertEquals($member->contactYIM, 'YIM');
        $this->assertEquals($member->contactMSN, 'MSN');
        $this->assertEquals($member->contactGoogleChat, 'GoogleChat');
        $this->assertEquals($member->contactAIM, 'AIM');
        $this->assertEquals($member->contactYoutube, 'Youtube');
        $this->assertEquals($member->contactSteam, 'Steam');
        $this->assertEquals($member->contactTwitter, 'Twitter');
        $this->assertEquals($member->contactBlueSky, 'BlueSky');
        $this->assertEquals($member->website, 'http://google.com');

        $birthdate = $this->container->get(Date::class)
            ->datetimeAsCarbon($member->birthdate)
        ;

        $this->assertEquals($birthdate->month, 1);
        $this->assertEquals($birthdate->day, 1);
        $this->assertEquals($birthdate->year, 2000);
    }

    public function testAvatarSettings(): void
    {
        $this->actingAs('member');

        $page = $this->go('?act=ucp&what=avatar');

        DOMAssert::assertSelectCount('.avatar img[src="/Service/Themes/Default/avatars/default.gif"]', 1, $page);
    }

    public function testAvatarSettingsSave(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['act' => 'ucp', 'what' => 'avatar'],
            post: ['changedava' => 'http://jaxboards.com'],
        ));

        DOMAssert::assertSelectCount('.avatar img[src="http://jaxboards.com"]', 1, $page);
    }

    public function testSoundSettings(): void
    {
        $this->actingAs('member');

        $page = $this->go('?act=ucp&what=sounds');

        DOMAssert::assertSelectCount('input[name=soundShout][checked]', 1, $page);
    }

    public function testSoundSettingsSave(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['act' => 'ucp', 'what' => 'sounds'],
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

        $page = $this->go('?act=ucp&what=board');

        DOMAssert::assertSelectCount('select[name=skin]', 1, $page);
        DOMAssert::assertSelectCount('input[name=usewordfilter][checked]', 1, $page);
        DOMAssert::assertSelectCount('input[name=wysiwyg][checked]', 1, $page);
    }

    public function testBoardCustomizationSave(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            get: ['act' => 'ucp', 'what' => 'board'],
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
