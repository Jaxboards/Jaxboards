<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\TextFormatting;
use Jax\User;

use function array_pop;
use function gmdate;
use function header;
use function htmlspecialchars;
use function in_array;
use function is_array;
use function is_numeric;
use function json_encode;
use function mb_strlen;
use function mb_strstr;
use function password_hash;
use function password_verify;
use function preg_match;
use function strtotime;
use function trim;
use function ucfirst;

use const PASSWORD_DEFAULT;
use const PHP_EOL;

final class UCP
{
    private string $what = '';

    private ?string $runscript = null;

    private bool $shownucp = false;

    private string $ucppage = '';

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Request $request,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {
        $this->page->loadMeta('ucp');
    }

    public function render(): void
    {
        if ($this->user->isGuest() || $this->user->get('group_id') === 4) {
            $this->page->location('?');

            return;
        }

        $this->page->path(['UCP' => '?act=ucp']);
        $this->what = $this->request->both('what') ?? '';

        match ($this->what) {
            'sounds' => $this->showsoundsettings(),
            'signature' => $this->showsigsettings(),
            'pass' => $this->showpasssettings(),
            'email' => $this->showemailsettings(),
            'avatar' => $this->showavatarsettings(),
            'profile' => $this->showprofilesettings(),
            'board' => $this->showboardsettings(),
            'inbox' => $this->showinbox(),
            default => $this->showmain(),
        };

        if ($this->request->isJSUpdate()) {
            return;
        }

        $this->showucp();
    }

    private function getlocationforform(): string
    {
        return $this->jax->hiddenFormFields(['act' => 'ucp', 'what' => $this->what]);
    }

    private function showinbox(): void
    {
        $messageId = $this->request->post('messageid');
        $page = $this->request->both('page');
        $view = $this->request->get('view');
        $flag = $this->request->both('flag');
        $dmessage = $this->request->post('dmessage');

        if (is_array($dmessage)) {
            $this->deleteMessages($dmessage);
        }

        match (true) {
            is_numeric($messageId) => match ($page) {
                'Delete' => $this->delete($messageId),
                'Forward' => $this->compose($messageId, 'fwd'),
                'Reply' => $this->compose($messageId),
            },
            is_numeric($view) => $this->viewmessage($view),
            is_numeric($flag) => $this->flag(),

            default => match ($page) {
                'compose' => $this->compose(),
                'sent' => $this->viewmessages('sent'),
                'flagged' => $this->viewmessages('flagged'),
                default => $this->viewmessages(),
            },
        };
    }

    private function showmain(): void
    {
        $error = null;

        if ($this->request->isJSUpdate() && !$this->request->hasPostData()) {
            return;
        }

        if (
            $this->request->post('ucpnotepad') !== null
        ) {
            if (mb_strlen((string) $this->request->post('ucpnotepad')) > 2000) {
                $error = 'The UCP notepad cannot exceed 2000 characters.';
                $this->page->JS('error', $error);
            } else {
                $this->user->set('ucpnotepad', $this->request->post('ucpnotepad'));
            }
        }

        $ucpnotepad = $this->user->get('ucpnotepad');

        $this->ucppage = ($error !== null ? $this->page->meta('error', $error) : '') . $this->page->meta(
            'ucp-index',
            $this->jax->hiddenFormFields(['act' => 'ucp']),
            $this->user->get('display_name'),
            $this->jax->pick($this->user->get('avatar'), $this->page->meta('default-avatar')),
            trim((string) $ucpnotepad) !== '' && trim((string) $ucpnotepad) !== '0'
            ? $this->textFormatting->blockhtml($ucpnotepad) : 'Personal notes go here.',
        );
        $this->showucp();
    }

    private function showucp($page = false): void
    {
        if ($this->shownucp) {
            return;
        }

        if (!$page) {
            $page = $this->ucppage;
        }

        $page = $this->page->meta('ucp-wrapper', $page);
        // $this->page->JS("window",Array("id"=>"ucpwin","title"=>"Settings","content"=>$page,"animate"=>false));
        $this->page->append('PAGE', $page);
        $this->page->JS('update', 'page', $page);
        if ($this->runscript) {
            $this->page->JS('script', $this->runscript);
        }

        $this->shownucp = true;
    }

