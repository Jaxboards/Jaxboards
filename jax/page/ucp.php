<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\Page;

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
    private $what = '';

    private $runscript = false;

    private $shownucp = false;

    private $ucppage = '';

    /**
     * @var Config
     */
    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
    ) {
        $this->page->loadmeta('ucp');
    }

    public function route(): void
    {
        global $USER;
        if (!$USER || $USER['group_id'] === 4) {
            $this->page->location('?');

            return;
        }

        $this->page->path(['UCP' => '?act=ucp']);
        $this->what = $this->jax->b['what'] ?? '';

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

        if ($this->page->jsupdate) {
            return;
        }

        $this->showucp();
    }

    public function getlocationforform(): string
    {
        return $this->jax->hiddenFormFields(['act' => 'ucp', 'what' => $this->what]);
    }

    public function showinbox(): void
    {
        if (
            isset($this->jax->p['dmessage'])
            && is_array($this->jax->p['dmessage'])
        ) {
            foreach ($this->jax->p['dmessage'] as $v) {
                $this->delete($v, false);
            }
        }

        if (
            isset($this->jax->p['messageid'])
            && is_numeric($this->jax->p['messageid'])
        ) {
            switch (mb_strtolower((string) $this->jax->p['page'])) {
                case 'delete':
                    $this->delete($this->jax->p['messageid']);

                    break;

                case 'forward':
                    $this->compose($this->jax->p['messageid'], 'fwd');

                    break;

                case 'reply':
                    $this->compose($this->jax->p['messageid']);

                    break;

                default:
            }
        } else {
            if (!isset($this->jax->b['page'])) {
                $this->jax->b['page'] = false;
            }

            if ($this->jax->b['page'] === 'compose') {
                $this->compose();
            } elseif (
                isset($this->jax->g['view'])
                && is_numeric($this->jax->g['view'])
            ) {
                $this->viewmessage($this->jax->g['view']);
            } elseif ($this->jax->b['page'] === 'sent') {
                $this->viewmessages('sent');
            } elseif ($this->jax->b['page'] === 'flagged') {
                $this->viewmessages('flagged');
            } elseif (
                isset($this->jax->b['flag'])
                && is_numeric($this->jax->b['flag'])
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
        global $USER;
        $e = '';

        if ($this->page->jsupdate && empty($this->jax->p)) {
            return;
        }

        if (
            isset($this->jax->p['ucpnotepad'])
            && $this->jax->p['ucpnotepad']
        ) {
            if (mb_strlen((string) $this->jax->p['ucpnotepad']) > 2000) {
                $e = 'The UCP notepad cannot exceed 2000 characters.';
                $this->page->JS('error', $e);
            } else {
                $this->database->safeupdate(
                    'members',
                    [
                        'ucpnotepad' => $this->jax->p['ucpnotepad'],
                    ],
                    'WHERE `id`=?',
                    $USER['id'],
                );
                $USER['ucpnotepad'] = $this->jax->p['ucpnotepad'];
            }
        }

        $this->ucppage = ($e !== '' && $e !== '0' ? $this->page->meta('error', $e) : '') . $this->page->meta(
            'ucp-index',
            $this->jax->hiddenFormFields(['act' => 'ucp']),
            $USER['display_name'],
            $this->jax->pick($USER['avatar'], $this->page->meta('default-avatar')),
            trim((string) $USER['ucpnotepad']) !== '' && trim((string) $USER['ucpnotepad']) !== '0'
            ? $this->jax->blockhtml($USER['ucpnotepad']) : 'Personal notes go here.',
        );
        $this->showucp();
    }

    public function showucp($page = false): void
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

        $this->page->updatepath();

        $this->shownucp = true;
    }

    public function showsoundsettings(): ?bool
    {
        global $USER;

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

        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            $update = [];
            foreach ($variables as $v) {
                $update[$v] = isset($this->jax->p[$v]) && $this->jax->p[$v]
                    ? 1
                    : 0;
            }

            $this->database->safeupdate(
                'members',
                $update,
                'WHERE `id`=?',
                $USER['id'],
            );

            foreach ($variables as $v) {
                $this->page->JS(
                    'script',
                    "window.globalsettings.{$v}="
                    . (isset($this->jax->p[$v]) && $this->jax->p[$v] ? 1 : 0),
                );
            }

            $this->page->JS('alert', 'Settings saved successfully.');

            $this->ucppage = 'Settings saved successfully.';
        } elseif ($this->page->jsupdate) {
            return true;
        }

        $checkboxes = [
            $this->getlocationforform() . $this->jax->hiddenFormFields(
                ['submit' => 1],
            ),
        ];

        foreach ($variables as $v) {
            $checkboxes[] = '<input type="checkbox" title="' . $v . '" name="' . $v . '" '
                . ($USER[$v] ? 'checked="checked"' : '') . '/>';
        }

        $this->ucppage = $this->page->meta('ucp-sound-settings', $checkboxes);
        $this->runscript = "if(document.querySelector('#dtnotify')&&window.webkitNotifications) "
            . "document.querySelector('#dtnotify').checked=(webkitNotifications.checkPermission()==0)";

        unset($checkboxes);

        return null;
    }

    public function showsigsettings(): void
    {
        global $USER;
        $update = false;
        $sig = $USER['sig'];
        if (isset($this->jax->p['changesig'])) {
            $sig = $this->jax->linkify($this->jax->p['changesig']);
            $this->database->safeupdate(
                'members',
                [
                    'sig' => $sig,
                ],
                'WHERE `id`=?',
                $USER['id'],
            );
            $update = true;
        }

        $this->ucppage = $this->page->meta(
            'ucp-sig-settings',
            $this->getlocationforform(),
            $sig !== ''
            ? $this->jax->theworks($sig) : '( none )',
            $this->jax->blockhtml($sig),
        );
        if (!$update) {
            return;
        }

        $this->showucp();
    }

    public function showpasssettings()
    {
        global $USER;
        $e = '';
        if (isset($this->jax->p['passchange'])) {
            if (!isset($this->jax->p['showpass'])) {
                $this->jax->p['showpass'] = false;
            }

            if (
                !$this->jax->p['showpass']
                && $this->jax->p['newpass1'] !== $this->jax->p['newpass2']
            ) {
                $e = 'Those passwords do not match.';
            }

            if (
                !$this->jax->p['newpass1']
                || !$this->jax->p['showpass']
                && !$this->jax->p['newpass2']
                || !$this->jax->p['curpass']
            ) {
                $e = 'All form fields are required.';
            }

            $verified_password = password_verify((string) $this->jax->p['curpass'], (string) $USER['pass']);
            if (!$verified_password) {
                $e = 'The password you entered is incorrect.';
            }

            if ($e === '' || $e === '0') {
                $hashpass = password_hash((string) $this->jax->p['newpass1'], PASSWORD_DEFAULT);
                $this->database->safeupdate(
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

            $this->ucppage .= $this->page->meta('error', $e);
            $this->page->JS('error', $e);
        }

        $this->ucppage .= $this->page->meta(
            'ucp-pass-settings',
            $this->getlocationforform()
            . $this->jax->hiddenFormFields(['passchange' => 1]),
        );
    }

    public function showemailsettings()
    {
        global $USER;
        $e = '';
        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            if (
                $this->jax->p['email']
                && !$this->jax->isemail($this->jax->p['email'])
            ) {
                $e = 'Please enter a valid email!';
            }

            if ($e !== '' && $e !== '0') {
                $this->page->JS('alert', $e);
            } else {
                $this->database->safeupdate(
                    'members',
                    [
                        'email' => $this->jax->p['email'],
                        'email_settings' => ($this->jax->p['notifications'] ?? false ? 2 : 0)
                        + ($this->jax->p['adminemails'] ?? false ? 1 : 0),
                    ],
                    'WHERE `id`=?',
                    $USER['id'],
                );
                $this->ucppage = 'Email settings updated.'
                    . '<br><br><a href="?act=ucp&what=email">Back</a>';
            }

            return $this->showucp();
        }

        $this->ucppage .= $this->page->meta(
            'ucp-email-settings',
            $this->getlocationforform() . $this->jax->hiddenFormFields(
                ['submit' => 'true'],
            ),
            isset($this->jax->b['change']) && $this->jax->b['change'] ? <<<HTML
                <input
                    type="text"
                    name="email"
                    aria-label="Email"
                    title="Enter your new email address"
                    value="{$USER['email']}" />
                HTML : '<strong>' . $this->jax->pick($USER['email'], '--none--')
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
        global $USER;
        $e = '';
        $update = false;
        if (isset($this->jax->p['changedava'])) {
            if (
                $this->jax->p['changedava']
                && !$this->jax->isurl($this->jax->p['changedava'])
            ) {
                $e = 'Please enter a valid image URL.';
            } else {
                $this->database->safeupdate(
                    'members',
                    ['avatar' => $this->jax->p['changedava']],
                    'WHERE `id`=?',
                    $USER['id'],
                );
                $USER['avatar'] = $this->jax->p['changedava'];
            }

            $update = true;
        }

        $this->ucppage = 'Your avatar: <span class="avatar"><img src="'
            . $this->jax->pick($USER['avatar'], $this->page->meta('default-avatar'))
            . '" alt="Your avatar"></span><br><br>
            <form data-ajax-form="true" method="post">'
            . $this->getlocationforform()
            . ($e !== '' && $e !== '0' ? $this->page->error($e) : '')
            . '<input type="text" name="changedava" title="Your avatar" value="'
            . $this->jax->blockhtml($USER['avatar']) . '" />
            <input type="submit" value="Edit" />
            </form>';
        if (!$update) {
            return;
        }

        $this->showucp();
    }

    public function showprofilesettings(): void
    {
        global $USER;
        $error = '';
        $genderOptions = ['', 'male', 'female', 'other'];
        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            // Insert the profile info into the database.
            $data = [
                'about' => $this->jax->p['about'],
                'contact_aim' => $this->jax->p['con_aim'],
                'contact_bluesky' => $this->jax->p['con_bluesky'],
                'contact_discord' => $this->jax->p['con_discord'],
                'contact_gtalk' => $this->jax->p['con_gtalk'],
                'contact_msn' => $this->jax->p['con_msn'],
                'contact_skype' => $this->jax->p['con_skype'],
                'contact_steam' => $this->jax->p['con_steam'],
                'contact_twitter' => $this->jax->p['con_twitter'],
                'contact_yim' => $this->jax->p['con_yim'],
                'contact_youtube' => $this->jax->p['con_youtube'],
                'display_name' => trim((string) $this->jax->p['display_name']),
                'dob_day' => $this->jax->pick($this->jax->p['dob_day'], null),
                'dob_month' => $this->jax->pick($this->jax->p['dob_month'], null),
                'dob_year' => $this->jax->pick($this->jax->p['dob_year'], null),
                'full_name' => $this->jax->p['full_name'],
                'gender' => in_array($this->jax->p['gender'], $genderOptions)
                ? $this->jax->p['gender'] : '',
                'location' => $this->jax->p['location'],
                'usertitle' => $this->jax->p['usertitle'],
                'website' => $this->jax->p['website'],
            ];

            // Begin input checking.
            if ($data['display_name'] === '') {
                $data['display_name'] = $USER['name'];
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
                    $USER['id'],
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
                ] as $k => $v
            ) {
                if (
                    mb_strstr($k, 'contact') !== false
                    && preg_match('/[^\w.@]/', (string) $data[$k])
                ) {
                    $error = "Invalid characters in {$v}";
                }

                $data[$k] = $this->jax->blockhtml($data[$k]);
                $length = $k === 'display_name'
                    ? 30
                    : ($k === 'location' ? 100 : 50);
                if (mb_strlen($data[$k]) <= $length) {
                    continue;
                }

                $error = "{$v} must be less than {$length} characters.";
            }

            // Handle errors/insert.
            if ($error === '' || $error === '0') {
                if ($data['display_name'] !== $USER['display_name']) {
                    $this->database->safeinsert(
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

                $this->database->safeupdate(
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

            $this->ucppage .= $this->page->meta('error', $error);
            $this->page->JS('error', $error);
        }

        $data = $USER;
        $genderselect = '<select name="gender" title="Your gender" aria-label="Gender">';
        foreach (['', 'male', 'female', 'other'] as $v) {
            $genderselect .= '<option value="' . $v . '"'
                . ($data['gender'] === $v ? ' selected="selected"' : '')
                . '>' . $this->jax->pick(ucfirst($v), 'Not telling') . '</option>';
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

        $this->ucppage = $this->page->meta(
            'ucp-profile-settings',
            $this->getlocationforform()
            . $this->jax->hiddenFormFields(['submit' => '1']),
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
        global $USER;
        $e = '';
        $showthing = false;
        if (
            isset($this->jax->b['skin'])
            && is_numeric($this->jax->b['skin'])
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
                $this->jax->b['skin'],
            );
            if (!$this->database->arow($result)) {
                $e = 'The skin chosen no longer exists.';
            } else {
                $this->database->disposeresult($result);
                $this->database->safeupdate(
                    'members',
                    [
                        'nowordfilter' => isset($this->jax->p['usewordfilter'])
                        && $this->jax->p['usewordfilter'] ? 0 : 1,
                        'skin_id' => $this->jax->b['skin'],
                        'wysiwyg' => isset($this->jax->p['wysiwyg'])
                        && $this->jax->p['wysiwyg'] ? 1 : 0,
                    ],
                    'WHERE `id`=?',
                    $USER['id'],
                );
                $USER['skin_id'] = $this->jax->b['skin'];
            }

            if ($e === '') {
                if ($this->page->jsaccess) {
                    $this->page->JS('reload');

                    return;
                }

                header('Location: ?act=ucp&what=board');

                return;
            }

            $this->ucppage .= $this->page->meta('error', $e);

            $showthing = true;
        }

        $result = $USER['group_id'] !== 2
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
        while ($f = $this->database->arow($result)) {
            $select .= "<option value='" . $f['id'] . "' "
                . ($USER['skin_id'] === $f['id'] ? "selected='selected'" : '')
                . '/>' . ($f['hidden'] ? '*' : '') . $f['title'] . '</option>';
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
        global $USER;
        $this->page->JS('softurl');
        $this->database->safeupdate(
            'messages',
            [
                'flag' => $this->jax->b['tog'] ? 1 : 0,
            ],
            'WHERE `id`=? AND `to`=?',
            $this->database->basicvalue($this->jax->b['flag']),
            $USER['id'],
        );
    }

    public function viewmessage($messageid): void
    {
        global $USER;
        if ($this->page->jsupdate && !$this->page->jsdirectlink) {
            return;
        }

        $e = '';
        $result = $this->database->safespecial(
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
            $this->database->basicvalue($messageid),
        );
        $message = $this->database->arow($result);
        $this->database->disposeresult($result);
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
            $this->jax->theworks($message['message']),
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

    public function updatenummessages(): void
    {
        global $USER;
        $result = $this->database->safeselect(
            'COUNT(`id`)',
            'messages',
            'WHERE `to`=? AND !`read`',
            $USER['id'],
        );
        $unread = $this->database->arow($result);
        $this->database->disposeresult($result);

        $unread = array_pop($unread);
        $this->page->JS('update', 'num-messages', $unread);
    }

    public function viewmessages($view = 'inbox'): void
    {
        global $USER;

        if ($this->page->jsupdate && empty($this->jax->p)) {
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
                $USER['id'],
            );
        } elseif ($view === 'flagged') {
            $result = $this->database->safespecial(
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
            $result = $this->database->safespecial(
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
        while ($f = $this->database->arow($result)) {
            $hasmessages = 1;
            if (!$f['read']) {
                ++$unread;
            }

            $dmessageOnchange = "RUN.stream.location('"
                . '?act=ucp&what=inbox&flag=' . $f['id'] . "&tog='+" . '
                (this.checked?1:0), 1)';
            $page .= $this->page->meta(
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
                $this->jax->date($f['date']),
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

    public function compose($messageid = '', $todo = ''): void
    {
        global $USER;
        $showfull = 0;
        $e = '';
        $mid = 0;
        $mname = '';
        $mtitle = '';
        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            $mid = $this->jax->b['mid'];
            if (!$mid && $this->jax->b['to']) {
                $result = $this->database->safeselect(
                    [
                        'id',
                        'email',
                        'email_settings',
                    ],
                    'members',
                    'WHERE `display_name`=?',
                    $this->database->basicvalue($this->jax->b['to']),
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
                $e = 'Invalid user!';
            } elseif (
                trim((string) $this->jax->b['title']) === ''
                || trim((string) $this->jax->b['title']) === '0'
            ) {
                $e = 'You must enter a title.';
            }

            if ($e !== '' && $e !== '0') {
                $this->page->JS('error', $e);
                $this->page->append('PAGE', $this->page->error($e));
            } else {
                // Put it into the table.
                $this->database->safeinsert(
                    'messages',
                    [
                        'date' => gmdate('Y-m-d H:i:s'),
                        'del_recipient' => 0,
                        'del_sender' => 0,
                        'from' => $USER['id'],
                        'message' => $this->jax->p['message'],
                        'read' => 0,
                        'title' => $this->jax->blockhtml($this->jax->p['title']),
                        'to' => $udata['id'],
                    ],
                );
                // Give them a notification.
                $cmd = json_encode(
                    [
                        'newmessage',
                        'You have a new message from ' . $USER['display_name'],
                        $this->database->insert_id(),
                    ],
                ) . PHP_EOL;
                $result = $this->database->safespecial(
                    <<<'EOT'
                        UPDATE %t
                        SET `runonce`=concat(`runonce`,?)
                        WHERE `uid`=?
                        EOT
                    ,
                    ['session'],
                    $this->database->basicvalue($cmd),
                    $udata['id'],
                );
                // Send em an email!
                if (($udata['email_settings'] & 2) !== 0) {
                    $this->jax->mail(
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

        if ($this->page->jsupdate && !$messageid) {
            return;
        }

        $msg = '';
        if ($messageid) {
            $result = $this->database->safeselect(
                [
                    '`from`',
                    'message',
                    'title',
                ],
                'messages',
                'WHERE (`to`=? OR `from`=?) AND `id`=?',
                $USER['id'],
                $USER['id'],
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

        if (isset($this->jax->g['mid']) && is_numeric($this->jax->g['mid'])) {
            $showfull = 1;
            $mid = $this->jax->b['mid'];
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

    public function delete($id, $relocate = true): void
    {
        global $USER;
        $result = $this->database->safeselect(
            [
                '`to`',
                '`from`',
                'del_recipient',
                'del_sender',
            ],
            'messages',
            'WHERE `id`=?',
            $this->database->basicvalue($id),
        );
        $message = $this->database->arow($result);
        $this->database->disposeresult($result);

        $is_recipient = $message['to'] === $USER['id'];
        $is_sender = $message['from'] === $USER['id'];
        if ($is_recipient) {
            $this->database->safeupdate(
                'messages',
                [
                    'del_recipient' => 1,
                ],
                'WHERE `id`=?',
                $this->database->basicvalue($id),
            );
        }

        if ($is_sender) {
            $this->database->safeupdate(
                'messages',
                [
                    'del_sender' => 1,
                ],
                'WHERE `id`=?',
                $this->database->basicvalue($id),
            );
        }

        $result = $this->database->safeselect(
            [
                'del_recipient',
                'del_sender',
            ],
            'messages',
            'WHERE `id`=?',
            $this->database->basicvalue($id),
        );
        $message = $this->database->arow($result);
        $this->database->disposeresult($result);

        if ($message['del_recipient'] && $message['del_sender']) {
            $this->database->safedelete(
                'messages',
                'WHERE `id`=?',
                $this->database->basicvalue($id),
            );
        }

        if (!$relocate) {
            return;
        }

        $this->page->location(
            '?act=ucp&what=inbox'
            . (isset($this->jax->b['prevpage']) && $this->jax->b['prevpage']
            ? '&page=' . $this->jax->b['prevpage'] : ''),
        );
    }
}
