<?php

if (!defined(INACP)) {
    die();
}
new members();
class members
{
    public function __construct()
    {
        global $JAX,$PAGE;
        if (!isset($JAX->b['do'])) {
            $JAX->b['do'] = null;
        }
        switch ($JAX->b['do']) {
            case 'merge':
                $this->merge();
                break;
            case 'edit':
                $this->editmem();
                break;
            case 'delete':
                $this->deletemem();
                break;
            case 'prereg':
                $this->preregister();
                break;
            case 'massmessage':
                $this->massmessage();
                break;
            case 'ipbans':
                $this->ipbans();
                break;
            case 'validation':
                $this->validation();
                break;
            default:
                $this->showmain();
                break;
        }
        $links = array(
            'edit' => 'Edit Members',
            'prereg' => 'Pre-Register',
            'merge' => 'Account Merge',
            'delete' => 'Delete Account',
            'massmessage' => 'Mass Message',
            'ipbans' => 'IP Bans',
            'validation' => 'Validation',
        );
        $sidebarLinks = '';
        foreach ($links as $do => $title) {
            $sidebarLinks .= $PAGE->parseTemplate(
                'sidebar-list-link.html',
                array(
                    'url' => '?act=members&do=' . $do,
                    'title' => $title,
                )
            ) . PHP_EOL;
        }
        /*$sidebarLinks .= $PAGE->parseTemplate(
            'sidebar-list-link.html',
            array(
                'url' => '?act=stats',
                'title' => 'Recount Statistics',
            )
        ) . PHP_EOL;*/

        $PAGE->sidebar(
            $PAGE->parseTemplate(
                'sidebar-list.html',
                array(
                    'content' => $sidebarLinks,
                )
            )
        );
    }

    public function showmain()
    {
        global $PAGE,$DB,$JAX;
        $result = $DB->safespecial(
            <<<'EOT'
SELECT m.`id` AS `id`,m.`avatar` AS `avatar`,
    m.`display_name` AS `display_name`,m.`group_id` AS `group_id`,
    g.`title` AS `group_title`
FROM %t m
LEFT JOIN %t g
    ON m.`group_id`=g.`id`
ORDER BY m.`display_name` ASC
EOT
            ,
            array('members', 'member_groups')
        );
        $rows = '';
        while ($f = $DB->arow($result)) {
            $rows .= $PAGE->parseTemplate(
                'members/show-main-row.html',
                array(
                    'avatar_url' => $JAX->pick(
                        $f['avatar'],
                        AVAURL . 'default.gif'
                    ),
                    'id' => $f['id'],
                    'title' => $f['display_name'],
                    'group_title' => $f['group_title'],
                )
            ) . PHP_EOL;
        }
        $PAGE->addContentBox(
            'Member List',
            $PAGE->parseTemplate(
                'members/show-main.html',
                array(
                    'rows' => $rows,
                )
            )
        );
    }