    private function showsoundsettings(): void
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
                $this->page->JS(
                    'script',
                    "window.globalsettings.{$field}="
                    . ($this->request->post($field) !== null ? 1 : 0),
                );
            }

            $this->page->JS('alert', 'Settings saved successfully.');

            $this->ucppage = 'Settings saved successfully.';
        } elseif ($this->request->isJSUpdate()) {
            return;
        }

        $checkboxes = [
            $this->getlocationforform() . $this->jax->hiddenFormFields(
                ['submit' => 1],
            ),
        ];

        foreach ($fields as $field) {
            $checkboxes[] = '<input type="checkbox" title="' . $field . '" name="' . $field . '" '
                . ($this->user->get($field) ? 'checked="checked"' : '') . '/>';
        }

        $this->ucppage = $this->page->meta('ucp-sound-settings', $checkboxes);
        $this->runscript = "if(document.querySelector('#dtnotify')&&window.webkitNotifications) "
            . "document.querySelector('#dtnotify').checked=(webkitNotifications.checkPermission()==0)";

        unset($checkboxes);
    }

    private function showsigsettings(): void
    {
        $update = false;
        $sig = $this->user->get('sig');
        if ($this->request->post('changesig') !== null) {
            $sig = $this->textFormatting->linkify($this->request->post('changesig'));
            $this->user->set('sig', $sig);
            $update = true;
        }

        $this->ucppage = $this->page->meta(
            'ucp-sig-settings',
            $this->getlocationforform(),
            $sig !== ''
            ? $this->textFormatting->theworks($sig) : '( none )',
            $this->textFormatting->blockhtml($sig),
        );
        if (!$update) {
            return;
        }

        $this->showucp();
    }

    private function showpasssettings(): void
    {
        $error = null;
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

            $verified_password = password_verify((string) $this->request->post('curpass'), (string) $this->user->get('pass'));
            if (!$verified_password) {
                $error = 'The password you entered is incorrect.';
            }

            if ($error === null) {
                $hashpass = password_hash((string) $this->request->post('newpass1'), PASSWORD_DEFAULT);
                $this->user->set('pass', $hashpass);
                $this->ucppage = <<<'HTML'
                    Password changed.
                        <br><br>
                        <a href="?act=ucp&what=pass">Back</a>
                    HTML;

                $this->showucp();

                return;
            }

            $this->ucppage .= $this->page->meta('error', $error);
            $this->page->JS('error', $error);
        }

        $this->ucppage .= $this->page->meta(
            'ucp-pass-settings',
            $this->getlocationforform()
            . $this->jax->hiddenFormFields(['passchange' => 1]),
        );
    }

    private function showemailsettings(): void
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
                $this->page->JS('alert', $error);
            } else {
                $this->user->setBulk([
                    'email' => $this->request->post('email'),
                    'email_settings' => ($this->request->post('notifications') ?? false ? 2 : 0)
                    + ($this->request->post('adminemails') ?? false ? 1 : 0),
                ]);
                $this->ucppage = 'Email settings updated.'
                    . '<br><br><a href="?act=ucp&what=email">Back</a>';
            }

            $this->showucp();

            return;
        }

        $this->ucppage .= $this->page->meta(
            'ucp-email-settings',
            $this->getlocationforform() . $this->jax->hiddenFormFields(
                ['submit' => 'true'],
            ),
            $this->request->both('change') !== null ? <<<HTML
                <input
                    type="text"
                    name="email"
                    aria-label="Email"
                    title="Enter your new email address"
                    value="{$this->user->get('email')}" />
                HTML : '<strong>' . $this->jax->pick($this->user->get('email'), '--none--')
            . "</strong> <a href='?act=ucp&what=email&change=1'>Change</a>"
            . "<input type='hidden' name='email' value='" . $this->user->get('email') . "' />",
            '<input type="checkbox" title="Notifications" name="notifications"'
            . (($this->user->get('email_settings') & 2) !== 0 ? " checked='checked'" : '') . '>',
            '<input type="checkbox" title="Admin Emails" name="adminemails"'
            . (($this->user->get('email_settings') & 1) !== 0 ? ' checked="checked"' : '') . '>',
        );
    }

    private function showavatarsettings(): void
    {
        $error = null;
        $update = false;
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

            $update = true;
        }

        $this->ucppage = 'Your avatar: <span class="avatar"><img src="'
            . $this->jax->pick($avatar, $this->page->meta('default-avatar'))
            . '" alt="Your avatar"></span><br><br>
            <form data-ajax-form="true" method="post">'
            . $this->getlocationforform()
            . ($error !== null ? $this->page->error($error) : '')
            . '<input type="text" name="changedava" title="Your avatar" value="'
            . $this->textFormatting->blockhtml($avatar) . '" />
            <input type="submit" value="Edit" />
            </form>';
        if (!$update) {
            return;
        }

        $this->showucp();
    }

    private function showprofilesettings(): void
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
                'dob_day' => $this->jax->pick($this->request->post('dob_day'), null),
                'dob_month' => $this->jax->pick($this->request->post('dob_month'), null),
                'dob_year' => $this->jax->pick($this->request->post('dob_year'), null),
                'full_name' => $this->request->post('full_name'),
                'gender' => in_array($this->request->post('gender'), $genderOptions)
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
                && !$data['dob_year']
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
                $this->ucppage = 'Profile successfully updated.<br>'
                    . '<br><a href="?act=ucp&what=profile">Back</a>';
                $this->showucp();

                return;
            }

            $this->ucppage .= $this->page->meta('error', $error);
            $this->page->JS('error', $error);
        }

        $genderselect = '<select name="gender" title="Your gender" aria-label="Gender">';
        foreach (['', 'male', 'female', 'other'] as $gender) {
            $genderselect .= '<option value="' . $gender . '"'
                . ($this->user->get('gender') === $gender ? ' selected="selected"' : '')
                . '>' . $this->jax->pick(ucfirst($gender), 'Not telling') . '</option>';
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

        $this->ucppage = $this->page->meta(
            'ucp-profile-settings',
            $this->getlocationforform()
            . $this->jax->hiddenFormFields(['submit' => '1']),
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

    private function showboardsettings(): void
    {
        $error = null;
        $showthing = false;
        $skinId = $this->user->get('skin_id');
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
                    $this->page->JS('reload');

                    return;
                }

                header('Location: ?act=ucp&what=board');

                return;
            }

            $this->ucppage .= $this->page->meta('error', $error);

            $showthing = true;
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

        $this->ucppage .= $this->page->meta(
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
        if (!$showthing) {
            return;
        }

        $this->showucp();
    }

    /*
        HERE BE PRIVATE MESSAGING
        ARRRRRRRRRRRRRRRRRRRRRRRR
     */

    private function flag(): void
    {
        $this->page->JS('softurl');
        $this->database->safeupdate(
            'messages',
            [
                'flag' => $this->request->both('tog') ? 1 : 0,
            ],
            'WHERE `id`=? AND `to`=?',
            $this->database->basicvalue($this->request->both('flag')),
            $this->user->get('id'),
        );
    }

    private function viewmessage(string $messageid): void
    {
        if (
            $this->request->isJSUpdate()
            && !$this->request->isJSDirectLink()
        ) {
            return;
        }

        $error = null;
        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT a.`id` AS `id`,a.`to` AS `to`,a.`from` AS `from`,a.`title` AS `title`,
                    a.`message` AS `message`,a.`read` AS `read`,
                    UNIX_TIMESTAMP(a.`date`) AS `date`,a.`del_recipient` AS `del_recipient`,
                    a.`del_sender` AS `del_sender`,a.`flag` AS `flag`,
                    m.`group_id` AS `group_id`,m.`display_name` AS `name`,
                    m.`avatar` AS `avatar`,m.`usertitle` AS `usertitle`
                FROM %t a
                LEFT JOIN %t m
                    ON a.`from`=m.`id`
                WHERE a.`id`=?
                ORDER BY a.`date` DESC
                SQL
            ,
            ['messages', 'members'],
            $this->database->basicvalue($messageid),
        );
        $message = $this->database->arow($result);
        $this->database->disposeresult($result);
        if (
            $message['from'] !== $this->user->get('id')
            && $message['to'] !== $this->user->get('id')
        ) {
            $error = "You don't have permission to view this message.";
        }

        if ($error !== null) {
            $this->showucp($error);

            return;
        }

        if (!$message['read'] && $message['to'] === $this->user->get('id')) {
            $this->database->safeupdate(
                'messages',
                ['read' => 1],
                'WHERE `id`=?',
                $message['id'],
            );
            $this->updatenummessages();
        }

        $page = $this->page->meta(
            'inbox-messageview',
            $message['title'],
            $this->page->meta(
                'user-link',
                $message['from'],
                $message['group_id'],
                $message['name'],
            ),
            $this->jax->date($message['date']),
            $this->textFormatting->theworks($message['message']),
            $this->jax->pick($message['avatar'], $this->page->meta('default-avatar')),
            $message['usertitle'],
            $this->jax->hiddenFormFields(
                [
                    'act' => 'ucp',
                    'messageid' => $message['id'],
                    'sender' => $message['from'],
                    'what' => 'inbox',
                ],
            ),
        );
        $this->showucp($page);
    }

    private function updatenummessages(): void
    {
        $result = $this->database->safeselect(
            'COUNT(`id`)',
            'messages',
            'WHERE `to`=? AND !`read`',
            $this->user->get('id'),
        );
        $unread = $this->database->arow($result);
        $this->database->disposeresult($result);

        $unread = array_pop($unread);
        $this->page->JS('update', 'num-messages', $unread);
    }

    private function viewmessages(string $view = 'inbox'): void
    {
        if ($this->request->isJSUpdate() && !$this->request->hasPostData()) {
            return;
        }

        $page = '';
        $result = null;
        $hasmessages = false;
        if ($view === 'sent') {
            $result = $this->database->safespecial(
                <<<'MySQL'
                    SELECT a.`id` AS `id`
                    , a.`to` AS `to`
                    , a.`from` AS `from`
                    , a.`title` AS `title`
                    , a.`message` AS `message`
                    , a.`read` AS `read`
                    , UNIX_TIMESTAMP(a.`date`) AS `date`
                    , a.`del_recipient` AS `del_recipient`
                    , a.`del_sender` AS `del_sender`
                    , a.`flag` AS `flag`
                    , m.`display_name` AS `display_name`
                    FROM %t a
                    LEFT JOIN %t m
                        ON a.`to`=m.`id`
                    WHERE a.`from`=? AND !a.`del_sender`
                    ORDER BY a.`date` DESC

                    MySQL,
                ['messages', 'members'],
                $this->user->get('id'),
            );
        } elseif ($view === 'flagged') {
            $result = $this->database->safespecial(
                <<<'SQL'
                    SELECT
                        a.`id` AS `id`,
                        a.`to` AS `to`,
                        a.`from` AS `from`,
                        a.`title` AS `title`,
                        a.`message` AS `message`,
                        a.`read` AS `read`,
                        UNIX_TIMESTAMP(a.`date`) AS `date`,
                        a.`del_recipient` AS `del_recipient`,
                        a.`del_sender` AS `del_sender`,
                        a.`flag` AS `flag`,
                        m.`display_name` AS `display_name`
                    FROM %t a
                    LEFT JOIN %t m
                        ON a.`from`=m.`id`
                    WHERE a.`to`=? AND a.`flag`=1
                    ORDER BY a.`date` DESC
                    SQL
                ,
                ['messages', 'members'],
                $this->user->get('id'),
            );
        } else {
            $result = $this->database->safespecial(
                <<<'SQL'
                    SELECT
                        a.`id` AS `id`,
                        a.`to` AS `to`,
                        a.`from` AS `from`,
                        a.`title` AS `title`,
                        a.`message` AS `message`,
                        a.`read` AS `read`,
                        UNIX_TIMESTAMP(a.`date`) AS `date`,
                        a.`del_recipient` AS `del_recipient`,
                        a.`del_sender` AS `del_sender`,
                        a.`flag` AS `flag`,
                        m.`display_name` AS `display_name`
                    FROM %t a
                    LEFT JOIN %t m
                    ON a.`from`=m.`id`
                    WHERE a.`to`=? AND !a.`del_recipient`
                    ORDER BY a.`date` DESC
                    SQL
                ,
                ['messages', 'members'],
                $this->user->get('id'),
            );
        }

        $unread = 0;
        while ($message = $this->database->arow($result)) {
            $hasmessages = 1;
            if (!$message['read']) {
                ++$unread;
            }

            $dmessageOnchange = "RUN.stream.location('"
                . '?act=ucp&what=inbox&flag=' . $message['id'] . "&tog='+" . '
                (this.checked?1:0), 1)';
            $page .= $this->page->meta(
                'inbox-messages-row',
                $message['read'] ? 'read' : 'unread',
                '<input class="check" type="checkbox" title="PM Checkbox" name="dmessage[]" '
                . 'value="' . $message['id'] . '" />',
                '<input type="checkbox" '
                . ($message['flag'] ? 'checked="checked" ' : '')
                . 'class="switch flag" onchange="' . $dmessageOnchange . '" />',
                $message['id'],
                $message['title'],
                $message['display_name'],
                $this->jax->date($message['date']),
            );
        }

        if (!$hasmessages) {
            if ($view === 'sent') {
                $msg = 'No sent messages.';
            } elseif ($view === 'flagged') {
                $msg = 'No flagged messages.';
            } else {
                $msg = 'No messages. You could always try '
                    . '<a href="?act=ucp&what=inbox&page=compose">'
                    . 'sending some</a>, though!';
            }

            $page .= '<tr><td colspan="5" class="error">' . $msg . '</td></tr>';
        }

        $page = $this->page->meta(
            'inbox-messages-listing',
            $this->jax->hiddenFormFields(
                [
                    'act' => 'ucp',
                    'what' => 'inbox',
                ],
            ),
            $view === 'sent' ? 'Recipient' : 'Sender',
            $page,
        );

        if ($view === 'inbox') {
            $this->page->JS('update', 'num-messages', $unread);
        }

        $this->showucp($page);
    }

    private function compose(string $messageid = '', string $todo = ''): void
    {
        $error = null;
        $mid = 0;
        $mname = '';
        $mtitle = '';
        if ($this->request->post('submit') !== null) {
            $mid = $this->request->both('mid');
            if (!$mid && $this->request->both('to')) {
                $result = $this->database->safeselect(
                    [
                        'id',
                        'email',
                        'email_settings',
                    ],
                    'members',
                    'WHERE `display_name`=?',
                    $this->database->basicvalue($this->request->both('to')),
                );
                $udata = $this->database->arow($result);
                $this->database->disposeresult($result);
            } else {
                $result = $this->database->safeselect(
                    [
                        'id',
                        'email',
                        'email_settings',
                    ],
                    'members',
                    'WHERE `id`=?',
                    $this->database->basicvalue($mid),
                );
                $udata = $this->database->arow($result);
                $this->database->disposeresult($result);
            }

            if (!$udata) {
                $error = 'Invalid user!';
            } elseif (
                trim((string) $this->request->both('title')) === ''
                || trim((string) $this->request->both('title')) === '0'
            ) {
                $error = 'You must enter a title.';
            }

            if ($error !== null) {
                $this->page->JS('error', $error);
                $this->page->append('PAGE', $this->page->error($error));
            } else {
                // Put it into the table.
                $this->database->safeinsert(
                    'messages',
                    [
                        'date' => $this->database->datetime(),
                        'del_recipient' => 0,
                        'del_sender' => 0,
                        'from' => $this->user->get('id'),
                        'message' => $this->request->post('message'),
                        'read' => 0,
                        'title' => $this->textFormatting->blockhtml($this->request->post('title')),
                        'to' => $udata['id'],
                    ],
                );
                // Give them a notification.
                $cmd = json_encode(
                    [
                        'newmessage',
                        'You have a new message from ' . $this->user->get('display_name'),
                        $this->database->insertId(),
                    ],
                ) . PHP_EOL;
                $result = $this->database->safespecial(
                    <<<'SQL'
                        UPDATE %t
                        SET `runonce`=concat(`runonce`,?)
                        WHERE `uid`=?
                        SQL
                    ,
                    ['session'],
                    $this->database->basicvalue($cmd),
                    $udata['id'],
                );
                // Send em an email!
                if (($udata['email_settings'] & 2) !== 0) {
                    $this->jax->mail(
                        $udata['email'],
                        'PM From ' . $this->user->get('display_name'),
                        "You are receiving this email because you've "
                        . 'received a message from ' . $this->user->get('display_name')
                        . ' on {BOARDLINK}.<br>'
                        . '<br>Please go to '
                        . "<a href='{BOARDURL}?act=ucp&what=inbox'>"
                        . '{BOARDURL}?act=ucp&what=inbox</a>'
                        . ' to view your message.',
                    );
                }

                $this->showucp(
                    'Message successfully delivered.'
                    . "<br><br><a href='?act=ucp&what=inbox'>Back</a>",
                );

                return;
            }
        }

        if ($this->request->isJSUpdate() && !$messageid) {
            return;
        }

        $msg = '';
        if ($messageid !== '' && $messageid !== '0') {
            $result = $this->database->safeselect(
                [
                    '`from`',
                    'message',
                    'title',
                ],
                'messages',
                'WHERE (`to`=? OR `from`=?) AND `id`=?',
                $this->user->get('id'),
                $this->user->get('id'),
                $this->database->basicvalue($messageid),
            );

            $message = $this->database->arow($result);
            $this->database->disposeresult($result);

            $mid = $message['from'];
            $result = $this->database->safeselect(
                ['display_name'],
                'members',
                'WHERE `id`=?',
                $mid,
            );
            $thisrow = $this->database->arow($result);
            $mname = array_pop($thisrow);
            $this->database->disposeresult($result);

            $msg = PHP_EOL . PHP_EOL . PHP_EOL
                . '[quote=' . $mname . ']' . $message['message'] . '[/quote]';
            $mtitle = ($todo === 'fwd' ? 'FWD:' : 'RE:') . $message['title'];
            if ($todo === 'fwd') {
                $mid = '';
                $mname = '';
            }
        }

        if (is_numeric($this->request->get('mid'))) {
            $showfull = 1;
            $mid = $this->request->both('mid');
            $result = $this->database->safeselect(
                ['display_name'],
                'members',
                'WHERE `id`=?',
                $mid,
            );
            $thisrow = $this->database->arow($result);
            $mname = array_pop($thisrow);
            $this->database->disposeresult($result);

            if (!$mname) {
                $mid = 0;
                $mname = '';
            }
        }

        $page = $this->page->meta(
            'inbox-composeform',
            $this->jax->hiddenFormFields(
                [
                    'act' => 'ucp',
                    'page' => 'compose',
                    'submit' => '1',
                    'what' => 'inbox',
                ],
            ),
            $mid,
            $mname,
            $mname ? 'good' : '',
            $mtitle,
            htmlspecialchars($msg),
        );
        $this->showucp($page);
    }

    private function delete($messageId, bool $relocate = true): void
    {
        $result = $this->database->safeselect(
            [
                '`to`',
                '`from`',
                'del_recipient',
                'del_sender',
            ],
            'messages',
            'WHERE `id`=?',
            $this->database->basicvalue($messageId),
        );
        $message = $this->database->arow($result);
        $this->database->disposeresult($result);

        $is_recipient = $message['to'] === $this->user->get('id');
        $is_sender = $message['from'] === $this->user->get('id');
        if ($is_recipient) {
            $this->database->safeupdate(
                'messages',
                [
                    'del_recipient' => 1,
                ],
                'WHERE `id`=?',
                $this->database->basicvalue($messageId),
            );
        }

        if ($is_sender) {
            $this->database->safeupdate(
                'messages',
                [
                    'del_sender' => 1,
                ],
                'WHERE `id`=?',
                $this->database->basicvalue($messageId),
            );
        }

        $result = $this->database->safeselect(
            [
                'del_recipient',
                'del_sender',
            ],
            'messages',
            'WHERE `id`=?',
            $this->database->basicvalue($messageId),
        );
        $message = $this->database->arow($result);
        $this->database->disposeresult($result);

        if ($message['del_recipient'] && $message['del_sender']) {
            $this->database->safedelete(
                'messages',
                'WHERE `id`=?',
                $this->database->basicvalue($messageId),
            );
        }

        if (!$relocate) {
            return;
        }

        $this->page->location(
            '?act=ucp&what=inbox'
            . ($this->request->both('prevpage') !== null
            ? '&page=' . $this->request->both('prevpage') : ''),
        );
    }

    private function deleteMessages(array $messageIds): void
    {
        foreach ($messageIds as $messageId) {
            $this->delete($messageId, false);
        }
    }
}
