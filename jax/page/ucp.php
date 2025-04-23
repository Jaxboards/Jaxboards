<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Jax;

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
use function mb_strtolower;
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
    public $what = '';

    public $runscript = false;

    public $shownucp = false;

    public $ucppage = '';

    public function __construct()
    {
        global $PAGE;
        $PAGE->loadmeta('ucp');
    }

    public function route(): void
    {
        global $PAGE,$JAX,$USER,$DB;
        if (!$USER || $USER['group_id'] === 4) {
            $PAGE->location('?');

            return;
        }

        $PAGE->path(['UCP' => '?act=ucp']);
        $this->what = $JAX->b['what'] ?? '';

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

        if ($PAGE->jsupdate) {
            return;
        }

        $this->showucp();
    }

    public function getlocationforform(): string
    {
        global $JAX;

        return JAX::hiddenFormFields(['act' => 'ucp', 'what' => $this->what]);
    }

    public function showinbox(): void
    {
        global $JAX;
        if (
            isset($JAX->p['dmessage'])
            && is_array($JAX->p['dmessage'])
        ) {
            foreach ($JAX->p['dmessage'] as $v) {
                $this->delete($v, false);
            }
        }

        if (
            isset($JAX->p['messageid'])
            && is_numeric($JAX->p['messageid'])
        ) {
            switch (mb_strtolower((string) $JAX->p['page'])) {
                case 'delete':
                    $this->delete($JAX->p['messageid']);

                    break;

                case 'forward':
                    $this->compose($JAX->p['messageid'], 'fwd');

                    break;

                case 'reply':
                    $this->compose($JAX->p['messageid']);

                    break;

                default:
            }
        } else {
            if (!isset($JAX->b['page'])) {
                $JAX->b['page'] = false;
            }

            if ($JAX->b['page'] === 'compose') {
                $this->compose();
            } elseif (
                isset($JAX->g['view'])
                && is_numeric($JAX->g['view'])
            ) {
                $this->viewmessage($JAX->g['view']);
            } elseif ($JAX->b['page'] === 'sent') {
                $this->viewmessages('sent');
            } elseif ($JAX->b['page'] === 'flagged') {
                $this->viewmessages('flagged');
            } elseif (
                isset($JAX->b['flag'])
                && is_numeric($JAX->b['flag'])
            ) {
                $this->flag();

                return;
            } else {
                $this->viewmessages();
            }
        }
    }

    public function showmain(): void
    {
        global $PAGE,$JAX,$USER,$DB;
        $e = '';

        if ($PAGE->jsupdate && empty($JAX->p)) {
            return;
        }

        if (isset($JAX->p['ucpnotepad']) && $JAX->p['ucpnotepad']) {
            if (mb_strlen((string) $JAX->p['ucpnotepad']) > 2000) {
                $e = 'The UCP notepad cannot exceed 2000 characters.';
                $PAGE->JS('error', $e);
            } else {
                $DB->safeupdate(
                    'members',
                    [
                        'ucpnotepad' => $JAX->p['ucpnotepad'],
                    ],
                    'WHERE `id`=?',
                    $USER['id'],
                );
                $USER['ucpnotepad'] = $JAX->p['ucpnotepad'];
            }
        }

        $this->ucppage = ($e !== '' && $e !== '0' ? $PAGE->meta('error', $e) : '') . $PAGE->meta(
            'ucp-index',
            JAX::hiddenFormFields(['act' => 'ucp']),
            $USER['display_name'],
            $JAX->pick($USER['avatar'], $PAGE->meta('default-avatar')),
            trim((string) $USER['ucpnotepad']) !== '' && trim((string) $USER['ucpnotepad']) !== '0'
            ? $JAX->blockhtml($USER['ucpnotepad']) : 'Personal notes go here.',
        );
        $this->showucp();
    }

    public function showucp($page = false): void
    {
        global $PAGE;
        if ($this->shownucp) {
            return;
        }

        if (!$page) {
            $page = $this->ucppage;
        }

        $page = $PAGE->meta('ucp-wrapper', $page);
        // $PAGE->JS("window",Array("id"=>"ucpwin","title"=>"Settings","content"=>$page,"animate"=>false));
        $PAGE->append('PAGE', $page);
        $PAGE->JS('update', 'page', $page);
        if ($this->runscript) {
            $PAGE->JS('script', $this->runscript);
        }

        $PAGE->updatepath();

        $this->shownucp = true;
    }

    public function showsoundsettings(): ?bool
    {
        global $USER,$PAGE,$JAX,$DB;

        $variables = [
            'sound_shout',
            'sound_im',
            'sound_pm',
            'notify_pm',
            'sound_postinmytopic',
            'notify_postinmytopic',
            'sound_postinsubscribedtopic',
            'notify_postinsubscribedtopic',
        ];

        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            $update = [];
            foreach ($variables as $v) {
                $update[$v] = isset($JAX->p[$v]) && $JAX->p[$v] ? 1 : 0;
            }

            $DB->safeupdate(
                'members',
                $update,
                'WHERE `id`=?',
                $USER['id'],
            );

            foreach ($variables as $v) {
                $PAGE->JS(
                    'script',
                    "window.globalsettings.{$v}="
                    . (isset($JAX->p[$v]) && $JAX->p[$v] ? 1 : 0),
                );
            }

            $PAGE->JS('alert', 'Settings saved successfully.');

            $PAGE->ucppage = 'Settings saved successfully.';
        } elseif ($PAGE->jsupdate) {
            return true;
        }

        $checkboxes = [
            $this->getlocationforform() . JAX::hiddenFormFields(
                ['submit' => 1],
            ),
        ];

        foreach ($variables as $v) {
            $checkboxes[] = '<input type="checkbox" title="' . $v . '" name="' . $v . '" '
                . ($USER[$v] ? 'checked="checked"' : '') . '/>';
        }

        $this->ucppage = $PAGE->meta('ucp-sound-settings', $checkboxes);
        $this->runscript = "if(document.querySelector('#dtnotify')&&window.webkitNotifications) "
            . "document.querySelector('#dtnotify').checked=(webkitNotifications.checkPermission()==0)";

        unset($checkboxes);

        return null;
    }

    public function showsigsettings(): void
    {
        global $USER,$JAX,$DB,$PAGE;
        $update = false;
        $sig = $USER['sig'];
        if (isset($JAX->p['changesig'])) {
            $sig = $JAX->linkify($JAX->p['changesig']);
            $DB->safeupdate(
                'members',
                [
                    'sig' => $sig,
                ],
                'WHERE `id`=?',
                $USER['id'],
            );
            $update = true;
        }

        $this->ucppage = $PAGE->meta(
            'ucp-sig-settings',
            $this->getlocationforform(),
            $sig !== ''
            ? $JAX->theworks($sig) : '( none )',
            $JAX->blockhtml($sig),
        );
        if (!$update) {
            return;
        }

        $this->showucp();
    }

    public function showpasssettings()
    {
        global $JAX,$USER,$PAGE,$DB;
        $e = '';
        if (isset($JAX->p['passchange'])) {
            if (!isset($JAX->p['showpass'])) {
                $JAX->p['showpass'] = false;
            }

            if (
                !$JAX->p['showpass']
                && $JAX->p['newpass1'] !== $JAX->p['newpass2']
            ) {
                $e = 'Those passwords do not match.';
            }

            if (
                !$JAX->p['newpass1']
                || !$JAX->p['showpass']
                && !$JAX->p['newpass2']
                || !$JAX->p['curpass']
            ) {
                $e = 'All form fields are required.';
            }

            $verified_password = password_verify((string) $JAX->p['curpass'], (string) $USER['pass']);
            if (!$verified_password) {
                $e = 'The password you entered is incorrect.';
            }

            if ($e === '' || $e === '0') {
                $hashpass = password_hash((string) $JAX->p['newpass1'], PASSWORD_DEFAULT);
                $DB->safeupdate(
                    'members',
                    [
                        'pass' => $hashpass,
                    ],
                    'WHERE `id`=?',
                    $USER['id'],
                );
                $this->ucppage = <<<'EOT'
                    Password changed.
                        <br><br>
                        <a href="?act=ucp&what=pass">Back</a>
                    EOT;

                return $this->showucp();
            }

            $this->ucppage .= $PAGE->meta('error', $e);
            $PAGE->JS('error', $e);
        }

        $this->ucppage .= $PAGE->meta(
            'ucp-pass-settings',
            $this->getlocationforform()
            . JAX::hiddenFormFields(['passchange' => 1]),
        );
    }

    public function showemailsettings()
    {
        global $USER,$JAX,$PAGE,$DB;
        $e = '';
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if ($JAX->p['email'] && !$JAX->isemail($JAX->p['email'])) {
                $e = 'Please enter a valid email!';
            }

            if ($e !== '' && $e !== '0') {
                $PAGE->JS('alert', $e);
            } else {
                $DB->safeupdate(
                    'members',
                    [
                        'email' => $JAX->p['email'],
                        'email_settings' => ($JAX->p['notifications'] ?? false ? 2 : 0)
                        + ($JAX->p['adminemails'] ?? false ? 1 : 0),
                    ],
                    'WHERE `id`=?',
                    $USER['id'],
                );
                $this->ucppage = 'Email settings updated.'
                    . '<br><br><a href="?act=ucp&what=email">Back</a>';
            }

            return $this->showucp();
        }

        $this->ucppage .= $PAGE->meta(
            'ucp-email-settings',
            $this->getlocationforform() . JAX::hiddenFormFields(
                ['submit' => 'true'],
            ),
            isset($JAX->b['change']) && $JAX->b['change'] ? <<<HTML
                <input
                    type="text"
                    name="email"
                    aria-label="Email"
                    title="Enter your new email address"
                    value="{$USER['email']}" />
                HTML : '<strong>' . $JAX->pick($USER['email'], '--none--')
            . "</strong> <a href='?act=ucp&what=email&change=1'>Change</a>"
            . "<input type='hidden' name='email' value='" . $USER['email'] . "' />",
            '<input type="checkbox" title="Notifications" name="notifications"'
            . (($USER['email_settings'] & 2) !== 0 ? " checked='checked'" : '') . '>',
            '<input type="checkbox" title="Admin Emails" name="adminemails"'
            . (($USER['email_settings'] & 1) !== 0 ? ' checked="checked"' : '') . '>',
        );
    }

    public function showavatarsettings(): void
    {
        global $USER,$PAGE,$JAX,$DB;
        $e = '';
        $update = false;
        if (isset($JAX->p['changedava'])) {
            if ($JAX->p['changedava'] && !$JAX->isurl($JAX->p['changedava'])) {
                $e = 'Please enter a valid image URL.';
            } else {
                $DB->safeupdate(
                    'members',
                    ['avatar' => $JAX->p['changedava']],
                    'WHERE `id`=?',
                    $USER['id'],
                );
                $USER['avatar'] = $JAX->p['changedava'];
            }

            $update = true;
        }

        $this->ucppage = 'Your avatar: <span class="avatar"><img src="'
            . $JAX->pick($USER['avatar'], $PAGE->meta('default-avatar'))
            . '" alt="Your avatar"></span><br><br>
            <form data-ajax-form="true" method="post">'
            . $this->getlocationforform()
            . ($e !== '' && $e !== '0' ? $PAGE->error($e) : '')
            . '<input type="text" name="changedava" title="Your avatar" value="'
            . $JAX->blockhtml($USER['avatar']) . '" />
            <input type="submit" value="Edit" />
            </form>';
        if (!$update) {
            return;
        }

        $this->showucp();
    }

    public function showprofilesettings(): void
    {
        global $USER,$JAX,$PAGE,$DB;
        $error = '';
        $genderOptions = ['', 'male', 'female', 'other'];
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            // Insert the profile info into the database.
            $data = [
                'about' => $JAX->p['about'],
                'contact_aim' => $JAX->p['con_aim'],
                'contact_bluesky' => $JAX->p['con_bluesky'],
                'contact_discord' => $JAX->p['con_discord'],
                'contact_gtalk' => $JAX->p['con_gtalk'],
                'contact_msn' => $JAX->p['con_msn'],
                'contact_skype' => $JAX->p['con_skype'],
                'contact_steam' => $JAX->p['con_steam'],
                'contact_twitter' => $JAX->p['con_twitter'],
                'contact_yim' => $JAX->p['con_yim'],
                'contact_youtube' => $JAX->p['con_youtube'],
                'display_name' => trim((string) $JAX->p['display_name']),
                'dob_day' => $JAX->pick($JAX->p['dob_day'], null),
                'dob_month' => $JAX->pick($JAX->p['dob_month'], null),
                'dob_year' => $JAX->pick($JAX->p['dob_year'], null),
                'full_name' => $JAX->p['full_name'],
                'gender' => in_array($JAX->p['gender'], $genderOptions)
                ? $JAX->p['gender'] : '',
                'location' => $JAX->p['location'],
                'usertitle' => $JAX->p['usertitle'],
                'website' => $JAX->p['website'],
            ];

            // Begin input checking.
            if ($data['display_name'] === '') {
                $data['display_name'] = $USER['name'];
            }

            $badNameChars = Config::getSetting('badnamechars');
            if (
                $badNameChars
                && preg_match($badNameChars, (string) $data['display_name'])
            ) {
                $error = 'Invalid characters in display name!';
            } else {
                $result = $DB->safeselect(
                    'COUNT(`id`) AS `same_display_name`',
                    'members',
                    'WHERE `display_name` = ? AND `id`!=? LIMIT 1',
                    $DB->basicvalue($data['display_name']),
                    $USER['id'],
                );
                $displayNameCheck = $DB->arow($result);
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
                ] as $k => $v
            ) {
                if (
                    mb_strstr($k, 'contact') !== false
                    && preg_match('/[^\w.@]/', (string) $data[$k])
                ) {
                    $error = "Invalid characters in {$v}";
                }

                $data[$k] = $JAX->blockhtml($data[$k]);
                $length = $k === 'display_name'
                    ? 30
                    : ($k === 'location' ? 100 : 50);
                if (mb_strlen((string) $data[$k]) <= $length) {
                    continue;
                }

                $error = "{$v} must be less than {$length} characters.";
            }

            // Handle errors/insert.
            if ($error === '' || $error === '0') {
                if ($data['display_name'] !== $USER['display_name']) {
                    $DB->safeinsert(
                        'activity',
                        [
                            'arg1' => $USER['display_name'],
                            'arg2' => $data['display_name'],
                            'date' => gmdate('Y-m-d H:i:s'),
                            'type' => 'profile_name_change',
                            'uid' => $USER['id'],
                        ],
                    );
                }

                $DB->safeupdate(
                    'members',
                    $data,
                    'WHERE `id`=?',
                    $USER['id'],
                );
                $this->ucppage = 'Profile successfully updated.<br>'
                    . '<br><a href="?act=ucp&what=profile">Back</a>';
                $this->showucp();

                return;
            }

            $PAGE->ucppage .= $PAGE->meta('error', $error);
            $PAGE->JS('error', $error);
        }

        $data = $USER;
        $genderselect = '<select name="gender" title="Your gender" aria-label="Gender">';
        foreach (['', 'male', 'female', 'other'] as $v) {
            $genderselect .= '<option value="' . $v . '"'
                . ($data['gender'] === $v ? ' selected="selected"' : '')
                . '>' . $JAX->pick(ucfirst($v), 'Not telling') . '</option>';
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
        foreach ($fullMonthNames as $k => $v) {
            $dobselect .= '<option value="' . ($k + 1) . '"'
                . ($k + 1 === $data['dob_month'] ? ' selected="selected"' : '')
                . '>' . $v . '</option>';
        }

        $dobselect .= '</select><select name="dob_day" title="Day"><option value="">--</option>';
        for ($x = 1; $x < 32; ++$x) {
            $dobselect .= '<option value="' . $x . '"'
                . ($x === $data['dob_day'] ? ' selected="selected"' : '')
                . '>' . $x . '</option>';
        }

        $dobselect .= '</select><select name="dob_year" title="Year">'
            . '<option value="">--</option>';
        $thisyear = (int) gmdate('Y');
        for ($x = $thisyear; $x > $thisyear - 100; --$x) {
            $dobselect .= '<option value="' . $x . '"'
                . ($x === $data['dob_year'] ? ' selected="selected"' : '')
                . '>' . $x . '</option>';
        }

        $dobselect .= '</select>';

        $this->ucppage = $PAGE->meta(
            'ucp-profile-settings',
            $this->getlocationforform()
            . JAX::hiddenFormFields(['submit' => '1']),
            $USER['name'],
            $data['display_name'],
            $data['full_name'],
            $data['usertitle'],
            $data['about'],
            $data['location'],
            $genderselect,
            $dobselect,
            $data['contact_skype'],
            $data['contact_discord'],
            $data['contact_yim'],
            $data['contact_msn'],
            $data['contact_gtalk'],
            $data['contact_aim'],
            $data['contact_youtube'],
            $data['contact_steam'],
            $data['contact_twitter'],
            $data['contact_bluesky'],
            $data['website'],
        );
    }

    public function showboardsettings(): void
    {
        global $PAGE,$DB,$JAX,$USER;
        $e = '';
        $showthing = false;
        if (isset($JAX->b['skin']) && is_numeric($JAX->b['skin'])) {
            $result = $DB->safeselect(
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
                $JAX->b['skin'],
            );
            if (!$DB->arow($result)) {
                $e = 'The skin chosen no longer exists.';
            } else {
                $DB->disposeresult($result);
                $DB->safeupdate(
                    'members',
                    [
                        'nowordfilter' => isset($JAX->p['usewordfilter'])
                        && $JAX->p['usewordfilter'] ? 0 : 1,
                        'skin_id' => $JAX->b['skin'],
                        'wysiwyg' => isset($JAX->p['wysiwyg'])
                        && $JAX->p['wysiwyg'] ? 1 : 0,
                    ],
                    'WHERE `id`=?',
                    $USER['id'],
                );
                $USER['skin_id'] = $JAX->b['skin'];
            }

            if ($e === '') {
                if ($PAGE->jsaccess) {
                    $PAGE->JS('reload');

                    return;
                }

                header('Location: ?act=ucp&what=board');

                return;
            }

            $this->ucppage .= $PAGE->meta('error', $e);

            $showthing = true;
        }

        $result = $USER['group_id'] !== 2
            ? $DB->safeselect(
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
            : $DB->safeselect(
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
        while ($f = $DB->arow($result)) {
            $select .= "<option value='" . $f['id'] . "' "
                . ($USER['skin_id'] === $f['id'] ? "selected='selected'" : '')
                . '/>' . ($f['hidden'] ? '*' : '') . $f['title'] . '</option>';
            $found = true;
        }

        $select = '<select name="skin" title="Board Skin">' . $select . '</select>';
        if (!$found) {
            $select = '--No Skins--';
        }

        $this->ucppage .= $PAGE->meta(
            'ucp-board-settings',
            $this->getlocationforform(),
            $select,
            '<input type="checkbox" name="usewordfilter" title="Use Word Filter"'
            . ($USER['nowordfilter'] ? '' : ' checked="checked"')
            . ' />',
            '<input type="checkbox" name="wysiwyg" title="WYSIWYG Enabled"'
            . ($USER['wysiwyg'] ? ' checked="checked"' : '')
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

    public function flag(): void
    {
        global $PAGE,$DB,$JAX,$USER;
        $PAGE->JS('softurl');
        $DB->safeupdate(
            'messages',
            [
                'flag' => $JAX->b['tog'] ? 1 : 0,
            ],
            'WHERE `id`=? AND `to`=?',
            $DB->basicvalue($JAX->b['flag']),
            $USER['id'],
        );
    }

    public function viewmessage($messageid): void
    {
        global $PAGE,$DB,$JAX,$USER;
        if ($PAGE->jsupdate && !$PAGE->jsdirectlink) {
            return;
        }

        $e = '';
        $result = $DB->safespecial(
            <<<'EOT'
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
                EOT
            ,
            ['messages', 'members'],
            $DB->basicvalue($messageid),
        );
        $message = $DB->arow($result);
        $DB->disposeresult($result);
        if (
            $message['from'] !== $USER['id']
            && $message['to'] !== $USER['id']
        ) {
            $e = "You don't have permission to view this message.";
        }

        if ($e !== '' && $e !== '0') {
            $this->showucp($e);

            return;
        }

        if (!$message['read'] && $message['to'] === $USER['id']) {
            $DB->safeupdate(
                'messages',
                ['read' => 1],
                'WHERE `id`=?',
                $message['id'],
            );
            $this->updatenummessages();
        }

        $page = $PAGE->meta(
            'inbox-messageview',
            $message['title'],
            $PAGE->meta(
                'user-link',
                $message['from'],
                $message['group_id'],
                $message['name'],
            ),
            $JAX->date($message['date']),
            $JAX->theworks($message['message']),
            $JAX->pick($message['avatar'], $PAGE->meta('default-avatar')),
            $message['usertitle'],
            JAX::hiddenFormFields(
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

    public function updatenummessages(): void
    {
        global $DB,$PAGE,$USER;
        $result = $DB->safeselect(
            'COUNT(`id`)',
            'messages',
            'WHERE `to`=? AND !`read`',
            $USER['id'],
        );
        $unread = $DB->arow($result);
        $DB->disposeresult($result);

        $unread = array_pop($unread);
        $PAGE->JS('update', 'num-messages', $unread);
    }

    public function viewmessages($view = 'inbox'): void
    {
        global $PAGE,$DB,$JAX,$USER;

        if ($PAGE->jsupdate && empty($JAX->p)) {
            return;
        }

        $page = '';
        $result = null;
        $hasmessages = false;
        if ($view === 'sent') {
            $result = $DB->safespecial(
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
                $USER['id'],
            );
        } elseif ($view === 'flagged') {
            $result = $DB->safespecial(
                <<<'EOT'
                    SELECT a.`id` AS `id`,a.`to` AS `to`,a.`from` AS `from`,a.`title` AS `title`,
                        a.`message` AS `message`,a.`read` AS `read`,
                        UNIX_TIMESTAMP(a.`date`) AS `date`,a.`del_recipient` AS `del_recipient`,
                        a.`del_sender` AS `del_sender`,a.`flag` AS `flag`,
                        m.`display_name` AS `display_name`
                    FROM %t a
                    LEFT JOIN %t m
                        ON a.`from`=m.`id`
                    WHERE a.`to`=? AND a.`flag`=1
                    ORDER BY a.`date` DESC
                    EOT
                ,
                ['messages', 'members'],
                $USER['id'],
            );
        } else {
            $result = $DB->safespecial(
                <<<'EOT'
                    SELECT a.`id` AS `id`,a.`to` AS `to`,a.`from` AS `from`,a.`title` AS `title`,
                        a.`message` AS `message`,a.`read` AS `read`,
                        UNIX_TIMESTAMP(a.`date`) AS `date`,a.`del_recipient` AS `del_recipient`,
                        a.`del_sender` AS `del_sender`,a.`flag` AS `flag`,
                        m.`display_name` AS `display_name`
                    FROM %t a
                    LEFT JOIN %t m
                    ON a.`from`=m.`id`
                    WHERE a.`to`=? AND !a.`del_recipient`
                    ORDER BY a.`date` DESC
                    EOT
                ,
                ['messages', 'members'],
                $USER['id'],
            );
        }

        $unread = 0;
        while ($f = $DB->arow($result)) {
            $hasmessages = 1;
            if (!$f['read']) {
                ++$unread;
            }

            $dmessageOnchange = "RUN.stream.location('"
                . '?act=ucp&what=inbox&flag=' . $f['id'] . "&tog='+" . '
                (this.checked?1:0), 1)';
            $page .= $PAGE->meta(
                'inbox-messages-row',
                $f['read'] ? 'read' : 'unread',
                '<input class="check" type="checkbox" title="PM Checkbox" name="dmessage[]" '
                . 'value="' . $f['id'] . '" />',
                '<input type="checkbox" '
                . ($f['flag'] ? 'checked="checked" ' : '')
                . 'class="switch flag" onchange="' . $dmessageOnchange . '" />',
                $f['id'],
                $f['title'],
                $f['display_name'],
                $JAX->date($f['date']),
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

        $page = $PAGE->meta(
            'inbox-messages-listing',
            JAX::hiddenFormFields(
                [
                    'act' => 'ucp',
                    'what' => 'inbox',
                ],
            ),
            $view === 'sent' ? 'Recipient' : 'Sender',
            $page,
        );

        if ($view === 'inbox') {
            $PAGE->JS('update', 'num-messages', $unread);
        }

        $this->showucp($page);
    }

    public function compose($messageid = '', $todo = ''): void
    {
        global $PAGE,$JAX,$USER,$DB;
        $showfull = 0;
        $e = '';
        $mid = 0;
        $mname = '';
        $mtitle = '';
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            $mid = $JAX->b['mid'];
            if (!$mid && $JAX->b['to']) {
                $result = $DB->safeselect(
                    [
                        'id',
                        'email',
                        'email_settings',
                    ],
                    'members',
                    'WHERE `display_name`=?',
                    $DB->basicvalue($JAX->b['to']),
                );
                $udata = $DB->arow($result);
                $DB->disposeresult($result);
            } else {
                $result = $DB->safeselect(
                    [
                        'id',
                        'email',
                        'email_settings',
                    ],
                    'members',
                    'WHERE `id`=?',
                    $DB->basicvalue($mid),
                );
                $udata = $DB->arow($result);
                $DB->disposeresult($result);
            }

            if (!$udata) {
                $e = 'Invalid user!';
            } elseif (
                trim((string) $JAX->b['title']) === ''
                || trim((string) $JAX->b['title']) === '0'
            ) {
                $e = 'You must enter a title.';
            }

            if ($e !== '' && $e !== '0') {
                $PAGE->JS('error', $e);
                $PAGE->append('PAGE', $PAGE->error($e));
            } else {
                // Put it into the table.
                $DB->safeinsert(
                    'messages',
                    [
                        'date' => gmdate('Y-m-d H:i:s'),
                        'del_recipient' => 0,
                        'del_sender' => 0,
                        'from' => $USER['id'],
                        'message' => $JAX->p['message'],
                        'read' => 0,
                        'title' => $JAX->blockhtml($JAX->p['title']),
                        'to' => $udata['id'],
                    ],
                );
                // Give them a notification.
                $cmd = json_encode(
                    [
                        'newmessage',
                        'You have a new message from ' . $USER['display_name'],
                        $DB->insert_id(1),
                    ],
                ) . PHP_EOL;
                $result = $DB->safespecial(
                    <<<'EOT'
                        UPDATE %t
                        SET `runonce`=concat(`runonce`,?)
                        WHERE `uid`=?
                        EOT
                    ,
                    ['session'],
                    $DB->basicvalue($cmd, 1),
                    $udata['id'],
                );
                // Send em an email!
                if (($udata['email_settings'] & 2) !== 0) {
                    $JAX->mail(
                        $udata['email'],
                        'PM From ' . $USER['display_name'],
                        "You are receiving this email because you've "
                        . 'received a message from ' . $USER['display_name']
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

        if ($PAGE->jsupdate && !$messageid) {
            return;
        }

        $msg = '';
        if ($messageid) {
            $result = $DB->safeselect(
                [
                    '`from`',
                    'message',
                    'title',
                ],
                'messages',
                'WHERE (`to`=? OR `from`=?) AND `id`=?',
                $USER['id'],
                $USER['id'],
                $DB->basicvalue($messageid),
            );

            $message = $DB->arow($result);
            $DB->disposeresult($result);

            $mid = $message['from'];
            $result = $DB->safeselect(
                ['display_name'],
                'members',
                'WHERE `id`=?',
                $mid,
            );
            $thisrow = $DB->arow($result);
            $mname = array_pop($thisrow);
            $DB->disposeresult($result);

            $msg = PHP_EOL . PHP_EOL . PHP_EOL
                . '[quote=' . $mname . ']' . $message['message'] . '[/quote]';
            $mtitle = ($todo === 'fwd' ? 'FWD:' : 'RE:') . $message['title'];
            if ($todo === 'fwd') {
                $mid = '';
                $mname = '';
            }
        }

        if (isset($JAX->g['mid']) && is_numeric($JAX->g['mid'])) {
            $showfull = 1;
            $mid = $JAX->b['mid'];
            $result = $DB->safeselect(
                ['display_name'],
                'members',
                'WHERE `id`=?',
                $mid,
            );
            $thisrow = $DB->arow($result);
            $mname = array_pop($thisrow);
            $DB->disposeresult($result);

            if (!$mname) {
                $mid = 0;
                $mname = '';
            }
        }

        $page = $PAGE->meta(
            'inbox-composeform',
            JAX::hiddenFormFields(
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

    public function delete($id, $relocate = true): void
    {
        global $PAGE,$JAX,$DB,$USER;
        $result = $DB->safeselect(
            [
                '`to`',
                '`from`',
                'del_recipient',
                'del_sender',
            ],
            'messages',
            'WHERE `id`=?',
            $DB->basicvalue($id),
        );
        $message = $DB->arow($result);
        $DB->disposeresult($result);

        $is_recipient = $message['to'] === $USER['id'];
        $is_sender = $message['from'] === $USER['id'];
        if ($is_recipient) {
            $DB->safeupdate(
                'messages',
                [
                    'del_recipient' => 1,
                ],
                'WHERE `id`=?',
                $DB->basicvalue($id),
            );
        }

        if ($is_sender) {
            $DB->safeupdate(
                'messages',
                [
                    'del_sender' => 1,
                ],
                'WHERE `id`=?',
                $DB->basicvalue($id),
            );
        }

        $result = $DB->safeselect(
            [
                'del_recipient',
                'del_sender',
            ],
            'messages',
            'WHERE `id`=?',
            $DB->basicvalue($id),
        );
        $message = $DB->arow($result);
        $DB->disposeresult($result);

        if ($message['del_recipient'] && $message['del_sender']) {
            $DB->safedelete(
                'messages',
                'WHERE `id`=?',
                $DB->basicvalue($id),
            );
        }

        if (!$relocate) {
            return;
        }

        $PAGE->location(
            '?act=ucp&what=inbox'
            . (isset($JAX->b['prevpage']) && $JAX->b['prevpage']
            ? '&page=' . $JAX->b['prevpage'] : ''),
        );
    }
}