    public function editmem()
    {
        global $PAGE,$JAX,$DB;
        $page = '';
        if ((isset($JAX->b['mid']) && $JAX->b['mid'])
            || (isset($JAX->p['submit']) && $JAX->p['submit'])
        ) {
            if (isset($JAX->b['mid'])
                && $JAX->b['mid']
                && is_numeric($JAX->b['mid'])
            ) {
                $result = $DB->safeselect(
                    <<<'EOT'
`id`,`name`,`pass`,`email`,`sig`,`posts`,`group_id`,`avatar`,`usertitle`,
UNIX_TIMESTAMP(`join_date`) AS `join_date`,
UNIX_TIMESTAMP(`last_visit`) AS `last_visit`,
`contact_skype`,`contact_yim`,`contact_msn`,`contact_gtalk`,`contact_aim`,
`website`,`birthdate`, DAY(`birthdate`) AS `dob_day`,
MONTH(`birthdate`) AS `dob_month`, YEAR(`birthdate`) AS `dob_year`,
`about`,`display_name`,`full_name`,`contact_steam`,`location`,`gender`,
`friends`,`enemies`,`sound_shout`,`sound_im`,`sound_pm`,`sound_postinmytopic`,
`sound_postinsubscribedtopic`,`notify_pm`,`notify_postinmytopic`,
`notify_postinsubscribedtopic`,`ucpnotepad`,`skin_id`,`contact_twitter`,
`contact_discord`,`contact_youtube`,`contact_bluesky`,
`email_settings`,`nowordfilter`,INET6_NTOA(`ip`) AS `ip`,`mod`,`wysiwyg`
EOT
                    ,
                    'members',
                    'WHERE `id`=?',
                    $DB->basicvalue($JAX->b['mid'])
                );
                $data = $DB->arow($result);
                $DB->disposeresult($result);
                if (isset($JAX->p['savedata']) && $JAX->p['savedata']) {
                    if (2 != $data['group_id'] || 1 == $JAX->userData['id']) {
                        $write = array();
                        if ($JAX->p['password']) {
                            $write['pass'] = password_hash(
                                $JAX->p['password'],
                                PASSWORD_DEFAULT
                            );
                        }
                        $fields = array(
                            'display_name',
                            'name',
                            'full_name',
                            'usertitle',
                            'location',
                            'avatar',
                            'about',
                            'sig',
                            'email',
                            'ucpnotepad',
                            'contact_aim',
			    'contact_bluesky',
			    'contact_discord',
                            'contact_gtalk',
                            'contact_msn',
                            'contact_skype',
                            'contact_steam',
                            'contact_twitter',
                            'contact_yim',
			    'contact_youtube',
                            'website',
                            'posts',
                            'group_id',
                        );
                        foreach ($fields as $field) {
                            if (isset($JAX->p[$field])) {
                                $write[$field] = $JAX->p[$field];
                            }
                        }
                        // Make it so root admins can't get out of admin.
                        if (1 == $JAX->b['mid']) {
                            $write['group_id'] = 2;
                        }
                        $DB->safeupdate(
                            'members',
                            $write,
                            'WHERE `id`=?',
                            $DB->basicvalue($JAX->b['mid'])
                        );
                        $page = $PAGE->success('Profile data saved');
                    } else {
                        $page = $PAGE->error(
                            'You do not have permission to edit this profile.' .
                            $PAGE->back()
                        );
                    }
                }
                $result = $DB->safeselect(
                    <<<'EOT'
`id`,`name`,`pass`,`email`,`sig`,`posts`,`group_id`,`avatar`,`usertitle`,
UNIX_TIMESTAMP(`join_date`) AS `join_date`,
UNIX_TIMESTAMP(`last_visit`) AS `last_visit`,
`contact_skype`,`contact_yim`,`contact_msn`,`contact_gtalk`,`contact_aim`,
`website`,`birthdate`, DAY(`birthdate`) AS `dob_day`,
MONTH(`birthdate`) AS `dob_month`, YEAR(`birthdate`) AS `dob_year`,
`about`,`display_name`,`full_name`,`contact_steam`,`location`,`gender`,
`friends`,`enemies`,`sound_shout`,`sound_im`,`sound_pm`,`sound_postinmytopic`,
`sound_postinsubscribedtopic`,`notify_pm`,`notify_postinmytopic`,
`notify_postinsubscribedtopic`,`ucpnotepad`,`skin_id`,`contact_twitter`,
`contact_discord`,`contact_youtube`,`contact_bluesky`,
`email_settings`,`nowordfilter`,INET6_NTOA(`ip`) AS `ip`,`mod`,`wysiwyg`
EOT
                    ,
                    'members',
                    'WHERE `id`=?',
                    $DB->basicvalue($JAX->b['mid'])
                );
            } else {
                $result = $DB->safeselect(
                    <<<'EOT'
`id`,`name`,`pass`,`email`,`sig`,`posts`,`group_id`,`avatar`,`usertitle`,
UNIX_TIMESTAMP(`join_date`) AS `join_date`,
UNIX_TIMESTAMP(`last_visit`) AS `last_visit`,
`contact_skype`,`contact_yim`,`contact_msn`,`contact_gtalk`,`contact_aim`,
`website`,`birthdate`, DAY(`birthdate`) AS `dob_day`,
MONTH(`birthdate`) AS `dob_month`, YEAR(`birthdate`) AS `dob_year`,
`about`,`display_name`,`full_name`,`contact_steam`,`location`,`gender`,
`friends`,`enemies`,`sound_shout`,`sound_im`,`sound_pm`,`sound_postinmytopic`,
`sound_postinsubscribedtopic`,`notify_pm`,`notify_postinmytopic`,
`notify_postinsubscribedtopic`,`ucpnotepad`,`skin_id`,`contact_twitter`,
`contact_discord`,`contact_youtube`,`contact_bluesky`,
`email_settings`,`nowordfilter`,INET6_NTOA(`ip`) AS `ip`,`mod`,`wysiwyg`
EOT
                    ,
                    'members',
                    'WHERE `display_name` LIKE ?',
                    $DB->basicvalue($JAX->p['name'] . '%')
                );
            }
            $data = array();
            while ($f = $DB->arow($result)) {
                $data[] = $f;
            }
            $nummembers = count($data);
            if ($nummembers > 1) {
                foreach ($data as $v) {
                    $page .= $PAGE->parseTemplate(
                        'members/edit-select-option.html',
                        array(
                            'avatar_url' => $JAX->pick(
                                $v['avatar'],
                                AVAURL . 'default.gif'
                            ),
                            'id' => $v['id'],
                            'title' => $v['display_name'],
                        )
                    ) . PHP_EOL;
                }

                return $PAGE->addContentBox('Select Member to Edit', $page);
            }
            if (!$nummembers) {
                return $PAGE->addContentBox(
                    'Error',
                    $PAGE->error('This member does not exist. ' . $PAGE->back())
                );
            }
            $data = array_pop($data);
            if (2 == $data['group_id'] && 1 != $JAX->userData['id']) {
                $page = $PAGE->error(
                    'You do not have permission to edit this profile. ' .
                    $PAGE->back()
                );
            } else {
                $page .= $JAX->hiddenFormFields(array('mid' => $data['id']));
                $page .= $this->formfield('Display Name:', 'display_name', $data['display_name']);
                $page .= $this->formfield('Username:', 'name', $data['name']);
                $page .= $this->formfield('Real Name:', 'full_name', $data['full_name']);
                $page .= $this->formfield('Password:', 'password', '');
                $page .= $this->getGroups($data['group_id']);
                $page .= $this->heading('Profile Fields');
                $page .= $this->formfield('User Title:', 'usertitle', $data['usertitle']);
                $page .= $this->formfield('Location:', 'location', $data['location']);
                $page .= $this->formfield('Website:', 'website', $data['website']);
                $page .= $this->formfield('Avatar:', 'avatar', $data['avatar']);
                $page .= $this->formfield('About:', 'about', $data['about'], 'textarea');
                $page .= $this->formfield('Signature:', 'sig', $data['sig'], 'textarea');
                $page .= $this->formfield('Email:', 'email', $data['email']);
                $page .= $this->formfield('UCP Notepad:', 'ucpnotepad', $data['ucpnotepad'], 'textarea');
                $page .= $this->heading('Contact Details');
                $page .= $this->formfield('AIM:', 'contact_aim', $data['contact_aim']);
		$page .= $this->formfield('Bluesky:', 'contact_bluesky', $data['contact_bluesky']);
                $page .= $this->formfield('Discord:', 'contact_discord', $data['contact_discord']);
                $page .= $this->formfield('Google Chat:', 'contact_gtalk', $data['contact_gtalk']);
		$page .= $this->formfield('MSN:', 'contact_msn', $data['contact_msn']);
                $page .= $this->formfield('Skype:', 'contact_skype', $data['contact_skype']);
                $page .= $this->formfield('Steam:', 'contact_steam', $data['contact_steam']);
                $page .= $this->formfield('Twitter:', 'contact_twitter', $data['contact_twitter']);
                $page .= $this->formfield('YIM:', 'contact_yim', $data['contact_yim']);
		$page .= $this->formfield('YouTube:', 'contact_youtube', $data['contact_youtube']);
                $page .= $this->heading('System-Generated Variables');
                $page .= $this->formfield('Post Count:', 'posts', $data['posts']);
                $page = $PAGE->parseTemplate('members/edit-form.html',
                    array('content' => $page,)
                );
            }
        } else {
            $page = $PAGE->parseTemplate(
                'members/edit.html'
            );
        }
        $PAGE->addContentBox(
            (isset($data['name']) && $data['name']) ?
            'Editing ' . $data['name'] . "'s details" : 'Edit Member',
            $page
        );
    }

