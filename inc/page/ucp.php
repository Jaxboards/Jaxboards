<?php

$PAGE->loadmeta('ucp');

new UCP();
class UCP
{
    public $what = '';
    public $runscript = false;
    public $shownucp = false;
    public $ucppage = '';

    public function __construct()
    {
        global $PAGE,$JAX,$USER,$DB;
        if (!$USER || 4 == $USER['group_id']) {
            return $PAGE->location('?');
        }
        $result = $DB->safeselect(
            <<<'EOT'
`id`,`name`,`pass`,`email`,`sig`,`posts`,`group_id`,`avatar`,`usertitle`,
`join_date`,`last_visit`,`contact_skype`,`contact_yim`,`contact_msn`,
`contact_gtalk`,`contact_aim`,`website`,`dob_day`,`dob_month`,`dob_year`,
`about`,`display_name`,`full_name`,`contact_steam`,`location`,`gender`,
`friends`,`enemies`,`sound_shout`,`sound_im`,`sound_pm`,`sound_postinmytopic`,
`sound_postinsubscribedtopic`,`notify_pm`,`notify_postinmytopic`,
`notify_postinsubscribedtopic`,`ucpnotepad`,`skin_id`,`contact_twitter`,
`email_settings`,`nowordfilter`,INET6_NTOA(`ip`) AS `ip`,`mod`,`wysiwyg`
EOT
            ,
            'members',
            'WHERE `id`=?',
            $DB->basicvalue($USER['id'])
        );
        $GLOBALS['USER'] = $DB->arow($result);
        $DB->disposeresult($result);

        $PAGE->path(array('UCP' => '?act=ucp'));
        $this->what = isset($JAX->b['what']) ? $JAX->b['what'] : '';
        switch ($this->what) {
            case 'sounds':
                $this->showsoundsettings();
                break;
            case 'signature':
                $this->showsigsettings();
                break;
            case 'pass':
                $this->showpasssettings();
                break;
            case 'email':
                $this->showemailsettings();
                break;
            case 'avatar':
                $this->showavatarsettings();
                break;
            case 'profile':
                $this->showprofilesettings();
                break;
            case 'board':
                $this->showboardsettings();
                break;
            case 'inbox':
                if (isset($JAX->p['dmessage'])
                && is_array($JAX->p['dmessage'])
                ) {
                    foreach ($JAX->p['dmessage'] as $v) {
                        $this->delete($v, false);
                    }
                }
                if (isset($JAX->p['messageid'])
                && is_numeric($JAX->p['messageid'])
                ) {
                    switch (mb_strtolower($JAX->p['page'])) {
                        case 'delete':
                            $this->delete($JAX->p['messageid']);
                            break;
                        case 'forward':
                            $this->compose($JAX->p['messageid'], 'fwd');
                            break;
                        case 'reply':
                            $this->compose($JAX->p['messageid']);
                            break;
                    }
                } else {
                    if (!isset($JAX->b['page'])) {
                        $JAX->b['page'] = false;
                    }
                    if ('compose' == $JAX->b['page']) {
                        $this->compose();
                    } elseif (isset($JAX->g['view'])
                    && is_numeric($JAX->g['view'])
                    ) {
                        $this->viewmessage($JAX->g['view']);
                    } elseif ('sent' == $JAX->b['page']) {
                        $this->viewmessages('sent');
                    } elseif ('flagged' == $JAX->b['page']) {
                        $this->viewmessages('flagged');
                    } elseif (isset($JAX->b['flag'])
                    && is_numeric($JAX->b['flag'])
                    ) {
                        return $this->flag($JAX->b['flag']);
                    } else {
                        $this->viewmessages();
                    }
                }
                break;
            default:
                if ($PAGE->jsupdate && empty($JAX->p)) {
                    return;
                }
                $this->showmain();
                break;
        }
        if (!$PAGE->jsaccess || $PAGE->jsnewlocation) {
            $this->showucp();
        }
    }

    public function getlocationforform()
    {
        global $JAX;

        return $JAX->hiddenFormFields(array('act' => 'ucp', 'what' => $this->what));
    }

