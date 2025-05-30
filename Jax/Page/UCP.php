<?php

declare(strict_types=1);

namespace Jax\Page;

use Carbon\Carbon;
use Jax\Config;
use Jax\Constants\Groups;
use Jax\Database;
use Jax\Jax;
use Jax\Models\Activity;
use Jax\Models\Member;
use Jax\Models\Skin;
use Jax\Page;
use Jax\Page\UCP\Inbox;
use Jax\Request;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function filter_var;
use function gmdate;
use function header;
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

final readonly class UCP
{
    public function __construct(
        private Config $config,
        private Database $database,
        private Jax $jax,
        private Inbox $inbox,
        private Page $page,
        private Request $request,
        private TextFormatting $textFormatting,
        private Template $template,
        private User $user,
    ) {
        $this->template->loadMeta('ucp');
    }

    public function render(): void
    {
        if (
            $this->user->isGuest()
            || $this->user->get()->group_id === Groups::Banned->value
        ) {
            $this->page->location('?');

            return;
        }

        $this->page->setBreadCrumbs(['?act=ucp' => 'UCP']);
        $what = $this->request->asString->both('what');

        // Not a single settings page needs update functionality except inbox
        if (
            $this->request->isJSUpdate()
            && !$this->request->hasPostData()
        ) {
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

    private function getlocationforform(): string
    {
        return $this->jax->hiddenFormFields([
            'act' => 'ucp',
            'what' => $this->request->asString->both('what') ?? '',
        ]);
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

        $ucpnotepad = (string) $this->user->get()->ucpnotepad;

        return ($error !== null ? $this->template->meta('error', $error) : '') . $this->template->meta(
            'ucp-index',
            $this->jax->hiddenFormFields(['act' => 'ucp']),
            $this->user->get()->display_name,
            $this->user->get()->avatar ?: $this->template->meta('default-avatar'),
            trim($ucpnotepad) !== ''
                ? $this->textFormatting->blockhtml($ucpnotepad) : 'Personal notes go here.',
        );
    }

    private function showucp(string $page): void
    {
        $page = $this->template->meta('ucp-wrapper', $page);
        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
    }

    private function showSoundSettings(): string
    {
        $fields = [
            'sound_shout',
            'sound_im',
            'sound_pm',
            'notify_pm',
            'sound_postinmytopic',
            'notify_postinmytopic',
            'sound_postinsubscribedtopic',
            'notify_postinsubscribedtopic',
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
                    "window.globalsettings.{$field}="
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
                . ($this->user->get($field) ? 'checked="checked"' : '') . '/>';
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
        $sig = (string) $this->user->get()->sig;
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
                (string) $this->user->get()->pass,
            );
            if (!$verifiedPassword) {
                $error = 'The password you entered is incorrect.';
            }

            if ($error === null) {
                $hashpass = password_hash((string) $newPass1, PASSWORD_DEFAULT);
                $this->user->set('pass', $hashpass);

                return <<<'HTML'
                    Password changed.
                        <br><br>
                        <a href="?act=ucp&what=pass">Back</a>
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
                'email_settings' => ($notifications ? 2 : 0) + ($adminEmails ? 1 : 0),
            ]);

            return 'Email settings updated.'
                . '<br><br><a href="?act=ucp&what=email">Back</a>';
        }

        $email = $this->user->get()->email;
        $emailSettings = (int) $this->user->get()->email_settings;
        $notificationsChecked = ($emailSettings & 2) !== 0 ? 'checked' : '';
        $adminEmailsChecked = ($emailSettings & 1) !== 0 ? 'checked' : '';

        return $this->template->meta(
            'ucp-email-settings',
            $this->getlocationforform() . $this->jax->hiddenFormFields(
                ['submit' => 'true'],
            ),
            match (true) {
                $this->request->both('change') !== null => <<<HTML
                    <input
                        type="text"
                        name="email"
                        aria-label="Email"
                        title="Enter your new email address"
                        value="{$this->user->get()->email}" />
                    HTML,
                (bool) $email => <<<HTML
                    <strong>{$email}</strong>
                    <a href='?act=ucp&what=email&change=1'>Change</a>
                    <input type='hidden' name='email' value='{$email}' />
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
        $avatar = (string) $this->user->get()->avatar;
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

        $avatarURL = $avatar ?: $this->template->meta('default-avatar');
        $locationForForm = $this->getlocationforform();
        $errorDisplay = $error !== null ? $this->page->error($error) : '';
        $avatarInputValue = $this->textFormatting->blockhtml($avatar);

        return <<<HTML
            Your avatar: <span class="avatar"><img src="{$avatarURL}" alt="Your avatar"></span>
            <br><br>
            <form data-ajax-form="true" method="post">
                {$locationForForm}
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
            'contact_aim' => $this->request->asString->post('con_aim'),
            'contact_bluesky' => $this->request->asString->post('con_bluesky'),
            'contact_discord' => $this->request->asString->post('con_discord'),
            'contact_gtalk' => $this->request->asString->post('con_gtalk'),
            'contact_msn' => $this->request->asString->post('con_msn'),
            'contact_skype' => $this->request->asString->post('con_skype'),
            'contact_steam' => $this->request->asString->post('con_steam'),
            'contact_twitter' => $this->request->asString->post('con_twitter'),
            'contact_yim' => $this->request->asString->post('con_yim'),
            'contact_youtube' => $this->request->asString->post('con_youtube'),
            'display_name' => trim((string) $this->request->asString->post('display_name')),
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
        if ($data['display_name'] === '') {
            $data['display_name'] = (string) $this->user->get()->name;
        }

        $badNameChars = $this->config->getSetting('badnamechars');
        if (
            $badNameChars
            && preg_match($badNameChars, $data['display_name'])
        ) {
            return 'Invalid characters in display name!';
        }

        $members = Member::selectMany(
            $this->database,
            'WHERE `display_name` = ? AND `id`!=? LIMIT 1',
            $data['display_name'],
            $this->user->get()->id,
        );
        if ($members !== []) {
            return 'That display name is already in use.';
        }

        $data['dob_year']
            = $data['dob_year'] < 1 || $data['dob_year'] > (int) gmdate('Y')
            ? null
            : gmdate(
                'Y',
                Carbon::create($data['dob_year'], 1, 1)?->getTimestamp(),
            );

        $data['dob_month']
            = $data['dob_month'] < 1 || $data['dob_month'] > 12
            ? null : gmdate(
                'm',
                Carbon::create(2000, $data['dob_month'], 1)?->getTimestamp(),
            );

        $data['dob_day']
            = $data['dob_day'] < 1
            ? null : gmdate(
                'd',
                Carbon::create(2000, 1, $data['dob_day'])?->getTimestamp(),
            );

        // Is the date provided valid?
        if ($data['dob_month'] && $data['dob_day']) {
            // Feb 29th check for leap years
            if ((int) $data['dob_month'] === 2) {
                if (
                    $data['dob_year'] > 0
                    && gmdate('L', Carbon::create($data['dob_year'])?->getTimestamp())
                ) {
                    $daysInMonth = 29;
                } elseif ($data['dob_year'] > 0) {
                    $daysInMonth = 28;
                } else {
                    // If we don't know the year, we can
                    // let it be a leap year.
                    $daysInMonth = 29;
                }
            } else {
                $daysInMonth = (int) gmdate(
                    't',
                    Carbon::create($data['dob_month'], 1)?->getTimestamp(),
                );
            }

            if ($data['dob_day'] > $daysInMonth) {
                return "That birth date doesn't exist!";
            }
        }

        $data['birthdate'] = !$data['dob_year'] && !$data['dob_month']
            ? null
            : ($data['dob_year'] ?? '0000') . '-' . ($data['dob_month'] ?? '00') . '-' . ($data['dob_day'] ?? '00');

        unset($data['dob_year'], $data['dob_month'], $data['dob_day']);

        foreach (
            [
                'contact_aim' => 'AIM username',
                'contact_bluesky' => 'Bluesky username',
                'contact_discord' => 'Discord username',
                'contact_gtalk' => 'Google Chat username',
                'contact_msn' => 'MSN username',
                'contact_skype' => 'Skype username',
                'contact_steam' => 'Steam username',
                'contact_twitter' => 'Twitter username',
                'contact_yim' => 'YIM username',
                'contact_youtube' => 'YouTube username',
                'display_name' => 'Display name',
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
            $length = $field === 'display_name'
                ? 30
                : ($field === 'location' ? 100 : 50);
            if (mb_strlen($data[$field]) <= $length) {
                continue;
            }

            return "{$fieldLabel} must be less than {$length} characters.";
        }

        if ($data['display_name'] !== $this->user->get()->display_name) {
            $activity = new Activity();
            $activity->arg1 = (string) $this->user->get()->display_name;
            $activity->arg2 = $data['display_name'];
            $activity->date = $this->database->datetime();
            $activity->type = 'profile_name_change';
            $activity->uid = (int) $this->user->get()->id;
            $activity->insert($this->database);
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

            return 'Profile successfully updated.<br>'
                . '<br><a href="?act=ucp&what=profile">Back</a>';
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
        foreach ($fullMonthNames as $index => $monthName) {
            $dobselect .= '<option value="' . ($index + 1) . '"'
                . ($index + 1 === $this->user->get()->dob_month ? ' selected="selected"' : '')
                . '>' . $monthName . '</option>';
        }

        $dobselect .= '</select><select name="dob_day" title="Day"><option value="">--</option>';
        for ($day = 1; $day < 32; ++$day) {
            $dobselect .= '<option value="' . $day . '"'
                . ($day === $this->user->get()->dob_day ? ' selected="selected"' : '')
                . '>' . $day . '</option>';
        }

        $dobselect .= '</select><select name="dob_year" title="Year">'
            . '<option value="">--</option>';
        $thisyear = (int) gmdate('Y');
        for ($year = $thisyear; $year > $thisyear - 100; --$year) {
            $dobselect .= '<option value="' . $year . '"'
                . ($year === $this->user->get()->dob_year ? ' selected="selected"' : '')
                . '>' . $year . '</option>';
        }

        $dobselect .= '</select>';

        return $this->template->meta(
            'ucp-profile-settings',
            $this->getlocationforform()
                . $this->jax->hiddenFormFields(['submit' => 'true']),
            $this->user->get()->name,
            $this->user->get()->display_name,
            $this->user->get()->full_name,
            $this->user->get()->usertitle,
            $this->user->get()->about,
            $this->user->get()->location,
            $genderselect,
            $dobselect,
            $this->user->get()->contact_skype,
            $this->user->get()->contact_discord,
            $this->user->get()->contact_yim,
            $this->user->get()->contact_msn,
            $this->user->get()->contact_gtalk,
            $this->user->get()->contact_aim,
            $this->user->get()->contact_youtube,
            $this->user->get()->contact_steam,
            $this->user->get()->contact_twitter,
            $this->user->get()->contact_bluesky,
            $this->user->get()->website,
        );
    }

    private function saveBoardSettings(): ?string
    {
        $skinId = (int) $this->request->asString->both('skin');

        $skin = Skin::selectOne(
            $this->database,
            Database::WHERE_ID_EQUALS,
            $skinId,
        );

        if ($skin === null) {
            return 'The skin chosen no longer exists.';
        }

        $this->user->setBulk([
            'nowordfilter' => $this->request->post('usewordfilter') ? 0 : 1,
            'skin_id' => $skinId,
            'wysiwyg' => $this->request->post('wysiwyg') ? 1 : 0,
        ]);

        if ($this->request->isJSAccess()) {
            $this->page->command('reload');

            return null;
        }

        header('Location: ?act=ucp&what=board');

        return null;
    }

    private function showBoardSettings(): string
    {
        $error = null;
        $skinId = $this->user->get()->skin_id;
        $page = '';
        if ($this->request->both('skin') !== null) {
            $error = $this->saveBoardSettings();
            if ($error) {
                $page .= $this->template->meta('error', $error);
            }
        }

        $skins = $this->user->get()->group_id !== 2
            ? Skin::selectMany($this->database, 'WHERE `hidden`!=1 ORDER BY `title` ASC')
            : Skin::selectMany($this->database, 'ORDER BY `title` ASC');
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
            $this->getlocationforform(),
            $select,
            '<input type="checkbox" name="usewordfilter" title="Use Word Filter"'
                . ($this->user->get()->nowordfilter ? '' : ' checked="checked"')
                . ' />',
            '<input type="checkbox" name="wysiwyg" title="WYSIWYG Enabled"'
                . ($this->user->get()->wysiwyg ? ' checked="checked"' : '')
                . ' />',
        );
    }
}