    public function preregister()
    {
        global $PAGE,$JAX,$DB;
        $page = '';
        $e = '';
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (!$JAX->p['username']
                || !$JAX->p['displayname']
                || !$JAX->p['pass']
            ) {
                $e = 'All fields required.';
            } elseif (mb_strlen($JAX->p['username']) > 30
                || $JAX->p['displayname'] > 30
            ) {
                $e = 'Display name and username must be under 30 characters.';
            } else {
                $result = $DB->safeselect(
                    '`name`,`display_name`',
                    'members',
                    'WHERE `name`=? OR `display_name`=?',
                    $DB->basicvalue($JAX->p['username']),
                    $DB->basicvalue($JAX->p['displayname'])
                );
                if ($f = $DB->arow($result)) {
                    $e = 'That ' . ($f['name'] == $JAX->p['username'] ?
                        'username' : 'display name') . ' is already taken';
                }

                $DB->disposeresult($result);
            }

            if ($e) {
                $page .= $PAGE->error($e);
            } else {
                $member = array(
                    'name' => $JAX->p['username'],
                    'display_name' => $JAX->p['displayname'],
                    'pass' => password_hash(
                        $JAX->p['pass'],
                        PASSWORD_DEFAULT
                    ),
                    'last_visit' => date('Y-m-d H:i:s', time()),
                    'birthdate' => '0000-00-00',
                    'group_id' => 1,
                    'posts' => 0,
                );
                $result = $DB->safeinsert('members', $member);
                $error = $DB->error();
                $DB->disposeresult($result);
                if (!$error) {
                    $page .= $PAGE->success('Member registered.');
                } else {
                    $page .= $PAGE->error(
                        'An error occurred while processing your request. ' .
                        $error
                    );
                }
            }
        }
        $page .= $PAGE->parseTemplate(
            'members/pre-register.html'
        );
        $PAGE->addContentBox('Pre-Register', $page);
    }

    public function getGroups($group_id = 0)
    {
        global $DB, $PAGE;
        $page = '';
        $result = $DB->safeselect(
            '`id`,`title`',
            'member_groups',
            'ORDER BY `title` DESC'
        );
        while ($f = $DB->arow($result)) {
            $page .= $PAGE->parseTemplate(
                'select-option.html',
                array(
                    'value' => $f['id'],
                    'label' => $f['title'],
                    'selected' => $group_id == $f['id'] ? ' selected="selected"' : '',
                )
            ) . PHP_EOL;
        }

        return $PAGE->parseTemplate(
            'members/get-groups.html',
            array(
                'content' => $page,
            )
        );
    }

    public function merge()
    {
        global $PAGE,$JAX,$DB;
        $page = '';
        $e = '';
        if (!isset($JAX->p['submit'])) {
            $JAX->p['submit'] = false;
        }
        if ($JAX->p['submit']) {
            if (!$JAX->p['mid1'] || !$JAX->p['mid2']) {
                $e = 'All fields are required';
            } elseif (!is_numeric($JAX->p['mid1']) || !is_numeric($JAX->p['mid2'])) {
                $e = 'An error occurred in processing your request';
            } elseif ($JAX->p['mid1'] == $JAX->p['mid2']) {
                $e = "Can't merge a member with her/himself";
            }
            if ($e) {
                $page .= $PAGE->error($e);
            } else {
                $mid1 = $DB->basicvalue($JAX->p['mid1']);
                $mid1int = $JAX->p['mid1'];
                $mid2 = $JAX->p['mid2'];

                // Files.
                $DB->safeupdate(
                    'files',
                    array(
                        'uid' => $mid2,
                    ),
                    'WHERE `uid`=?',
                    $mid1
                );
                // PMs.
                $DB->safeupdate(
                    'messages',
                    array(
                        'to' => $mid2,
                    ),
                    'WHERE `to`=?',
                    $mid1
                );
                $DB->safeupdate(
                    'messages',
                    array(
                        'from' => $mid2,
                    ),
                    'WHERE `from`=?',
                    $mid1
                );
                // Posts.
                $DB->safeupdate(
                    'posts',
                    array(
                        'auth_id' => $mid2,
                    ),
                    'WHERE `auth_id`=?',
                    $mid1
                );
                // Profile comments.
                $DB->safeupdate(
                    'profile_comments',
                    array(
                        'to' => $mid2,
                    ),
                    'WHERE `to`=?',
                    $mid1
                );
                $DB->safeupdate(
                    'profile_comments',
                    array(
                        'from' => $mid2,
                    ),
                    'WHERE `from`=?',
                    $mid1
                );
                // Topics.
                $DB->safeupdate(
                    'topics',
                    array(
                        'auth_id' => $mid2,
                    ),
                    'WHERE `auth_id`=?',
                    $mid1
                );
                $DB->safeupdate(
                    'topics',
                    array(
                        'lp_uid' => $mid2,
                    ),
                    'WHERE `lp_uid`=?',
                    $mid1
                );

                // Forums.
                $DB->safeupdate(
                    'forums',
                    array(
                        'lp_uid' => $mid2,
                    ),
                    'WHERE `lp_uid`=?',
                    $mid1
                );

                // Shouts.
                $DB->safeupdate(
                    'shouts',
                    array(
                        'uid' => $mid2,
                    ),
                    'WHERE `uid`=?',
                    $mid1
                );

                // Session.
                $DB->safeupdate(
                    'session',
                    array(
                        'uid' => $mid2,
                    ),
                    'WHERE `uid`=?',
                    $mid1
                );

                // Sum post count on account being merged into.
                $result = $DB->safeselect(
                    '`posts`,`id`',
                    'members',
                    'WHERE `id`=?',
                    $mid1
                );
                $posts = $DB->arow($result);
                $DB->disposeresult($result);

                if (!$posts) {
                    $posts = 0;
                } else {
                    $posts = $posts['posts'];
                }
                $DB->safespecial(
                    'UPDATE %t SET `posts` = `posts` + ? WHERE `id`=?',
                    array('members'),
                    $posts,
                    $mid2
                );

                // Delete the account.
                $DB->safedelete('members', 'WHERE `id`=?', $mid1);

                // Update stats.
                $DB->safespecial(
                    <<<'EOT'
UPDATE %t
SET `members` = `members` - 1,
    `last_register` = (SELECT MAX(`id`) FROM %t)
EOT
                    ,
                    array('stats', 'members')
                );
                $page .= $PAGE->success('Successfully merged the two accounts.');
            }
        }
        $page .= '';
        $PAGE->addContentBox(
            'Account Merge',
            $page . PHP_EOL .
            $PAGE->parseTemplate(
                'members/merge.html'
            )
        );
    }

    public function deletemem()
    {
        global $PAGE,$JAX,$DB;
        $page = '';
        $e = '';
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (!$JAX->p['mid']) {
                $e = 'All fields are required';
            } elseif (!is_numeric($JAX->p['mid'])) {
                $e = 'An error occurred in processing your request';
            }
            if ($e) {
                $page .= $PAGE->error($e);
            } else {
                $mid = $DB->basicvalue($JAX->p['mid']);

                // PMs.
                $DB->safedelete('messages', 'WHERE `to`=?', $mid);
                $DB->safedelete('messages', 'WHERE `from`=?', $mid);
                // Posts.
                $DB->safedelete('posts', 'WHERE `auth_id`=?', $mid);
                // Profile comments.
                $DB->safedelete('profile_comments', 'WHERE `to`=?', $mid);
                $DB->safedelete('profile_comments', 'WHERE `from`=?', $mid);
                // Topics.
                $DB->safedelete('topics', 'WHERE `auth_id`=?', $mid);

                // Forums.
                $DB->safeupdate(
                    'forums',
                    array(
                        'lp_uid' => null,
                        'lp_date' => '0000-00-00 00:00:00',
                        'lp_tid' => null,
                        'lp_topic' => '',
                    ),
                    'WHERE `lp_uid`=?',
                    $mid
                );

                // Shouts.
                $DB->safedelete('shouts', 'WHERE `uid`=?', $mid);

                // Session.
                $DB->safedelete('session', 'WHERE `uid`=?', $mid);

                // Delete the account.
                $DB->safedelete('members', 'WHERE `id`=?', $mid);

                $DB->fixAllForumLastPosts();

                // Update stats.
                $DB->safespecial(
                    <<<'EOT'
UPDATE %t
SET `members` = `members` - 1,
    `last_register` = (SELECT MAX(`id`) FROM %t)
EOT
                    ,
                    array('stats', 'members')
                );
                $page .= $PAGE->success(
                    'Successfully deleted the member account. ' .
                    'Board Stat Recount suggested.'
                );
            }
        }
        $PAGE->addContentBox(
            'Delete Account',
            $page . PHP_EOL .
            $PAGE->parseTemplate(
                'members/delete.html'
            )
        );
    }

    public function ipbans()
    {
        global $PAGE,$JAX;
        $page = '';
        if (isset($JAX->p['ipbans'])) {
            $data = explode(PHP_EOL, $JAX->p['ipbans']);
            foreach ($data as $k => $v) {
                $iscomment = false;
                // Check to see if each line is an ip, if it isn't,
                // add a comment.
                if ('#' == $v[0]) {
                    $iscomment = true;
                } elseif (!filter_var($v, FILTER_VALIDATE_IP)) {
                    if (mb_strstr($v, '.')) {
                        // IPv4 stuff.
                        $d = explode('.', $v);
                        if (!trim($v)) {
                            continue;
                        }
                        if (count($d) > 4) {
                            $iscomment = true;
                        } elseif (count($d) < 4 && '.' != mb_substr($v, -1)) {
                            $iscomment = true;
                        } else {
                            foreach ($d as $v2) {
                                if ($v2 && (is_numeric($v2) && $v2 > 255)) {
                                    $iscomment = true;
                                }
                            }
                        }
                    } elseif (mb_strstr($v, ':')) {
                        // Must be IPv6.
                        if (!filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                            // Only need to run these checks if
                            // it's not a valid IPv6 address.
                            $d = explode(':', $v);
                            if (!trim($v)) {
                                continue;
                            }
                            if (count($d) > 8) {
                                $iscomment = true;
                            } elseif (':' !== mb_substr($v, -1)) {
                                $iscomment = true;
                            } else {
                                foreach ($d as $v2) {
                                    if (!ctype_xdigit($v2)
                                        || mb_strlen($v2) > 4
                                    ) {
                                        $iscomment = true;
                                    }
                                }
                            }
                        }
                    }
                }
                if ($iscomment) {
                    $data[$k] = '#' . $v;
                }
            }
            $data = implode(PHP_EOL, $data);
            $o = fopen(BOARDPATH . 'bannedips.txt', 'w');
            fwrite($o, $data);
            fclose($o);
        } else {
            if (file_exists(BOARDPATH . 'bannedips.txt')) {
                $data = file_get_contents(BOARDPATH . 'bannedips.txt');
            } else {
                $data = '';
            }
        }
        $PAGE->addContentBox(
            'IP Bans',
            $PAGE->parseTemplate(
                'members/ip-bans.html',
                array(
                    'content' => htmlspecialchars($data),
                )
            )
        );
    }

    public function massmessage()
    {
        global $PAGE,$JAX,$DB;
        $page = '';
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (!trim($JAX->p['title']) || !trim($JAX->p['message'])) {
                $page .= $PAGE->error('All fields required!');
            } else {
                $q = $DB->safeselect(
                    '`id`',
                    'members',
                    'WHERE (?-UNIX_TIMESTAMP(`last_visit`))<?',
                    time(),
                    (60 * 60 * 24 * 31 * 6)
                );
                $num = 0;
                while ($f = $DB->arow($q)) {
                    $DB->safeinsert(
                        'messages',
                        array(
                            'to' => $f['id'],
                            'from' => $JAX->userData['id'],
                            'message' => $JAX->p['message'],
                            'title' => $JAX->p['title'],
                            'del_recipient' => 0,
                            'del_sender' => 0,
                            'read' => 0,
                            'flag' => 0,
                            'date' => date('Y-m-d H:i:s', time()),
                        )
                    );
                    ++$num;
                }
                $page .= $PAGE->success("Successfully delivered {$num} messages");
            }
        }
        $PAGE->addContentBox(
            'Mass Message',
            $page . PHP_EOL .
            $PAGE->parseTemplate(
                'members/mass-message.html'
            )
        );
    }

    public function validation()
    {
        global $PAGE,$DB;
        if (isset($_POST['submit1'])) {
            $PAGE->writeCFG(
                array(
                    'membervalidation' => isset($_POST['v_enable'])
                    && $_POST['v_enable'] ? 1 : 0,
                )
            );
        }
        $page = $PAGE->parseTemplate(
            'members/validation.html',
            array(
                'checked' => $PAGE->getCFGSetting('membervalidation')
                ? 'checked="checked"' : '',
            )
        ) . PHP_EOL;
        $PAGE->addContentBox('Enable Member Validation', $page);

        if (isset($_POST['mid'])) {
            if ('Allow' == $_POST['action']) {
                $DB->safeupdate(
                    'members',
                    array(
                        'group_id' => 1,
                    ),
                    'WHERE `id`=?',
                    $DB->basicvalue($_POST['mid'])
                );
            }
        }
        $result = $DB->safeselect(
            '`id`,`display_name`,INET6_NTOA(`ip`) AS `ip`,`email`,' .
            'UNIX_TIMESTAMP(`join_date`) AS `join_date`',
            'members',
            'WHERE `group_id`=5'
        );
        $page = '';
        while ($f = $DB->arow($result)) {
            $page .= $PAGE->parseTemplate(
                'members/validation-list-row.html',
                array(
                    'id' => $f['id'],
                    'title' => $f['display_name'],
                    'ip_address' => $f['ip'],
                    'email_address' => $f['email'],
                    'join_date' => date('M jS, Y @ g:i A', $f['join_date']),
                )
            ) . PHP_EOL;
        }
        $page = $page ? $PAGE->parseTemplate(
            'members/validation-list.html',
            array(
                'content' => $page,
            )
        ) : 'There are currently no members awaiting validation.';
        $PAGE->addContentBox('Members Awaiting Validation', $page);
    }

    public function formfield($label, $name, $value, $which = false)
    {
        global $PAGE;

        if (mb_strtolower($which) === 'textarea') {
            return $PAGE->parseTemplate(
                'members/edit-form-field-textarea.html',
                array(
                    'label' => $label,
                    'title' => $name,
                    'value' => $value,
                )
            ) . PHP_EOL;
        } else {
            return $PAGE->parseTemplate(
                'members/edit-form-field-text.html',
                array(
                    'label' => $label,
                    'title' => $name,
                    'value' => $value,
                )
            ) . PHP_EOL;
        }
    }

    public function heading($value)
    {
        global $PAGE;

        return $PAGE->parseTemplate(
            'members/edit-heading.html',
            array(
                'value' => $value,
            )
        );
    }
}
