<?php

declare(strict_types=1);

namespace Jax\Routes;

use Carbon\Carbon;
use Jax\Config;
use Jax\Constants\Groups;
use Jax\Database\Database;
use Jax\Date;
use Jax\Interfaces\Route;
use Jax\Jax;
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

use function filter_var;
use function gmdate;
use function in_array;
use function is_string;
use function mb_strlen;
use function mb_strstr;
use function password_hash;
use function password_verify;
use function preg_match;
use function trim;
use function ucfirst;

use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_URL;
use const PASSWORD_DEFAULT;

final readonly class UCP implements Route
{
    public function __construct(
        private Config $config,
        private Database $database,
        private Date $date,
        private Jax $jax,
        private Inbox $inbox,
        private Page $page,
        private Request $request,
        private Router $router,
        private TextFormatting $textFormatting,
        private Template $template,
        private User $user,
    ) {
        $this->template->loadMeta('ucp');
    }

    public function route($params): void
    {
        if (
            $this->user->isGuest()
            || $this->user->get()->groupID === Groups::Banned->value
        ) {
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
            'sounds' => $this->showSoundSettings(),
            'signature' => $this->showSigSettings(),
            'pass' => $this->showPassSettings(),
            'email' => $this->showEmailSettings(),
            'avatar' => $this->showAvatarSettings(),
            'profile' => $this->showProfileSettings(),
            'board' => $this->showBoardSettings(),
            'inbox' => $this->inbox->render(),
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

        $ucpnotepad = $this->user->get()->ucpnotepad;

        return ($error !== null ? $this->template->meta('error', $error) : '') . $this->template->meta(
            'ucp-index',
            $this->jax->hiddenFormFields(['act' => 'ucp']),
            $this->user->get()->displayName,
            $this->user->get()->avatar ?: $this->template->meta('default-avatar'),
            trim($ucpnotepad) !== ''
                ? $this->textFormatting->blockhtml($ucpnotepad) : 'Personal notes go here.',
        );
    }

    private function showucp(string $page): void
    {
        $page = $this->template->meta(
            'ucp-wrapper',
            // Settings links
            $this->router->url('ucp'),
            $this->router->url('ucp', ['what' => 'pass']),
            $this->router->url('ucp', ['what' => 'email']),
            $this->router->url('ucp', ['what' => 'avatar']),
            $this->router->url('ucp', ['what' => 'signature']),
            $this->router->url('ucp', ['what' => 'profile']),
            $this->router->url('ucp', ['what' => 'sounds']),
            $this->router->url('ucp', ['what' => 'board']),
            // inbox links
            $this->router->url('ucp', ['what' => 'inbox', 'view' => 'compose']),
            $this->router->url('ucp', ['what' => 'inbox']),
            $this->router->url('ucp', ['what' => 'inbox', 'view' => 'sent']),
            $this->router->url('ucp', ['what' => 'inbox', 'view' => 'flagged']),
            $page,
        );
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
                    "window.globalSettings.{$field}="
                        . ($this->request->post($field) !== null ? 1 : 0),
                );
            }

            $this->page->command('alert', 'Settings saved successfully.');
        }

        $checkboxes = [
            $this->getlocationforform() . $this->jax->hiddenFormFields(
                ['submit' => 'true'],
            ),
        ];

        foreach ($fields as $field) {
            $checkboxes[] = '<input type="checkbox" title="' . $field . '" name="' . $field . '" '
                . ($this->user->get()->{$field} !== 0 ? 'checked="checked"' : '') . '/>';
        }

        $this->page->command('script', <<<'JS'
                if (document.querySelector('#dtnotify') && window.webkitNotifications) {
                    document.querySelector('#dtnotify').checked=(webkitNotifications.checkPermission()==0)
                }
            JS);

        return $this->template->meta('ucp-sound-settings', ...$checkboxes);
    }

    private function showSigSettings(): string
    {
        $sig = $this->user->get()->sig;
        $changeSig = $this->request->asString->post('changesig');
        if ($changeSig !== null) {
            $sig = $this->textFormatting->linkify($changeSig);
            $this->user->set('sig', $sig);
        }

        return $this->template->meta(
            'ucp-sig-settings',
            $this->getlocationforform(),
            $sig !== ''
                ? $this->textFormatting->theWorks($sig) : '( none )',
            $this->textFormatting->blockhtml($sig),
        );
    }

    private function showPassSettings(): string
    {
        $error = null;
        $page = '';

        $currentPassword = $this->request->asString->post('curpass');
        $newPass1 = $this->request->asString->post('newpass1');
        $newPass2 = $this->request->asString->post('newpass2');
        $showPassword = (bool) $this->request->asString->post('showpass');

        if ($this->request->post('passchange') !== null) {
            if (!$showPassword && $newPass1 !== $newPass2) {
                $error = 'Those passwords do not match.';
            }

            if (
                !$newPass1
                || (!$showPassword && !$newPass2)
                || !$currentPassword
            ) {
                $error = 'All form fields are required.';
            }

            $verifiedPassword = password_verify(
                (string) $currentPassword,
                $this->user->get()->pass,
            );
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

            $page .= $this->template->meta('error', $error);
            $this->page->command('error', $error);
        }

        return $page . $this->template->meta(
            'ucp-pass-settings',
            $this->getlocationforform()
                . $this->jax->hiddenFormFields(['passchange' => 'true']),
        );
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
                $this->page->command('alert', $error);

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

        $email = $this->user->get()->email;
        $emailSettings = $this->user->get()->emailSettings;
        $notificationsChecked = ($emailSettings & 2) !== 0 ? 'checked' : '';
        $adminEmailsChecked = ($emailSettings & 1) !== 0 ? 'checked' : '';
        $changeEmailURL = $this->router->url('ucp', ['what' => 'email', 'change' => '1']);

        return $this->template->meta(
            'ucp-email-settings',
            $this->jax->hiddenFormFields(
                ['submit' => 'true'],
            ),
            match (true) {
                $this->request->both('change') !== null => <<<HTML
                    <input
                        type="text"
                        name="email"
                        aria-label="Email"
                        title="Enter your new email address"
                        value="{$this->user->get()->email}">
                    HTML,
                (bool) $email => <<<HTML
                    <strong>{$email}</strong>
                    <a href='{$changeEmailURL}'>Change</a>
                    <input type='hidden' name='email' value='{$email}'>
                    HTML,

                default => '--none--',
            },
            <<<HTML
                <input type="checkbox" title="Notifications" name="notifications" {$notificationsChecked}>
                HTML,
            <<<HTML
                <input type="checkbox" title="Admin Emails" name="adminemails" {$adminEmailsChecked}>
                HTML,
        );
    }

    private function showAvatarSettings(): string
    {
        $error = null;
        $avatar = $this->user->get()->avatar;
        $changedAvatar = $this->request->asString->post('changedava');
        if ($changedAvatar !== null) {
            if (
                $changedAvatar
                && !filter_var($changedAvatar, FILTER_VALIDATE_URL)
            ) {
                $error = 'Please enter a valid image URL.';
            } else {
                $avatar = $changedAvatar;
                $this->user->set('avatar', $avatar);
            }
        }

        $avatarURL = $avatar ?: trim($this->template->meta('default-avatar'));
        $errorDisplay = $error !== null ? $this->page->error($error) : '';
        $avatarInputValue = $this->textFormatting->blockhtml($avatar);

        return <<<HTML
            Your avatar: <span class="avatar"><img src="{$avatarURL}" alt="Your avatar"></span>
            <br><br>
            <form data-ajax-form="true" method="post">
                {$errorDisplay}
                <input type="text" name="changedava" title="Your avatar" value="{$avatarInputValue}">
                <input type="submit" value="Edit">
            </form>
            HTML;
    }

    /**
     * Returns string error or null for success.
     */
    private function updateProfileSettings(): ?string
    {
        $genderOptions = ['', 'male', 'female', 'other'];
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
            'full_name' => $this->request->asString->post('full_name'),
            'gender' => in_array($this->request->asString->post('gender'), $genderOptions, true)
                ? $this->request->asString->post('gender') : '',
            'location' => $this->request->asString->post('location'),
            'usertitle' => $this->request->asString->post('usertitle'),
            'website' => $this->request->asString->post('website'),
        ];

        // Begin input checking.
        if ($data['displayName'] === '') {
            $data['displayName'] = $this->user->get()->name;
        }

        $badNameChars = $this->config->getSetting('badnamechars');
        if (
            $badNameChars
            && preg_match($badNameChars, $data['displayName'])
        ) {
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
            ? Carbon::create($data['dob_year'], $data['dob_month'], $data['dob_day'], 0, 0, 0, 'UTC')?->format('Y-m-d H:i:s')
            : null;
        unset($data['dob_day'], $data['dob_month'], $data['dob_year']);

        foreach (
            [
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
                'full_name' => 'Full name',
                'location' => 'Location',
                'usertitle' => 'User Title',
                'website' => 'Website URL',
            ] as $field => $fieldLabel
        ) {
            if (
                mb_strstr($field, 'contact') !== false
                && preg_match('/[^\w.@]/', (string) $data[$field])
            ) {
                return "Invalid characters in {$fieldLabel}";
            }

            $data[$field] = $this->textFormatting->blockhtml($data[$field] ?? '');
            $length = $field === 'displayName'
                ? 30
                : ($field === 'location' ? 100 : 50);
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

                return $this->template->meta('error', $updateResult);
            }

            $editProfileURL = $this->router->url('ucp', ['what' => 'profile']);

            return <<<HTML
                Profile successfully updated.
                <br><br>
                <a href="{$editProfileURL}">Back</a>
                HTML;
        }

        $genderselect = '<select name="gender" title="Your gender" aria-label="Gender">';
        foreach (['', 'male', 'female', 'other'] as $gender) {
            $genderSelected = $this->user->get()->gender === $gender
                ? 'selected'
                : '';
            $genderDisplay = ucfirst($gender) ?: 'Not telling';
            $genderselect .= <<<HTML
                <option value="{$gender}" {$genderSelected}>{$genderDisplay}</option>
                HTML;
        }

        $genderselect .= '</select>';

        $dobselect = '<select name="dob_month" title="Month"><option value="">--</option>';
        $fullMonthNames = [
            'January',
            'February',
            'March',
            'April',
            'May',
            'June',
            'July',
            'August',
            'September',
            'October',
            'November',
            'December',
        ];

        $birthdate = $this->user->get()->birthdate;
        $birthdate = $birthdate !== null
            ? $this->date->dateAsCarbon($birthdate)
            : null;

        foreach ($fullMonthNames as $index => $monthName) {
            $dobselect .= '<option value="' . ($index + 1) . '"'
                . ($index + 1 === $birthdate?->month ? ' selected="selected"' : '')
                . '>' . $monthName . '</option>';
        }

        $dobselect .= '</select><select name="dob_day" title="Day"><option value="">--</option>';
        for ($day = 1; $day < 32; ++$day) {
            $dobselect .= '<option value="' . $day . '"'
                . ($day === $birthdate?->day ? ' selected="selected"' : '')
                . '>' . $day . '</option>';
        }

        $dobselect .= '</select><select name="dob_year" title="Year">'
            . '<option value="">--</option>';

        $thisyear = (int) gmdate('Y');
        for ($year = $thisyear; $year > $thisyear - 100; --$year) {
            $dobselect .= '<option value="' . $year . '"'
                . ($year === $birthdate?->year ? ' selected="selected"' : '')
                . '>' . $year . '</option>';
        }

        $dobselect .= '</select>';

        return $this->template->meta(
            'ucp-profile-settings',
            $this->getlocationforform()
                . $this->jax->hiddenFormFields(['submit' => 'true']),
            $this->user->get()->name,
            $this->user->get()->displayName,
            $this->user->get()->full_name,
            $this->user->get()->usertitle,
            $this->user->get()->about,
            $this->user->get()->location,
            $genderselect,
            $dobselect,
            $this->user->get()->contactSkype,
            $this->user->get()->contactDiscord,
            $this->user->get()->contactYIM,
            $this->user->get()->contactMSN,
            $this->user->get()->contactGoogleChat,
            $this->user->get()->contactAIM,
            $this->user->get()->contactYoutube,
            $this->user->get()->contactSteam,
            $this->user->get()->contactTwitter,
            $this->user->get()->contactBlueSky,
            $this->user->get()->website,
        );
    }

    private function saveBoardSettings(): ?string
    {
        $skinId = (int) $this->request->asString->both('skin');

        $skin = Skin::selectOne($skinId);

        if ($skin === null) {
            return 'The skin chosen no longer exists.';
        }

        $this->user->setBulk([
            'nowordfilter' => $this->request->post('usewordfilter') ? 0 : 1,
            'skinID' => $skinId,
            'wysiwyg' => $this->request->post('wysiwyg') ? 1 : 0,
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
        $skinId = $this->user->get()->skinID;
        $page = '';
        if ($this->request->both('skin') !== null) {
            $error = $this->saveBoardSettings();
            if ($error) {
                $page .= $this->template->meta('error', $error);
            }
        }

        $skins = $this->user->get()->groupID !== 2
            ? Skin::selectMany('WHERE `hidden`!=1 ORDER BY `title` ASC')
            : Skin::selectMany('ORDER BY `title` ASC');
        $select = '';
        foreach ($skins as $skin) {
            $select .= "<option value='" . $skin->id . "' "
                . ($skinId === $skin->id ? "selected='selected'" : '')
                . '/>' . ($skin->hidden ? '*' : '') . $skin->title . '</option>';
        }

        $select = '<select name="skin" title="Board Skin">' . $select . '</select>';
        if ($skins === []) {
            $select = '--No Skins--';
        }

        return $page . $this->template->meta(
            'ucp-board-settings',
            '',
            $select,
            '<input type="checkbox" name="usewordfilter" title="Use Word Filter"'
                . ($this->user->get()->nowordfilter !== 0 ? '' : ' checked="checked"')
                . '>',
            '<input type="checkbox" name="wysiwyg" title="WYSIWYG Enabled"'
                . ($this->user->get()->wysiwyg !== 0 ? ' checked="checked"' : '')
                . '>',
        );
    }
}
