<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\Page;
use Jax\Page\UCP\Inbox;
use Jax\Request;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function gmdate;
use function header;
use function in_array;
use function is_numeric;
use function mb_strlen;
use function mb_strstr;
use function password_hash;
use function password_verify;
use function preg_match;
use function strtotime;
use function trim;
use function ucfirst;

use const PASSWORD_DEFAULT;

final class UCP
{
    private string $what = '';

    private ?string $runscript = null;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Inbox $inbox,
        private readonly Page $page,
        private readonly Request $request,
        private readonly TextFormatting $textFormatting,
        private readonly Template $template,
        private readonly User $user,
    ) {
        $this->template->loadMeta('ucp');
    }

    public function render(): void
    {
        if ($this->user->isGuest() || $this->user->get('group_id') === 4) {
            $this->page->location('?');

            return;
        }

        $this->page->setBreadCrumbs(['UCP' => '?act=ucp']);
        $this->what = $this->request->both('what') ?? '';

        // Not a single settings page needs update functionality except inbox
        if (
            $this->request->isJSUpdate()
            && !$this->request->hasPostData()
            && $this->what !== 'inbox'
        ) {
            return;
        }

        $page = match ($this->what) {
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

        $this->showucp($page);
    }

    private function getlocationforform(): string
    {
        return $this->jax->hiddenFormFields(['act' => 'ucp', 'what' => $this->what]);
    }

    private function showMain(): ?string
    {
        $error = null;

        if ($this->request->isJSUpdate() && !$this->request->hasPostData()) {
            return null;
        }

        if (
            $this->request->post('ucpnotepad') !== null
        ) {
            if (mb_strlen((string) $this->request->post('ucpnotepad')) > 2_000) {
                $error = 'The UCP notepad cannot exceed 2000 characters.';
                $this->page->command('error', $error);
            } else {
                $this->user->set('ucpnotepad', $this->request->post('ucpnotepad'));
            }
        }

        $ucpnotepad = $this->user->get('ucpnotepad');

        return ($error !== null ? $this->template->meta('error', $error) : '') . $this->template->meta(
            'ucp-index',
            $this->jax->hiddenFormFields(['act' => 'ucp']),
            $this->user->get('display_name'),
            $this->user->get('avatar') ?: $this->template->meta('default-avatar'),
            trim((string) $ucpnotepad) !== '' && trim((string) $ucpnotepad) !== '0'
            ? $this->textFormatting->blockhtml($ucpnotepad) : 'Personal notes go here.',
        );
    }

    private function showucp($page = false): void
    {
        $page = $this->template->meta('ucp-wrapper', $page);
        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
        if (!$this->runscript) {
            return;
        }

        $this->page->command('script', $this->runscript);
    }

    private function showSoundSettings(): ?string
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

        $this->runscript = "if(document.querySelector('#dtnotify')&&window.webkitNotifications) "
        . "document.querySelector('#dtnotify').checked=(webkitNotifications.checkPermission()==0)";

        return $this->template->meta('ucp-sound-settings', $checkboxes);
    }

    private function showSigSettings(): string
    {
        $sig = $this->user->get('sig');
        if ($this->request->post('changesig') !== null) {
            $sig = $this->textFormatting->linkify($this->request->post('changesig'));
            $this->user->set('sig', $sig);
        }

        return $this->template->meta(
            'ucp-sig-settings',
            $this->getlocationforform(),
            $sig !== ''
            ? $this->textFormatting->theworks($sig) : '( none )',
            $this->textFormatting->blockhtml($sig),
        );
    }

    private function showPassSettings(): string
    {
        $error = null;
        $page = '';
        if ($this->request->post('passchange') !== null) {
            if (
                !$this->request->post('showpass')
                && $this->request->post('newpass1') !== $this->request->post('newpass2')
            ) {
                $error = 'Those passwords do not match.';
            }

            if (
                !$this->request->post('newpass1')
                || !$this->request->post('showpass')
                && !$this->request->post('newpass2')
                || !$this->request->post('curpass')
            ) {
                $error = 'All form fields are required.';
            }

            $verifiedPassword = password_verify(
                (string) $this->request->post('curpass'),
                (string) $this->user->get('pass'),
            );
            if (!$verifiedPassword) {
                $error = 'The password you entered is incorrect.';
            }

            if ($error === null) {
                $hashpass = password_hash((string) $this->request->post('newpass1'), PASSWORD_DEFAULT);
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
        if ($this->request->post('submit') !== null) {
            if (
                $this->request->post('email')
                && !$this->jax->isemail($this->request->post('email'))
            ) {
                $error = 'Please enter a valid email!';
            }

            if ($error !== null) {
                $this->page->command('alert', $error);

                return null;
            }

            $this->user->setBulk([
                'email' => $this->request->post('email'),
                'email_settings' => ($this->request->post('notifications') ?? false ? 2 : 0)
                + ($this->request->post('adminemails') ?? false ? 1 : 0),
            ]);

            return 'Email settings updated.'
                . '<br><br><a href="?act=ucp&what=email">Back</a>';
        }

        $email = $this->user->get('email');
        $emailSettings = $this->user->get('email_settings');
        $notificationsChecked = $emailSettings & 2 !== 0 ? 'checked' : '';
        $adminEmailsChecked = $emailSettings & 1 !== 0 ? 'checked' : '';

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
                        value="{$this->user->get('email')}" />
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

    private function showAvatarSettings(): ?string
    {
        $error = null;
        $avatar = $this->user->get('avatar');
        if ($this->request->post('changedava') !== null) {
            if (
                $this->request->post('changedava')
                && !$this->jax->isurl($this->request->post('changedava'))
            ) {
                $error = 'Please enter a valid image URL.';
            } else {
                $this->user->set('avatar', $this->request->post('changedava'));
                $avatar = $this->request->post('changedava');
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

    private function showProfileSettings(): ?string
    {
        $error = null;
        $genderOptions = ['', 'male', 'female', 'other'];
        if ($this->request->post('submit') !== null) {
            // Insert the profile info into the database.
            $data = [
                'about' => $this->request->post('about'),
                'contact_aim' => $this->request->post('con_aim'),
                'contact_bluesky' => $this->request->post('con_bluesky'),
                'contact_discord' => $this->request->post('con_discord'),
                'contact_gtalk' => $this->request->post('con_gtalk'),
                'contact_msn' => $this->request->post('con_msn'),
                'contact_skype' => $this->request->post('con_skype'),
                'contact_steam' => $this->request->post('con_steam'),
                'contact_twitter' => $this->request->post('con_twitter'),
                'contact_yim' => $this->request->post('con_yim'),
                'contact_youtube' => $this->request->post('con_youtube'),
                'display_name' => trim((string) $this->request->post('display_name')),
                'dob_day' => $this->request->post('dob_day'),
                'dob_month' => $this->request->post('dob_month'),
                'dob_year' => $this->request->post('dob_year'),
                'full_name' => $this->request->post('full_name'),
                'gender' => in_array($this->request->post('gender'), $genderOptions, true)
                ? $this->request->post('gender') : '',
                'location' => $this->request->post('location'),
                'usertitle' => $this->request->post('usertitle'),
                'website' => $this->request->post('website'),
            ];

            // Begin input checking.
            if ($data['display_name'] === '') {
                $data['display_name'] = $this->user->get('name');
            }

            $badNameChars = $this->config->getSetting('badnamechars');
            if (
                $badNameChars
                && preg_match($badNameChars, (string) $data['display_name'])
            ) {
                $error = 'Invalid characters in display name!';
            } else {
                $result = $this->database->safeselect(
                    'COUNT(`id`) AS `same_display_name`',
                    'members',
                    'WHERE `display_name` = ? AND `id`!=? LIMIT 1',
                    $this->database->basicvalue($data['display_name']),
                    $this->user->get('id'),
                );
                $displayNameCheck = $this->database->arow($result);
                if ($displayNameCheck['same_display_name'] > 0) {
                    $error = 'That display name is already in use.';
                }
            }

            $data['dob_year']
                = !$data['dob_year']
                || !is_numeric($data['dob_year'])
                || $data['dob_year'] < 1
                || $data['dob_year'] > (int) gmdate('Y')
             ? null : gmdate(
                 'Y',
                 strtotime($data['dob_year'] . '/1/1'),
             );

            $data['dob_month']
                = !$data['dob_month']
                || !is_numeric($data['dob_month'])
                || $data['dob_month'] < 1
                || $data['dob_month'] > 12
             ? null : gmdate(
                 'm',
                 strtotime('2000/' . $data['dob_month'] . '/1'),
             );

            $data['dob_day']
                = !$data['dob_day']
                || !is_numeric($data['dob_day'])
                || $data['dob_day'] < 1
             ? null : gmdate(
                 'd',
                 strtotime('2000/1/' . $data['dob_day']),
             );

            // Is the date provided valid?
            if ($data['dob_month'] && $data['dob_day']) {
                // Feb 29th check for leap years
                if ((int) $data['dob_month'] === 2) {
                    if (
                        $data['dob_year'] > 0
                        && gmdate('L', strtotime($data['dob_year']))
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
                        strtotime($data['dob_month'] . '/1'),
                    );
                }

                if ($data['dob_day'] > $daysInMonth) {
                    $error = "That birth date doesn't exist!";
                }
            }

            if (
                !$data['dob_year']
                && !$data['dob_month']
            ) {
                // User provided no birthdate, just set field to null
                $data['birthdate'] = null;
            } else {
                $data['birthdate'] = ($data['dob_year'] ?? '0000') . '-'
                    . ($data['dob_month'] ?? '00') . '-'
                    . ($data['dob_day'] ?? '00');
            }

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
                    $error = "Invalid characters in {$fieldLabel}";
                }

                $data[$field] = $this->textFormatting->blockhtml($data[$field]);
                $length = $field === 'display_name'
                    ? 30
                    : ($field === 'location' ? 100 : 50);
                if (mb_strlen($data[$field]) <= $length) {
                    continue;
                }

                $error = "{$fieldLabel} must be less than {$length} characters.";
            }

            // Handle errors/insert.
            if ($error === null) {
                if ($data['display_name'] !== $this->user->get('display_name')) {
                    $this->database->safeinsert(
                        'activity',
                        [
                            'arg1' => $this->user->get('display_name'),
                            'arg2' => $data['display_name'],
                            'date' => $this->database->datetime(),
                            'type' => 'profile_name_change',
                            'uid' => $this->user->get('id'),
                        ],
                    );
                }

                $this->user->setBulk($data);

                return 'Profile successfully updated.<br>'
                    . '<br><a href="?act=ucp&what=profile">Back</a>';
            }

            $this->page->command('error', $error);

            return $this->template->meta('error', $error);
        }

        $genderselect = '<select name="gender" title="Your gender" aria-label="Gender">';
        foreach (['', 'male', 'female', 'other'] as $gender) {
            $genderSelected = $this->user->get('gender') === $gender
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
                . ($index + 1 === $this->user->get('dob_month') ? ' selected="selected"' : '')
                . '>' . $monthName . '</option>';
        }

        $dobselect .= '</select><select name="dob_day" title="Day"><option value="">--</option>';
        for ($day = 1; $day < 32; ++$day) {
            $dobselect .= '<option value="' . $day . '"'
                . ($day === $this->user->get('dob_day') ? ' selected="selected"' : '')
                . '>' . $day . '</option>';
        }

        $dobselect .= '</select><select name="dob_year" title="Year">'
            . '<option value="">--</option>';
        $thisyear = (int) gmdate('Y');
        for ($year = $thisyear; $year > $thisyear - 100; --$year) {
            $dobselect .= '<option value="' . $year . '"'
                . ($year === $this->user->get('dob_year') ? ' selected="selected"' : '')
                . '>' . $year . '</option>';
        }

        $dobselect .= '</select>';

        return $this->template->meta(
            'ucp-profile-settings',
            $this->getlocationforform()
            . $this->jax->hiddenFormFields(['submit' => 'true']),
            $this->user->get('name'),
            $this->user->get('display_name'),
            $this->user->get('full_name'),
            $this->user->get('usertitle'),
            $this->user->get('about'),
            $this->user->get('location'),
            $genderselect,
            $dobselect,
            $this->user->get('contact_skype'),
            $this->user->get('contact_discord'),
            $this->user->get('contact_yim'),
            $this->user->get('contact_msn'),
            $this->user->get('contact_gtalk'),
            $this->user->get('contact_aim'),
            $this->user->get('contact_youtube'),
            $this->user->get('contact_steam'),
            $this->user->get('contact_twitter'),
            $this->user->get('contact_bluesky'),
            $this->user->get('website'),
        );
    }

    private function showBoardSettings(): ?string
    {
        $error = null;
        $skinId = $this->user->get('skin_id');
        $page = '';
        if (
            is_numeric($this->request->both('skin'))
        ) {
            $result = $this->database->safeselect(
                [
                    'id',
                    '`using`',
                    'title',
                    'custom',
                    'wrapper',
                    '`default`',
                    'hidden',
                ],
                'skins',
                'WHERE `id`=?',
                $this->request->both('skin'),
            );
            if (!$this->database->arow($result)) {
                $error = 'The skin chosen no longer exists.';
            } else {
                $skinId = $this->request->both('skin');

                $this->database->disposeresult($result);
                $this->user->setBulk([
                    'nowordfilter' => $this->request->post('usewordfilter') !== null
                    && $this->request->post('usewordfilter') ? 0 : 1,
                    'skin_id' => $skinId,
                    'wysiwyg' => $this->request->post('wysiwyg') !== null ? 1 : 0,
                ]);
            }

            if ($error === null) {
                if ($this->request->isJSAccess()) {
                    $this->page->command('reload');

                    return null;
                }

                header('Location: ?act=ucp&what=board');

                return null;
            }

            $page .= $this->template->meta('error', $error);
        }

        $result = $this->user->get('group_id') !== 2
            ? $this->database->safeselect(
                [
                    'id',
                    '`using`',
                    'title',
                    'custom',
                    'wrapper',
                    '`default`',
                    'hidden',
                ],
                'skins',
                'WHERE `hidden`!=1 ORDER BY `title` ASC',
            )
            : $this->database->safeselect(
                [
                    'id',
                    '`using`',
                    'title',
                    'custom',
                    'wrapper',
                    '`default`',
                    'hidden',
                ],
                'skins',
                'ORDER BY `title` ASC',
            );
        $select = '';
        while ($skin = $this->database->arow($result)) {
            $select .= "<option value='" . $skin['id'] . "' "
                . ($skinId === $skin['id'] ? "selected='selected'" : '')
                . '/>' . ($skin['hidden'] ? '*' : '') . $skin['title'] . '</option>';
            $found = true;
        }

        $select = '<select name="skin" title="Board Skin">' . $select . '</select>';
        if (!$found) {
            $select = '--No Skins--';
        }

        return $page . $this->template->meta(
            'ucp-board-settings',
            $this->getlocationforform(),
            $select,
            '<input type="checkbox" name="usewordfilter" title="Use Word Filter"'
            . ($this->user->get('nowordfilter') ? '' : ' checked="checked"')
            . ' />',
            '<input type="checkbox" name="wysiwyg" title="WYSIWYG Enabled"'
            . ($this->user->get('wysiwyg') ? ' checked="checked"' : '')
            . ' />',
        );
    }
}
