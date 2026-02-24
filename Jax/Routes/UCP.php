<?php

declare(strict_types=1);

namespace Jax\Routes;

use Carbon\Carbon;
use Jax\Config;
use Jax\Constants\Groups;
use Jax\Database\Database;
use Jax\Date;
use Jax\Interfaces\Route;
use Jax\Models\Activity;
use Jax\Models\Member;
use Jax\Models\Skin;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Routes\UCP\Inbox;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;
use Override;

use function filter_var;
use function is_string;
use function mb_strlen;
use function mb_strstr;
use function password_hash;
use function password_verify;
use function preg_match;
use function trim;

use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_URL;
use const PASSWORD_DEFAULT;

final readonly class UCP implements Route
{
    public function __construct(
        private Config $config,
        private Database $database,
        private Date $date,
        private Inbox $inbox,
        private Page $page,
        private Request $request,
        private Router $router,
        private TextFormatting $textFormatting,
        private Template $template,
        private User $user,
    ) {}

    #[Override]
    public function route($params): void
    {
        if ($this->user->isGuest() || $this->user->get()->groupID === Groups::Banned->value) {
            $this->router->redirect('index');

            return;
        }

        $this->page->setBreadCrumbs([
            $this->router->url('ucp') => 'UCP',
        ]);
        $what = $params['what'] ?? $this->request->asString->both('what');

        // Not a single settings page needs update functionality except inbox
        if ($this->request->isJSUpdate()) {
            return;
        }

        $page = match ($what) {
            'avatar' => $this->showAvatarSettings(),
            'board' => $this->showBoardSettings(),
            'email' => $this->showEmailSettings(),
            'inbox' => $this->inbox->render(),
            'notifications' => $this->showNotifications(),
            'pass' => $this->showPassSettings(),
            'profile' => $this->showProfileSettings(),
            'signature' => $this->showSigSettings(),
            'sounds' => $this->showSoundSettings(),
            default => $this->showMain(),
        };

        if (!$page) {
            return;
        }

        $this->showucp($page);
    }

    private function showMain(): ?string
    {
        $error = null;

        if ($this->request->isJSUpdate()) {
            return null;
        }

        $ucpNotepad = $this->request->asString->post('ucpnotepad');
        if ($ucpNotepad !== null) {
            if (mb_strlen($ucpNotepad) > 2_000) {
                $error = 'The UCP notepad cannot exceed 2000 characters.';
                $this->page->command('error', $error);
            } else {
                $this->user->set('ucpnotepad', $ucpNotepad);
            }
        }

        return (
            ($error !== null ? $this->template->render('error', ['message' => $error]) : '')
            . $this->template->render('ucp/notepad', [
                'user' => $this->user->get(),
            ])
        );
    }

    private function showucp(string $page): void
    {
        $page = $this->template->render('ucp/index', [
            'page' => $page,
            'perms' => $this->user->getGroup(),
        ]);
        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
    }

    private function showSoundSettings(): string
    {
        $fields = [
            'soundShout',
            'soundIM',
            'soundPM',
            'notifyPM',
            'soundPostInMyTopic',
            'notifyPostInMyTopic',
            'soundPostInSubscribedTopic',
            'notifyPostInSubscribedTopic',
        ];

        if ($this->request->post('submit') !== null) {
            $update = [];
            foreach ($fields as $field) {
                $update[$field] = $this->request->post($field) !== null ? 1 : 0;
            }

            $this->user->setBulk($update);

            foreach ($fields as $field) {
                $this->page->command(
                    'script',
                    "window.globalSettings.{$field}=" . ($this->request->post($field) !== null ? 1 : 0),
                );
            }

            $this->page->command('success', 'Settings saved successfully.');
        }

        $this->page->command('script', <<<'JS'
                if (document.querySelector('#dtnotify') && window.webkitNotifications) {
                    document.querySelector('#dtnotify').checked=(webkitNotifications.checkPermission()==0)
                }
            JS);

        return $this->template->render('ucp/sound-settings', [
            'user' => $this->user->get(),
        ]);
    }

    private function showSigSettings(): string
    {
        $changeSig = $this->request->asString->post('changesig');
        if ($changeSig !== null) {
            $this->user->set('sig', $this->textFormatting->linkify($changeSig));
        }

        return $this->template->render('ucp/sig-settings', [
            'user' => $this->user->get(),
        ]);
    }

    private function showPassSettings(): string
    {
        $error = null;

        $currentPassword = $this->request->asString->post('curpass');
        $newPass1 = $this->request->asString->post('newpass1');
        $newPass2 = $this->request->asString->post('newpass2');
        $showPassword = (bool) $this->request->asString->post('showpass');

        if ($this->request->post('passchange') !== null) {
            if (!$showPassword && $newPass1 !== $newPass2) {
                $error = 'Those passwords do not match.';
            }

            if (!$newPass1 || !$showPassword && !$newPass2 || !$currentPassword) {
                $error = 'All form fields are required.';
            }

            $verifiedPassword = password_verify((string) $currentPassword, $this->user->get()->pass);
            if (!$verifiedPassword) {
                $error = 'The password you entered is incorrect.';
            }

            if ($error === null) {
                $hashpass = password_hash((string) $newPass1, PASSWORD_DEFAULT);
                $this->user->set('pass', $hashpass);
                $backURL = $this->router->url('ucp', ['what' => 'pass']);

                return <<<HTML
                    Password changed.
                        <br><br>
                        <a href="{$backURL}">Back</a>
                    HTML;
            }

            $this->page->command('error', $error);
        }

        return $this->template->render('ucp/pass-settings', [
            'error' => $error,
        ]);
    }

    private function showEmailSettings(): ?string
    {
        $error = null;
        $email = $this->request->asString->post('email');
        $notifications = (bool) $this->request->post('notifications');
        $adminEmails = (bool) $this->request->post('adminemails');

        if ($this->request->post('submit') !== null) {
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email!';
            }

            if ($error !== null) {
                $this->page->command('error', $error);

                return null;
            }

            $this->user->setBulk([
                'email' => $this->request->asString->post('email'),
                'emailSettings' => ($notifications ? 2 : 0) + ($adminEmails ? 1 : 0),
            ]);

            $emailSettingsURL = $this->router->url('ucp', ['what' => 'email']);

            return <<<HTML
                Email settings updated.
                <br><br>
                <a href="{$emailSettingsURL}">Back</a>
                HTML;
        }

        $emailSettings = $this->user->get()->emailSettings;

        return $this->template->render('ucp/email-settings', [
            'changeEmail' => $this->request->both('changeEmail'),
            'user' => $this->user->get(),
            'notificationsEnabled' => ($emailSettings & 2) !== 0,
            'adminEmailsEnabled' => ($emailSettings & 1) !== 0,
        ]);
    }

    private function saveAvatarSettings(string $newAvatar): ?string
    {
        if ($newAvatar && !filter_var($newAvatar, FILTER_VALIDATE_URL)) {
            return 'Please enter a valid image URL.';
        }

        $this->user->set('avatar', $newAvatar);

        return null;
    }

    private function showAvatarSettings(): string
    {
        $error = null;
        $changedAvatar = $this->request->asString->post('changedava');
        if ($changedAvatar !== null) {
            $error = $this->saveAvatarSettings($changedAvatar);
        }

        return $this->template->render('ucp/avatar-settings', [
            'error' => $error,
            'user' => $this->user->get(),
        ]);
    }

    /**
     * Returns string error or null for success.
     */
    private function updateProfileSettings(): ?string
    {
        // Insert the profile info into the database.
        $data = [
            'about' => $this->request->asString->post('about'),
            'contactAIM' => $this->request->asString->post('contactAIM'),
            'contactBlueSky' => $this->request->asString->post('contactBlueSky'),
            'contactDiscord' => $this->request->asString->post('contactDiscord'),
            'contactGoogleChat' => $this->request->asString->post('contactGoogleChat'),
            'contactMSN' => $this->request->asString->post('contactMSN'),
            'contactSkype' => $this->request->asString->post('contactSkype'),
            'contactSteam' => $this->request->asString->post('contactSteam'),
            'contactTwitter' => $this->request->asString->post('contactTwitter'),
            'contactYIM' => $this->request->asString->post('contactYIM'),
            'contactYoutube' => $this->request->asString->post('contactYoutube'),
            'displayName' => trim((string) $this->request->asString->post('displayName')),
            'dob_day' => (int) $this->request->asString->post('dob_day'),
            'dob_month' => (int) $this->request->asString->post('dob_month'),
            'dob_year' => (int) $this->request->asString->post('dob_year'),
            'fullName' => $this->request->asString->post('fullName'),
            'gender' => $this->request->asString->post('gender') ?? '',
            'location' => $this->request->asString->post('location'),
            'usertitle' => $this->request->asString->post('usertitle'),
            'website' => $this->request->asString->post('website'),
        ];

        // Begin input checking.
        if ($data['displayName'] === '') {
            $data['displayName'] = $this->user->get()->name;
        }

        $badNameChars = $this->config->getSetting('badnamechars');
        if ($badNameChars && preg_match($badNameChars, $data['displayName'])) {
            return 'Invalid characters in display name!';
        }

        $members = Member::selectMany(
            'WHERE `displayName` = ? AND `id`!=? LIMIT 1',
            $data['displayName'],
            $this->user->get()->id,
        );
        if ($members !== []) {
            return 'That display name is already in use.';
        }

        $data['birthdate'] = $data['dob_year'] || $data['dob_month']
            ? Carbon::create($data['dob_year'], $data['dob_month'], $data['dob_day'], 0, 0, 0, 'UTC')?->format(
                'Y-m-d H:i:s',
            )
            : null;
        unset($data['dob_day'], $data['dob_month'], $data['dob_year']);

        foreach ([
            'contactAIM' => 'AIM username',
            'contactBlueSky' => 'Bluesky username',
            'contactDiscord' => 'Discord ID',
            'contactGoogleChat' => 'Google Chat username',
            'contactMSN' => 'MSN username',
            'contactSkype' => 'Skype username',
            'contactSteam' => 'Steam username',
            'contactTwitter' => 'Twitter username',
            'contactYIM' => 'YIM username',
            'contactYoutube' => 'YouTube username',
            'displayName' => 'Display name',
            'fullName' => 'Full name',
            'location' => 'Location',
            'usertitle' => 'User Title',
            'website' => 'Website URL',
        ] as $field => $fieldLabel) {
            if (mb_strstr($field, 'contact') !== false && preg_match('/[^\w.@]/', (string) $data[$field])) {
                return "Invalid characters in {$fieldLabel}";
            }

            $data[$field] ??= '';
            $length = $field === 'displayName' ? 30 : ($field === 'location' ? 100 : 50);
            if (mb_strlen($data[$field]) <= $length) {
                continue;
            }

            return "{$fieldLabel} must be less than {$length} characters.";
        }

        if ($data['displayName'] !== $this->user->get()->displayName) {
            $activity = new Activity();
            $activity->arg1 = $this->user->get()->displayName;
            $activity->arg2 = $data['displayName'];
            $activity->date = $this->database->datetime();
            $activity->type = 'profile_name_change';
            $activity->uid = $this->user->get()->id;
            $activity->insert();
        }

        $this->user->setBulk($data);

        return null;
    }

    private function showProfileSettings(): string
    {
        if ($this->request->post('submit') !== null) {
            $updateResult = $this->updateProfileSettings();

            if (is_string($updateResult)) {
                $this->page->command('error', $updateResult);

                return $this->template->render('error', ['message' => $updateResult]);
            }

            $editProfileURL = $this->router->url('ucp', ['what' => 'profile']);

            return <<<HTML
                Profile successfully updated.
                <br><br>
                <a href="{$editProfileURL}">Back</a>
                HTML;
        }

        $birthdate = $this->user->get()->birthdate;
        $birthdate = $birthdate !== null ? $this->date->dateAsCarbon($birthdate) : null;

        return $this->template->render('ucp/profile-settings', [
            'user' => $this->user->get(),
            'birthdate' => $birthdate,
        ]);
    }

    private function saveBoardSettings(): ?string
    {
        $skinId = (int) $this->request->asString->both('skin');
        $itemsPerPage = (int) $this->request->post('itemsPerPage');
        $itemsPerPage = max(10, min(50, $itemsPerPage));

        $skin = Skin::selectOne($skinId);

        if ($skin === null) {
            return 'The skin chosen no longer exists.';
        }

        $this->user->setBulk([
            'nowordfilter' => $this->request->post('usewordfilter') ? 0 : 1,
            'skinID' => $skinId,
            'wysiwyg' => $this->request->post('wysiwyg') ? 1 : 0,
            'itemsPerPage' => $itemsPerPage,
        ]);

        if ($this->request->isJSAccess()) {
            $this->page->command('reload');

            return null;
        }

        $this->router->redirect('ucp', ['what' => 'board']);

        return null;
    }

    private function showBoardSettings(): string
    {
        $error = null;
        if ($this->request->both('submit') !== null) {
            $error = $this->saveBoardSettings();
        }

        $skins = $this->user->get()->groupID !== 2
            ? Skin::selectMany('WHERE `hidden`!=1 ORDER BY `title` ASC')
            : Skin::selectMany('ORDER BY `title` ASC');

        return $this->template->render('ucp/board-settings', [
            'error' => $error,
            'skins' => $skins,
            'user' => $this->user->get(),
        ]);
    }

    private function showNotifications(): string
    {
        return $this->template->render('ucp/notifications', [
            'notifications' => [],
        ]);
    }
}