    public function showmain()
    {
        global $PAGE,$JAX,$USER,$DB;
        $e = '';
        if (isset($JAX->p['ucpnotepad']) && $JAX->p['ucpnotepad']) {
            if (mb_strlen($JAX->p['ucpnotepad']) > 2000) {
                $e = 'The UCP notepad cannot exceed 2000 characters.';
                $PAGE->JS('error', $e);
            } else {
                $DB->safeupdate(
                    'members',
                    array(
                        'ucpnotepad' => $JAX->p['ucpnotepad'],
                    ),
                    'WHERE `id`=?',
                    $USER['id']
                );
                $USER['ucpnotepad'] = $JAX->p['ucpnotepad'];
            }
        }
        $this->ucppage = ($e ? $PAGE->meta('error', $e) : '') . $PAGE->meta(
            'ucp-index',
            $JAX->hiddenFormFields(array('act' => 'ucp')),
            $USER['display_name'],
            $JAX->pick($USER['avatar'], $PAGE->meta('default-avatar')),
            trim($USER['ucpnotepad']) ?
            $JAX->blockhtml($USER['ucpnotepad']) : 'Personal notes go here.'
        );
        $this->showentirething = true;
        $this->showucp();
    }

    public function showucp($page = false)
    {
        global $PAGE;
        if ($this->shownucp) {
            return;
        }
        if (!$page) {
            $page = $this->ucppage;
        }

        $page = $PAGE->meta('ucp-wrapper', $page);
        //$PAGE->JS("window",Array("id"=>"ucpwin","title"=>"Settings","content"=>$page,"animate"=>false));
        $PAGE->append('PAGE', $page);
        $PAGE->JS('update', 'page', $page);
        if ($this->runscript) {
            $PAGE->JS('script', $this->runscript);
        }
        $PAGE->updatepath();

        $this->shownucp = true;
    }

    public function showsoundsettings()
    {
        global $USER,$PAGE,$JAX,$DB;

        $variables = array(
            'sound_shout',
            'sound_im',
            'sound_pm',
            'notify_pm',
            'sound_postinmytopic',
            'notify_postinmytopic',
            'sound_postinsubscribedtopic',
            'notify_postinsubscribedtopic',
        );

        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            $update = array();
            foreach ($variables as $v) {
                $update[$v] = (isset($JAX->p[$v]) && $JAX->p[$v]) ? 1 : 0;
            }
            $DB->safeupdate(
                'members',
                $update,
                'WHERE `id`=?',
                $USER['id']
            );

            foreach ($variables as $v) {
                $PAGE->JS(
                    'script',
                    "window.globalsettings.${v}=" .
                    ((isset($JAX->p[$v]) && $JAX->p[$v]) ? 1 : 0)
                );
            }

            $PAGE->JS('alert', 'Settings saved successfully.');

            $PAGE->ucppage = 'Settings saved successfully.';
        } elseif ($PAGE->jsupdate) {
            return true;
        }

        $checkboxes = array(
            $this->getlocationforform() . $JAX->hiddenFormFields(
                array('submit' => 1)
            ), );

        foreach ($variables as $v) {
            $checkboxes[] = '<input type="checkbox" name="' . $v . '" ' .
                ($USER[$v] ? 'checked="checked"' : '') . '/>';
        }

        $this->ucppage = $PAGE->meta('ucp-sound-settings', $checkboxes);
        $this->runscript = "if($('dtnotify')&&window.webkitNotifications) " .
            "$('dtnotify').checked=(webkitNotifications.checkPermission()==0)";

        unset($checkboxes);
    }

    public function showsigsettings()
    {
        global $USER,$JAX,$DB,$PAGE;
        $update = false;
        $sig = $USER['sig'];
        if (isset($JAX->p['changesig'])) {
            $sig = $JAX->linkify($JAX->p['changesig']);
            $DB->safeupdate(
                'members',
                array(
                    'sig' => $sig,
                ),
                'WHERE `id`=?',
                $USER['id']
            );
            $update = true;
        }
        $this->ucppage = $PAGE->meta(
            'ucp-sig-settings',
            $this->getlocationforform(),
            '' !== $sig ?
            $JAX->theworks($sig) : '( none )',
            $JAX->blockhtml($sig)
        );
        if ($update) {
            $this->showucp();
        }
    }

    public function showpasssettings()
    {
        global $JAX,$USER,$PAGE,$DB;
        $e = '';
        if (isset($JAX->p['passchange'])) {
            if (!isset($JAX->p['showpass'])) {
                $JAX->p['showpass'] = false;
            }
            if (!$JAX->p['showpass'] && $JAX->p['newpass1'] != $JAX->p['newpass2']) {
                $e = 'Those passwords do not match.';
            }
            if (!$JAX->p['newpass1']
                || !$JAX->p['showpass']
                && !$JAX->p['newpass2']
                || !$JAX->p['curpass']
            ) {
                $e = 'All form fields are required.';
            }
            $verified_password = password_verify($JAX->p['curpass'], $USER['pass']);
            if (!$verified_password) {
                // check if it's an old md5 hash
                if (md5($JAX->p['curpass']) === $USER['pass']) {
                    $verified_password = true;
                }
            }
            if (!$verified_password) {
                $e = 'The password you entered is incorrect.';
            }
            if ($e) {
                $this->ucppage .= $PAGE->meta('error', $e);
                $PAGE->JS('error', $e);
            } else {
                $hashpass = password_hash($JAX->p['newpass1'], PASSWORD_DEFAULT);
                $DB->safeupdate(
                    'members',
                    array(
                        'pass' => $hashpass,
                    ),
                    'WHERE `id`=?',
                    $USER['id']
                );
                $this->ucppage = <<<'EOT'
Password changed.
    <br /><br />
    <a href="?act=ucp&what=pass">Back</a>
EOT;

                return $this->showucp();
            }
        }
        $this->ucppage .= $PAGE->meta(
            'ucp-pass-settings',
            $this->getlocationforform()
            . $JAX->hiddenFormFields(array('passchange' => 1))
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
            if ($e) {
                $PAGE->JS('alert', $e);
            } else {
                $DB->safeupdate(
                    'members',
                    array(
                        'email' => $JAX->p['email'],
                        'email_settings' => ($JAX->p['notifications'] ? 2 : 0)
                        + ($JAX->p['adminemails'] ? 1 : 0),
                    ),
                    'WHERE `id`=?',
                    $USER['id']
                );
                $this->ucppage = 'Email settings updated.' .
                    '<br /><br /><a href="?act=ucp&what=email">Back</a>';
            }

            return $this->showucp();
        }
        $this->ucppage .= $PAGE->meta(
            'ucp-email-settings',
            $this->getlocationforform() . $JAX->hiddenFormFields(
                array('submit' => 'true')
            ),
            ((isset($JAX->b['change']) && $JAX->b['change']) ?
            "<input type='text' name='email' value='" . $USER['email'] . "' />" :
            '<strong>' . $JAX->pick($USER['email'], '--none--') .
            "</strong> <a href='?act=ucp&what=email&change=1'>Change</a>" .
            "<input type='hidden' name='email' value='" . ($USER['email']) . "' />"
            ),
            '<input type="checkbox" name="notifications"' .
            ($USER['email_settings'] & 2 ? " checked='checked'" : '') . '>',
            '<input type="checkbox" name="adminemails"' .
            ($USER['email_settings'] & 1 ? ' checked="checked"' : '') . '>'
        );
    }

    public function showavatarsettings()
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
                    array('avatar' => $JAX->p['changedava']),
                    'WHERE `id`=?',
                    $USER['id']
                );
                $USER['avatar'] = $JAX->p['changedava'];
            }
            $update = true;
        }
        $this->ucppage = 'Your avatar: <span class="avatar"><img src="' .
            $JAX->pick($USER['avatar'], $PAGE->meta('default-avatar')) .
            '" alt="Unable to load avatar"></span><br /><br />
            <form onsubmit="return RUN.submitForm(this)" method="post">' .
            $this->getlocationforform()
            . ($e ? $PAGE->error($e) : '') .
            '<input type="text" name="changedava" value="' .
            $JAX->blockhtml($USER['avatar']) . '" />
            <input type="submit" value="Edit" />
            </form>';
        if ($update) {
            $this->showucp();
        }
    }

    public function showprofilesettings()
    {
        global $USER,$JAX,$PAGE,$DB,$CFG;
        $error = '';
        $genderOptions = array('', 'male', 'female', 'other');
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            //insert profile info into the database'
            $data = array(
                'display_name' => trim($JAX->p['display_name']),
                'full_name' => $JAX->p['full_name'],
                'usertitle' => $JAX->p['usertitle'],
                'about' => $JAX->p['about'],
                'location' => $JAX->p['location'],
                'dob_month' => $JAX->pick($JAX->p['dob_month'], null),
                'dob_day' => $JAX->pick($JAX->p['dob_day'], null),
                'dob_year' => $JAX->pick($JAX->p['dob_year'], null),
                'contact_yim' => $JAX->p['con_yim'],
                'contact_msn' => $JAX->p['con_msn'],
                'contact_gtalk' => $JAX->p['con_gtalk'],
                'contact_skype' => $JAX->p['con_skype'],
                'contact_aim' => $JAX->p['con_aim'],
                'contact_steam' => $JAX->p['con_steam'],
                'contact_twitter' => $JAX->p['con_twitter'],
                'website' => $JAX->p['website'],
                'gender' => in_array($JAX->p['gender'], $genderOptions) ?
                $JAX->p['gender'] : '',
            );

            /* BEGIN input checking */

            if ('' === $data['display_name']) {
                $data['display_name'] = $USER['name'];
            }
            if ($CFG['badnamechars']
                && preg_match($CFG['badnamechars'], $data['display_name'])
            ) {
                $error = 'Invalid characters in display name!';
            } else {
                $result = $DB->safeselect(
                    'COUNT(`id`) AS `same_display_name`',
                    'members',
                    'WHERE `display_name` = ? AND `id`!=? LIMIT 1',
                    $DB->basicvalue($data['display_name']),
                    $USER['id']
                );
                $displayNameCheck = $DB->arow($result);
                if (0 < $displayNameCheck['same_display_name']) {
                    $error = 'That display name is already in use.';
                }
            }
            if (!$data['dob_month']) {
                $data['dob_month'] = 0;
            }
            if (!$data['dob_year']) {
                $data['dob_year'] = 0;
            }
            if (!$data['dob_day']) {
                $data['dob_day'] = 0;
            }
            if ($data['dob_month'] || $data['dob_year'] || $data['dob_day']) {
                if (!is_numeric($data['dob_month'])
                    || !is_numeric($data['dob_day'])
                    && !is_numeric($data['dob_year'])
                ) {
                    $error = "That isn't a valid birth date.";
                }
                if (($data['dob_month'] % 2)
                    && 31 == $data['dob_day']
                    || 2 == $data['dob_month']
                    && (!$data['dob_year'] % 4
                    && $data['dob_day'] > 29
                    || $data['dob_year'] % 4
                    && $data['dob_day'] > 28)
                ) {
                    $error = "That birth date doesn't exist!";
                }
            }
            foreach (array(
                'contact_yim' => 'YIM username',
                'contact_msn' => 'MSN username',
                'contact_gtalk' => 'Google Talk username',
                'contact_steam' => 'Steam username',
                'contact_twitter' => 'Twitter ID',
                'contact_aim' => 'AIM username',
                'contact_skype' => 'Skype username',
                'full_name' => 'Full name',
                'display_name' => 'Display name',
                'website' => 'Website URL',
                'usertitle' => 'User Title',
                'location' => 'Location',
            ) as $k => $v) {
                if (false !== mb_strstr($k, 'contact')
                    && preg_match('/[^\\w.@]/', $data[$k])
                ) {
                    $error = "Invalid characters in ${v}";
                }

                $data[$k] = $JAX->blockhtml($data[$k]);
                $length = 'display_name' == $k ? 30 : ('location' == $k ? 100 : 50);
                if (mb_strlen($data[$k]) > $length) {
                    $error = "${v} must be less than ${length} characters.";
                }
            }

            /*Handle errors/insert*/

            if (!$error) {
                if ($data['display_name'] != $USER['display_name']) {
                    $DB->safeinsert(
                        'activity',
                        array(
                            'type' => 'profile_name_change',
                            'arg1' => $USER['display_name'],
                            'arg2' => $data['display_name'],
                            'uid' => $USER['id'],
                            'date' => time(),
                        )
                    );
                }
                $DB->safeupdate(
                    'members',
                    $data,
                    'WHERE `id`=?',
                    $USER['id']
                );
                $this->ucppage = 'Profile successfully updated.<br />' .
                    '<br /><a href="?act=ucp&what=profile">Back</a>';
                $this->showucp();

                return;
            }
            $PAGE->ucppage .= $PAGE->meta('error', $error);
            $PAGE->JS('error', $error);
        }
        $data = $USER;
        $genderselect = '<select name="gender">';
        foreach (array('', 'male', 'female', 'other') as $v) {
            $genderselect .= '<option value="' . $v . '"' .
                ($data['gender'] == $v ? ' selected="selected"' : '') .
                '>' . $JAX->pick(ucfirst($v), 'Not telling') . '</option>';
        }
        $genderselect .= '</select>';

        $dobselect = '<select name="dob_month"><option value="">--</option>';
        $fullMonthNames = array(
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
        );
        foreach ($fullMonthNames as $k => $v) {
            $dobselect .= '<option value="' . ($k + 1) . '"' .
                (($k + 1) == $data['dob_month'] ? ' selected="selected"' : '') .
                '>' . $v . '</option>';
        }
        $dobselect .= '</select><select name="dob_day"><option value="">--</option>';
        for ($x = 1; $x < 32; ++$x) {
            $dobselect .= '<option value="' . $x . '"' .
                ($x == $data['dob_day'] ? ' selected="selected"' : '') .
                '>' . $x . '</option>';
        }
        $dobselect .= '</select><select name="dob_year">' .
            '<option value="">--</option>';
        $thisyear = (int) date('Y');
        for ($x = $thisyear; $x > $thisyear - 100; --$x) {
            $dobselect .= '<option value="' . $x . '"' .
                ($x == $data['dob_year'] ? ' selected="selected"' : '') .
                '>' . $x . '</option>';
        }
        $dobselect .= '</select>';

        $this->ucppage = $PAGE->meta(
            'ucp-profile-settings',
            $this->getlocationforform() .
            $JAX->hiddenFormFields(array('submit' => '1')),
            $USER['name'],
            $data['display_name'],
            $data['full_name'],
            $data['usertitle'],
            $data['about'],
            $data['location'],
            $genderselect,
            $dobselect,
            $data['contact_skype'],
            $data['contact_yim'],
            $data['contact_msn'],
            $data['contact_gtalk'],
            $data['contact_aim'],
            $data['contact_steam'],
            $data['contact_twitter'],
            $data['website']
        );
    }

    public function showboardsettings()
    {
        global $PAGE,$DB,$JAX,$USER;
        $e = '';
        $showthing = false;
        if (isset($JAX->b['skin']) && is_numeric($JAX->b['skin'])) {
            $result = $DB->safeselect(
                '`id`,`using`,`title`,`custom`,`wrapper`,`default`,`hidden`',
                'skins',
                'WHERE `id`=?',
                $JAX->b['skin']
            );
            if (!$DB->arow($result)) {
                $e = 'The skin chosen no longer exists.';
            } else {
                $DB->disposeresult($result);
                $DB->safeupdate(
                    'members',
                    array(
                        'skin_id' => $JAX->b['skin'],
                        'nowordfilter' => (isset($JAX->p['usewordfilter'])
                        && $JAX->p['usewordfilter']) ? 0 : 1,
                        'wysiwyg' => (isset($JAX->p['wysiwyg'])
                        && $JAX->p['wysiwyg']) ? 1 : 0,
                    ),
                    'WHERE `id`=?',
                    $USER['id']
                );
                $USER['skin_id'] = $JAX->b['skin'];
            }
            if (!$e) {
                if ($PAGE->jsaccess) {
                    return $PAGE->JS('script', 'document.location.reload()');
                }

                return header('Location: ?act=ucp&what=board');
            }
            $this->ucppage .= $PAGE->meta('error', $e);

            $showthing = true;
        }
        $result = (2 != $USER['group_id']) ?
            $DB->safeselect(
                '`id`,`using`,`title`,`custom`,`wrapper`,`default`,`hidden`',
                'skins',
                'WHERE `hidden`!=1 ORDER BY `title` ASC'
            ) :
            $DB->safeselect(
                '`id`,`using`,`title`,`custom`,`wrapper`,`default`,`hidden`',
                'skins',
                'ORDER BY `title` ASC'
            );
        $select = '';
        while ($f = $DB->arow($result)) {
            $select .= "<option value='" . $f['id'] . "' " .
                ($USER['skin_id'] == $f['id'] ? "selected='selected'" : '') .
                '/>' . ($f['hidden'] ? '*' : '') . $f['title'] . '</option>';
            $found = true;
        }
        $select = '<select name="skin">' . $select . '</select>';
        if (!$found) {
            $select = '--No Skins--';
        }
        $this->ucppage .= $PAGE->meta(
            'ucp-board-settings',
            $this->getlocationforform(),
            $select,
            '<input type="checkbox" name="usewordfilter"' .
            (!$USER['nowordfilter'] ? ' checked="checked"' : '') .
            ' />',
            '<input type="checkbox" name="wysiwyg"' .
            ($USER['wysiwyg'] ? ' checked="checked"' : '') .
            ' />'
        );
        if ($showthing) {
            $this->showucp();
        }
    }

    /* HERE BE PRIVATE MESSAGING
    ARRRRRRRRRRRRRRRRRRRRRRRR */

    public function flag()
    {
        global $PAGE,$DB,$JAX,$USER;
        $PAGE->JS('softurl');
        $DB->safeupdate(
            'messages',
            array(
                'flag' => $JAX->b['tog'] ? 1 : 0,
            ),
            'WHERE `id`=? AND `to`=?',
            $DB->basicvalue($JAX->b['flag']),
            $USER['id']
        );
    }

    public function viewmessage($messageid)
    {
        global $PAGE,$DB,$JAX,$USER;
        if ($PAGE->jsupdate && !$PAGE->jsdirectlink) {
            return;
        }
        $e = '';
        $result = $DB->safespecial(
            <<<'EOT'
SELECT a.`id` AS `id`,a.`to` AS `to`,a.`from` AS `from`,a.`title` AS `title`,
	a.`message` AS `message`,a.`read` AS `read`,a.`date` AS `date`,
	a.`del_recipient` AS `del_recipient`,a.`del_sender` AS `del_sender`,
	a.`flag` AS `flag`,m.`group_id` AS `group_id`,m.`display_name` AS `name`,
    m.`avatar` AS `avatar`,m.`usertitle` AS `usertitle`
FROM %t a
LEFT JOIN %t m
    ON a.`from`=m.`id`
WHERE a.`id`=?
ORDER BY a.`date` DESC
EOT
            ,
            array('messages', 'members'),
            $DB->basicvalue($messageid)
        );
        $message = $DB->arow($result);
        $DB->disposeresult($result);
        if ($message['from'] != $USER['id'] && $message['to'] != $USER['id']) {
            $e = "You don't have permission to view this message.";
        }
        if ($e) {
            return $this->showucp($e);
        }
        if (!$message['read'] && $message['to'] == $USER['id']) {
            $DB->safeupdate(
                'messages',
                array('read' => 1),
                'WHERE `id`=?',
                $message['id']
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
                $message['name']
            ),
            $JAX->date($message['date']),
            $JAX->theworks($message['message']),
            $JAX->pick($message['avatar'], $PAGE->meta('default-avatar')),
            $message['usertitle'],
            $JAX->hiddenFormFields(
                array(
                    'act' => 'ucp',
                    'what' => 'inbox',
                    'messageid' => $message['id'],
                    'sender' => $message['from'],
                )
            )
        );
        $this->showucp($page);
    }

    public function updatenummessages()
    {
        global $DB,$PAGE,$USER;
        $result = $DB->safeselect(
            'COUNT(`id`)',
            'messages',
            'WHERE `to`=? AND !`read`',
            $USER['id']
        );
        $unread = $DB->arow($result);
        $DB->disposeresult($result);

        $unread = array_pop($unread);
        $PAGE->JS('update', 'num-messages', $unread);
    }

    public function viewmessages($view = 'inbox')
    {
        global $PAGE,$DB,$JAX,$USER;

        if ($PAGE->jsupdate && empty($JAX->p)) {
            return;
        }
        $page = '';
        $result = null;
        $hasmessages = false;
        if ('sent' == $view) {
            $result = $DB->safespecial(
                <<<'EOT'
SELECT a.`id` AS `id`,a.`to` AS `to`,a.`from` AS `from`,
	a.`title` AS `title`,a.`message` AS `message`,a.`read` AS `read`,
	a.`date` AS `date`,a.`del_recipient` AS `del_recipient`,
	a.`del_sender` AS `del_sender`,a.`flag` AS `flag`,
    m.`display_name` AS `display_name`
FROM %t a
LEFT JOIN %t m
    ON a.`to`=m.`id`
WHERE a.`from`=? AND !a.`del_sender`
ORDER BY a.`date` DESC
EOT
                ,
                array('messages', 'members'),
                $USER['id']
            );
        } elseif ('flagged' == $view) {
            $result = $DB->safespecial(
                <<<'EOT'
SELECT a.`id` AS `id`,a.`to` AS `to`,a.`from` AS `from`,a.`title` AS `title`,
	a.`message` AS `message`,a.`read` AS `read`,a.`date` AS `date`,
	a.`del_recipient` AS `del_recipient`,a.`del_sender` AS `del_sender`,
	a.`flag` AS `flag`,m.`display_name` AS `display_name`
FROM %t a
LEFT JOIN %t m
    ON a.`from`=m.`id`
WHERE a.`to`=? AND a.`flag`=1
ORDER BY a.`date` DESC
EOT
                ,
                array('messages', 'members'),
                $USER['id']
            );
        } else {
            $result = $DB->safespecial(
                <<<'EOT'
SELECT a.`id` AS `id`,a.`to` AS `to`,a.`from` AS `from`,a.`title` AS `title`,
	a.`message` AS `message`,a.`read` AS `read`,a.`date` AS `date`,
    a.`del_recipient` AS `del_recipient`,a.`del_sender` AS `del_sender`,
    a.`flag` AS `flag`,m.`display_name` AS `display_name`
FROM %t a
LEFT JOIN %t m
ON a.`from`=m.`id`
WHERE a.`to`=? AND !a.`del_recipient`
ORDER BY a.`date` DESC
EOT
                ,
                array('messages', 'members'),
                $USER['id']
            );
        }
        $unread = 0;
        while ($f = $DB->arow($result)) {
            $hasmessages = 1;
            if (!$f['read']) {
                ++$unread;
            }
            $dmessageOnclick = 'RUN.stream.location(\'' .
                '?act=ucp&what=inbox&flag=' . $f['id'] . '&tog=\'+' . '
                (this.checked?1:0))';
            $page .= $PAGE->meta(
                'inbox-messages-row',
                (!$f['read'] ? 'unread' : 'read'),
                '<input class="check" type="checkbox" name="dmessage[]" ' .
                'value="' . $f['id'] . '" />',
                '<input type="checkbox" ' .
                ($f['flag'] ? 'checked="checked" ' : '') .
                'class="switch flag" onclick="' . $dmessageOnclick . '" />',
                $f['id'],
                $f['title'],
                $f['display_name'],
                $JAX->date($f['date'])
            );
        }

        if (!$hasmessages) {
            if ('sent' == $view) {
                $msg = 'No sent messages.';
            } elseif ('flagged' == $view) {
                $msg = 'No flagged messages.';
            } else {
                $msg = 'No messages. You could always try ' .
                    '<a href="?act=ucp&what=inbox&page=compose">' .
                    'sending some</a>, though!';
            }
            $page .= '<tr><td colspan="5" class="error">' . $msg . '</td></tr>';
        }

        $page = $PAGE->meta(
            'inbox-messages-listing',
            $JAX->hiddenFormFields(
                array(
                    'act' => 'ucp',
                    'what' => 'inbox',
                )
            ),
            'sent' == $view ? 'Recipient' : 'Sender',
            $page
        );

        if ('inbox' == $view) {
            $PAGE->JS('update', 'num-messages', $unread);
        }
        $this->showucp($page);
    }

    public function compose($messageid = '', $todo = '')
    {
        global $PAGE,$JAX,$USER,$DB,$CFG;
        $showfull = 0;
        $e = '';
        $mid = 0;
        $mname = '';
        $mtitle = '';
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            $mid = $JAX->b['mid'];
            if (!$mid && $JAX->b['to']) {
                $result = $DB->safeselect(
                    '`id`,`email`,`email_settings`',
                    'members',
                    'WHERE `display_name`=?',
                    $DB->basicvalue($JAX->b['to'])
                );
                $udata = $DB->arow($result);
                $DB->disposeresult($result);
            } else {
                $result = $DB->safeselect(
                    '`id`,`email`,`email_settings`',
                    'members',
                    'WHERE `id`=?',
                    $DB->basicvalue($mid)
                );
                $udata = $DB->arow($result);
                $DB->disposeresult($result);
            }
            if (!$udata) {
                $e = 'Invalid user!';
            } elseif (!trim($JAX->b['title'])) {
                $e = 'You must enter a title.';
            }
            if ($e) {
                $PAGE->JS('error', $e);
                $PAGE->append('PAGE', $PAGE->error($e));
            } else {
                //put it into the table
                $DB->safeinsert(
                    'messages',
                    array(
                        'to' => $udata['id'],
                        'from' => $USER['id'],
                        'title' => $JAX->blockhtml($JAX->p['title']),
                        'message' => $JAX->p['message'],
                        'date' => time(),
                        'del_sender' => 0,
                        'del_recipient' => 0,
                        'read' => 0,
                    )
                );
                //give them a notification
                $cmd = $JAX->json_encode(
                    array(
                        'newmessage',
                        'You have a new message from ' .
                        $USER['display_name'], $DB->insert_id(1),
                    )
                ) . PHP_EOL;
                $result = $DB->safespecial(
                    <<<'EOT'
UPDATE %t
SET `runonce`=concat(`runonce`,?)
WHERE `uid`=?
EOT
                    ,
                    array('session'),
                    $DB->basicvalue($cmd, 1),
                    $udata['id']
                );
                //send em an email!
                if ($udata['email_settings'] & 2) {
                    $JAX->mail(
                        $udata['email'],
                        'PM From ' . $USER['display_name'],
                        "You are receiving this email because you've " .
                        'received a message from ' . $USER['display_name'] .
                        ' on {BOARDLINK}.<br />' .
                        '<br />Please go to ' .
                        "<a href='{BOARDURL}?act=ucp&what=inbox'>" .
                        '{BOARDURL}?act=ucp&what=inbox</a>' .
                        ' to view your message.'
                    );
                }

                $this->showucp(
                    'Message successfully delivered.' .
                    "<br /><br /><a href='?act=ucp&what=inbox'>Back</a>"
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
                <<<'EOT'
`id`,`to`,`from`,`title`,`message`,`read`,`date`,`del_recipient`,`del_sender`,
`flag`
EOT
                ,
                'messages',
                'WHERE (`to`=? OR `from`=?) AND `id`=?',
                $USER['id'],
                $USER['id'],
                $DB->basicvalue($messageid)
            );

            $message = $DB->arow($result);
            $DB->disposeresult($result);

            $mid = $message['from'];
            $result = $DB->safeselect(
                '`display_name`',
                'members',
                'WHERE `id`=?',
                $mid
            );
            $thisrow = $DB->arow($result);
            $mname = array_pop($thisrow);
            $DB->disposeresult($result);

            $msg = PHP_EOL . PHP_EOL . PHP_EOL .
                '[quote=' . $mname . ']' . $message['message'] . '[/quote]';
            $mtitle = ('fwd' == $todo ? 'FWD:' : 'RE:') . $message['title'];
            if ('fwd' == $todo) {
                $mid = $mname = '';
            }
        }
        if (isset($JAX->g['mid']) && is_numeric($JAX->g['mid'])) {
            $showfull = 1;
            $mid = $JAX->b['mid'];
            $result = $DB->safeselect(
                '`display_name`',
                'members',
                'WHERE `id`=?',
                $mid
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
            $JAX->hiddenFormFields(
                array(
                    'act' => 'ucp',
                    'what' => 'inbox',
                    'page' => 'compose',
                    'submit' => '1',
                )
            ),
            $mid,
            $mname,
            ($mname ? 'good' : ''),
            $mtitle,
            htmlspecialchars($msg)
        );
        $this->showucp($page);
    }

    public function delete($id, $relocate = true)
    {
        global $PAGE,$JAX,$DB,$USER;
        $result = $DB->safeselect(
            <<<'EOT'
`id`,`to`,`from`,`title`,`message`,`read`,`date`,`del_recipient`,`del_sender`,
`flag`
EOT
            ,
            'messages',
            'WHERE `id`=?',
            $DB->basicvalue($id)
        );
        $message = $DB->arow($result);
        $DB->disposeresult($result);

        $is_recipient = $message['to'] == $USER['id'];
        $is_sender = $message['from'] == $USER['id'];
        if ($is_recipient) {
            $DB->safeupdate(
                'messages',
                array(
                    'del_recipient' => 1,
                ),
                'WHERE `id`=?',
                $DB->basicvalue($id)
            );
        }
        if ($is_sender) {
            $DB->safeupdate(
                'messages',
                array(
                    'del_sender' => 1,
                ),
                'WHERE `id`=?',
                $DB->basicvalue($id)
            );
        }
        $result = $DB->safeselect(
            <<<'EOT'
`id`,`to`,`from`,`title`,`message`,`read`,`date`,`del_recipient`,`del_sender`,
`flag`
EOT
            ,
            'messages',
            'WHERE `id`=?',
            $DB->basicvalue($id)
        );
        $message = $DB->arow($result);
        $DB->disposeresult($result);

        if ($message['del_recipient'] && $message['del_sender']) {
            $DB->safedelete(
                'messages',
                'WHERE `id`=?',
                $DB->basicvalue($id)
            );
        }
        if ($relocate) {
            $PAGE->location(
                '?act=ucp&what=inbox' .
                (isset($JAX->b['prevpage']) && $JAX->b['prevpage'] ?
                '&page=' . $JAX->b['prevpage'] : '')
            );
        }
    }
}
