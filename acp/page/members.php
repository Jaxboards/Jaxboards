<?php

declare(strict_types=1);

namespace ACP\Page;

use Jax\Config;

use function array_pop;
use function count;
use function ctype_xdigit;
use function explode;
use function fclose;
use function file_exists;
use function file_get_contents;
use function filter_var;
use function fopen;
use function fwrite;
use function gmdate;
use function htmlspecialchars;
use function implode;
use function is_numeric;
use function mb_strlen;
use function mb_strstr;
use function mb_strtolower;
use function mb_substr;
use function password_hash;
use function time;
use function trim;

use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;
use const PASSWORD_DEFAULT;
use const PHP_EOL;

final class Members
{
    public const DEFAULT_AVATAR = '/Service/Themes/Default/avatars/default.gif';

    public function route(): void
    {
        global $JAX,$PAGE;
        if (!isset($JAX->b['do'])) {
            $JAX->b['do'] = null;
        }

        match ($JAX->b['do']) {
            'merge' => $this->merge(),
            'edit' => $this->editmem(),
            'delete' => $this->deletemem(),
            'prereg' => $this->preregister(),
            'massmessage' => $this->massmessage(),
            'ipbans' => $this->ipbans(),
            'validation' => $this->validation(),
            default => $this->showmain(),
        };
        $links = [
            'delete' => 'Delete Account',
            'edit' => 'Edit Members',
            'ipbans' => 'IP Bans',
            'massmessage' => 'Mass Message',
            'merge' => 'Account Merge',
            'prereg' => 'Pre-Register',
            'validation' => 'Validation',
        ];
        $sidebarLinks = '';
        foreach ($links as $do => $title) {
            $sidebarLinks .= $PAGE->parseTemplate(
                'sidebar-list-link.html',
                [
                    'title' => $title,
                    'url' => '?act=members&do=' . $do,
                ],
            ) . PHP_EOL;
        }

        /*
            $sidebarLinks .= $PAGE->parseTemplate(
            'sidebar-list-link.html',
            array(
                'url' => '?act=stats',
                'title' => 'Recount Statistics',
            )
            ) . PHP_EOL;
         */

        $PAGE->sidebar(
            $PAGE->parseTemplate(
                'sidebar-list.html',
                [
                    'content' => $sidebarLinks,
                ],
            ),
        );
    }

    public function showmain(): void
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
            ['members', 'member_groups'],
        );
        $rows = '';
        while ($f = $DB->arow($result)) {
            $rows .= $PAGE->parseTemplate(
                'members/show-main-row.html',
                [
                    'avatar_url' => $JAX->pick(
                        $f['avatar'],
                        self::DEFAULT_AVATAR,
                    ),
                    'group_title' => $f['group_title'],
                    'id' => $f['id'],
                    'title' => $f['display_name'],
                ],
            ) . PHP_EOL;
        }

        $PAGE->addContentBox(
            'Member List',
            $PAGE->parseTemplate(
                'members/show-main.html',
                [
                    'rows' => $rows,
                ],
            ),
        );
    }

    public function editmem()
    {
        global $PAGE,$JAX,$DB;
        $userData = $DB->getUser();
        $page = '';
        if (
            (isset($JAX->b['mid']) && $JAX->b['mid'])
            || (isset($JAX->p['submit']) && $JAX->p['submit'])
        ) {
            if (
                isset($JAX->b['mid'])
                && $JAX->b['mid']
                && is_numeric($JAX->b['mid'])
            ) {
                $result = $DB->safeselect(
                    ['group_id'],
                    'members',
                    'WHERE `id`=?',
                    $DB->basicvalue($JAX->b['mid']),
                );
                $data = $DB->arow($result);
                $DB->disposeresult($result);
                if (isset($JAX->p['savedata']) && $JAX->p['savedata']) {
                    if (
                        $data['group_id'] !== 2 || $userData['id'] === 1
                    ) {
                        $write = [];
                        if ($JAX->p['password']) {
                            $write['pass'] = password_hash(
                                (string) $JAX->p['password'],
                                PASSWORD_DEFAULT,
                            );
                        }

                        $fields = [
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
                        ];
                        foreach ($fields as $field) {
                            if (!isset($JAX->p[$field])) {
                                continue;
                            }

                            $write[$field] = $JAX->p[$field];
                        }

                        // Make it so root admins can't get out of admin.
                        if ($JAX->b['mid'] === 1) {
                            $write['group_id'] = 2;
                        }

                        $DB->safeupdate(
                            'members',
                            $write,
                            'WHERE `id`=?',
                            $DB->basicvalue($JAX->b['mid']),
                        );
                        $page = $PAGE->success('Profile data saved');
                    } else {
                        $page = $PAGE->error(
                            'You do not have permission to edit this profile.'
                            . $PAGE->back(),
                        );
                    }
                }

                $result = $DB->safeselect(
                    [
                        'about',
                        'avatar',
                        'birthdate',
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
                        'display_name',
                        'email_settings',
                        'email',
                        'enemies',
                        'friends',
                        'full_name',
                        'gender',
                        'group_id',
                        'id',
                        'location',
                        '`mod`',
                        'name',
                        'notify_pm',
                        'notify_postinmytopic',
                        'notify_postinsubscribedtopic',
                        'nowordfilter',
                        'pass',
                        'posts',
                        'sig',
                        'skin_id',
                        'sound_im',
                        'sound_pm',
                        'sound_postinmytopic',
                        'sound_postinsubscribedtopic',
                        'sound_shout',
                        'ucpnotepad',
                        'usertitle',
                        'website',
                        'wysiwyg',
                        'DAY(`birthdate`) AS `dob_day`',
                        'UNIX_TIMESTAMP(`join_date`) AS `join_date`',
                        'UNIX_TIMESTAMP(`last_visit`) AS `last_visit`',
                    ],
                    'members',
                    'WHERE `id`=?',
                    $DB->basicvalue($JAX->b['mid']),
                );
            } else {
                $result = $DB->safeselect(
                    [
                        'about',
                        'avatar',
                        'birthdate',
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
                        'display_name',
                        'email_settings',
                        'email',
                        'enemies',
                        'friends',
                        'full_name',
                        'gender',
                        'group_id',
                        'id',
                        'location',
                        '`mod`',
                        'name',
                        'notify_pm',
                        'notify_postinmytopic',
                        'notify_postinsubscribedtopic',
                        'nowordfilter',
                        'pass',
                        'posts',
                        'sig',
                        'skin_id',
                        'sound_im',
                        'sound_pm',
                        'sound_postinmytopic',
                        'sound_postinsubscribedtopic',
                        'sound_shout',
                        'ucpnotepad',
                        'usertitle',
                        'website',
                        'wysiwyg',
                    ],
                    'members',
                    'WHERE `display_name` LIKE ?',
                    $DB->basicvalue($JAX->p['name'] . '%'),
                );
            }

            $data = [];
            while ($f = $DB->arow($result)) {
                $data[] = $f;
            }

            $nummembers = count($data);
            if ($nummembers > 1) {
                foreach ($data as $v) {
                    $page .= $PAGE->parseTemplate(
                        'members/edit-select-option.html',
                        [
                            'avatar_url' => $JAX->pick(
                                $v['avatar'],
                                self::DEFAULT_AVATAR,
                            ),
                            'id' => $v['id'],
                            'title' => $v['display_name'],
                        ],
                    ) . PHP_EOL;
                }

                return $PAGE->addContentBox('Select Member to Edit', $page);
            }

            if ($nummembers === 0) {
                return $PAGE->addContentBox(
                    'Error',
                    $PAGE->error('This member does not exist. ' . $PAGE->back()),
                );
            }

            $data = array_pop($data);
            if ($data['group_id'] === 2 && $userData['id'] !== 1) {
                $page = $PAGE->error(
                    'You do not have permission to edit this profile. '
                    . $PAGE->back(),
                );
            } else {
                $page .= JAX::hiddenFormFields(['mid' => $data['id']]);
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
                $page = $PAGE->parseTemplate(
                    'members/edit-form.html',
                    ['content' => $page],
                );
            }
        } else {
            $page = $PAGE->parseTemplate(
                'members/edit.html',
            );
        }

        $PAGE->addContentBox(
            isset($data['name']) && $data['name']
            ? 'Editing ' . $data['name'] . "'s details" : 'Edit Member',
            $page,
        );

        return null;
    }

    public function preregister(): void
    {
        global $PAGE,$JAX,$DB;
        $page = '';
        $e = '';
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (
                !$JAX->p['username']
                || !$JAX->p['displayname']
                || !$JAX->p['pass']
            ) {
                $e = 'All fields required.';
            } elseif (
                mb_strlen((string) $JAX->p['username']) > 30
                || $JAX->p['displayname'] > 30
            ) {
                $e = 'Display name and username must be under 30 characters.';
            } else {
                $result = $DB->safeselect(
                    ['name', 'display_name'],
                    'members',
                    'WHERE `name`=? OR `display_name`=?',
                    $DB->basicvalue($JAX->p['username']),
                    $DB->basicvalue($JAX->p['displayname']),
                );
                if ($f = $DB->arow($result)) {
                    $e = 'That ' . ($f['name'] === $JAX->p['username']
                        ? 'username' : 'display name') . ' is already taken';
                }

                $DB->disposeresult($result);
            }

            if ($e !== '' && $e !== '0') {
                $page .= $PAGE->error($e);
            } else {
                $member = [
                    'birthdate' => '0000-00-00',
                    'display_name' => $JAX->p['displayname'],
                    'group_id' => 1,
                    'last_visit' => gmdate('Y-m-d H:i:s'),
                    'name' => $JAX->p['username'],
                    'pass' => password_hash(
                        (string) $JAX->p['pass'],
                        PASSWORD_DEFAULT,
                    ),
                    'posts' => 0,
                ];
                $result = $DB->safeinsert('members', $member);
                $error = $DB->error();
                $DB->disposeresult($result);
                if (!$error) {
                    $page .= $PAGE->success('Member registered.');
                } else {
                    $page .= $PAGE->error(
                        'An error occurred while processing your request. '
                        . $error,
                    );
                }
            }
        }

        $page .= $PAGE->parseTemplate(
            'members/pre-register.html',
        );
        $PAGE->addContentBox('Pre-Register', $page);
    }

    public function getGroups($group_id = 0)
    {
        global $DB, $PAGE;
        $page = '';
        $result = $DB->safeselect(
            ['id', 'title'],
            'member_groups',
            'ORDER BY `title` DESC',
        );
        while ($f = $DB->arow($result)) {
            $page .= $PAGE->parseTemplate(
                'select-option.html',
                [
                    'label' => $f['title'],
                    'selected' => $group_id === $f['id'] ? ' selected="selected"' : '',
                    'value' => $f['id'],
                ],
            ) . PHP_EOL;
        }

        return $PAGE->parseTemplate(
            'members/get-groups.html',
            [
                'content' => $page,
            ],
        );
    }

    public function merge(): void
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
            } elseif (
                !is_numeric($JAX->p['mid1'])
                || !is_numeric($JAX->p['mid2'])
            ) {
                $e = 'An error occurred in processing your request';
            } elseif ($JAX->p['mid1'] === $JAX->p['mid2']) {
                $e = "Can't merge a member with her/himself";
            }

            if ($e !== '' && $e !== '0') {
                $page .= $PAGE->error($e);
            } else {
                $mid1 = $DB->basicvalue($JAX->p['mid1']);
                $mid1int = $JAX->p['mid1'];
                $mid2 = $JAX->p['mid2'];

                // Files.
                $DB->safeupdate(
                    'files',
                    [
                        'uid' => $mid2,
                    ],
                    'WHERE `uid`=?',
                    $mid1,
                );
                // PMs.
                $DB->safeupdate(
                    'messages',
                    [
                        'to' => $mid2,
                    ],
                    'WHERE `to`=?',
                    $mid1,
                );
                $DB->safeupdate(
                    'messages',
                    [
                        'from' => $mid2,
                    ],
                    'WHERE `from`=?',
                    $mid1,
                );
                // Posts.
                $DB->safeupdate(
                    'posts',
                    [
                        'auth_id' => $mid2,
                    ],
                    'WHERE `auth_id`=?',
                    $mid1,
                );
                // Profile comments.
                $DB->safeupdate(
                    'profile_comments',
                    [
                        'to' => $mid2,
                    ],
                    'WHERE `to`=?',
                    $mid1,
                );
                $DB->safeupdate(
                    'profile_comments',
                    [
                        'from' => $mid2,
                    ],
                    'WHERE `from`=?',
                    $mid1,
                );
                // Topics.
                $DB->safeupdate(
                    'topics',
                    [
                        'auth_id' => $mid2,
                    ],
                    'WHERE `auth_id`=?',
                    $mid1,
                );
                $DB->safeupdate(
                    'topics',
                    [
                        'lp_uid' => $mid2,
                    ],
                    'WHERE `lp_uid`=?',
                    $mid1,
                );

                // Forums.
                $DB->safeupdate(
                    'forums',
                    [
                        'lp_uid' => $mid2,
                    ],
                    'WHERE `lp_uid`=?',
                    $mid1,
                );

                // Shouts.
                $DB->safeupdate(
                    'shouts',
                    [
                        'uid' => $mid2,
                    ],
                    'WHERE `uid`=?',
                    $mid1,
                );

                // Session.
                $DB->safeupdate(
                    'session',
                    [
                        'uid' => $mid2,
                    ],
                    'WHERE `uid`=?',
                    $mid1,
                );

                // Sum post count on account being merged into.
                $result = $DB->safeselect(
                    ['id', 'posts'],
                    'members',
                    'WHERE `id`=?',
                    $mid1,
                );
                $posts = $DB->arow($result);
                $DB->disposeresult($result);
                $posts = $posts ? $posts['posts'] : 0;

                $DB->safespecial(
                    'UPDATE %t SET `posts` = `posts` + ? WHERE `id`=?',
                    ['members'],
                    $posts,
                    $mid2,
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
                    ['stats', 'members'],
                );
                $page .= $PAGE->success('Successfully merged the two accounts.');
            }
        }

        $page .= '';
        $PAGE->addContentBox(
            'Account Merge',
            $page . PHP_EOL
            . $PAGE->parseTemplate(
                'members/merge.html',
            ),
        );
    }

    public function deletemem(): void
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

            if ($e !== '' && $e !== '0') {
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
                    [
                        'lp_date' => '0000-00-00 00:00:00',
                        'lp_tid' => null,
                        'lp_topic' => '',
                        'lp_uid' => null,
                    ],
                    'WHERE `lp_uid`=?',
                    $mid,
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
                    ['stats', 'members'],
                );
                $page .= $PAGE->success(
                    'Successfully deleted the member account. '
                    . 'Board Stat Recount suggested.',
                );
            }
        }

        $PAGE->addContentBox(
            'Delete Account',
            $page . PHP_EOL
            . $PAGE->parseTemplate(
                'members/delete.html',
            ),
        );
    }

    public function ipbans(): void
    {
        global $PAGE,$JAX;
        $page = '';
        if (isset($JAX->p['ipbans'])) {
            $data = explode(PHP_EOL, $JAX->p['ipbans']);
            foreach ($data as $k => $v) {
                $iscomment = false;
                // Check to see if each line is an ip, if it isn't,
                // add a comment.
                if ($v[0] === '#') {
                    $iscomment = true;
                } elseif (!filter_var($v, FILTER_VALIDATE_IP)) {
                    if (mb_strstr($v, '.')) {
                        // IPv4 stuff.
                        $d = explode('.', $v);
                        if (trim($v) === '') {
                            continue;
                        }

                        if (trim($v) === '0') {
                            continue;
                        }

                        if (count($d) > 4) {
                            $iscomment = true;
                        } elseif (count($d) < 4 && mb_substr($v, -1) !== '.') {
                            $iscomment = true;
                        } else {
                            foreach ($d as $v2) {
                                if ($v2 === '') {
                                    continue;
                                }

                                if ($v2 === '0') {
                                    continue;
                                }

                                if (!is_numeric($v2)) {
                                    continue;
                                }

                                if ($v2 <= 255) {
                                    continue;
                                }

                                if (!is_numeric($v2)) {
                                    continue;
                                }

                                if ($v2 <= 255) {
                                    continue;
                                }

                                $iscomment = true;
                            }
                        }
                    } elseif (mb_strstr($v, ':')) {
                        // Must be IPv6.
                        if (!filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                            // Only need to run these checks if
                            // it's not a valid IPv6 address.
                            $d = explode(':', $v);
                            if (trim($v) === '') {
                                continue;
                            }

                            if (trim($v) === '0') {
                                continue;
                            }

                            if (count($d) > 8) {
                                $iscomment = true;
                            } elseif (mb_substr($v, -1) !== ':') {
                                $iscomment = true;
                            } else {
                                foreach ($d as $v2) {
                                    if (
                                        ctype_xdigit($v2)
                                        && mb_strlen($v2) <= 4
                                    ) {
                                        continue;
                                    }

                                    $iscomment = true;
                                }
                            }
                        }
                    }
                }

                if (!$iscomment) {
                    continue;
                }

                $data[$k] = '#' . $v;
            }

            $data = implode(PHP_EOL, $data);
            $o = fopen(BOARDPATH . 'bannedips.txt', 'w');
            fwrite($o, $data);
            fclose($o);
        } elseif (file_exists(BOARDPATH . 'bannedips.txt')) {
            $data = file_get_contents(BOARDPATH . 'bannedips.txt');
        } else {
            $data = '';
        }

        $PAGE->addContentBox(
            'IP Bans',
            $PAGE->parseTemplate(
                'members/ip-bans.html',
                [
                    'content' => htmlspecialchars($data),
                ],
            ),
        );
    }

    public function massmessage(): void
    {
        global $PAGE,$JAX,$DB;
        $userData = $DB->getUser();
        $page = '';
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (
                !trim((string) $JAX->p['title'])
                || !trim((string) $JAX->p['message'])
            ) {
                $page .= $PAGE->error('All fields required!');
            } else {
                $q = $DB->safeselect(
                    ['id'],
                    'members',
                    'WHERE (?-UNIX_TIMESTAMP(`last_visit`))<?',
                    time(),
                    60 * 60 * 24 * 31 * 6,
                );
                $num = 0;
                while ($f = $DB->arow($q)) {
                    $DB->safeinsert(
                        'messages',
                        [
                            'date' => gmdate('Y-m-d H:i:s'),
                            'del_recipient' => 0,
                            'del_sender' => 0,
                            'flag' => 0,
                            'from' => $userData['id'],
                            'message' => $JAX->p['message'],
                            'read' => 0,
                            'title' => $JAX->p['title'],
                            'to' => $f['id'],
                        ],
                    );
                    ++$num;
                }

                $page .= $PAGE->success("Successfully delivered {$num} messages");
            }
        }

        $PAGE->addContentBox(
            'Mass Message',
            $page . PHP_EOL
            . $PAGE->parseTemplate(
                'members/mass-message.html',
            ),
        );
    }

    public function validation(): void
    {
        global $PAGE, $DB, $JAX;
        if (isset($_POST['submit1'])) {
            Config::write(
                [
                    'membervalidation' => isset($_POST['v_enable'])
                    && $_POST['v_enable'] ? 1 : 0,
                ],
            );
        }

        $page = $PAGE->parseTemplate(
            'members/validation.html',
            [
                'checked' => Config::getSetting('membervalidation')
                ? 'checked="checked"' : '',
            ],
        ) . PHP_EOL;
        $PAGE->addContentBox('Enable Member Validation', $page);

        if (isset($_POST['mid']) && $_POST['action'] === 'Allow') {
            $DB->safeupdate(
                'members',
                [
                    'group_id' => 1,
                ],
                'WHERE `id`=?',
                $DB->basicvalue($_POST['mid']),
            );
        }

        $result = $DB->safeselect(
            [
                'display_name',
                'email',
                'id',
                'ip',
                'UNIX_TIMESTAMP(`join_date`) AS `join_date`',
            ],
            'members',
            'WHERE `group_id`=5',
        );
        $page = '';
        while ($f = $DB->arow($result)) {
            $page .= $PAGE->parseTemplate(
                'members/validation-list-row.html',
                [
                    'email_address' => $f['email'],
                    'id' => $f['id'],
                    'ip_address' => IPAddress::asHumanReadable($f['ip']),
                    'join_date' => gmdate('M jS, Y @ g:i A', $f['join_date']),
                    'title' => $f['display_name'],
                ],
            ) . PHP_EOL;
        }

        $page = $page !== '' && $page !== '0' ? $PAGE->parseTemplate(
            'members/validation-list.html',
            [
                'content' => $page,
            ],
        ) : 'There are currently no members awaiting validation.';
        $PAGE->addContentBox('Members Awaiting Validation', $page);
    }

    public function formfield($label, $name, $value, $which = false): string
    {
        global $PAGE;

        if (mb_strtolower((string) $which) === 'textarea') {
            return $PAGE->parseTemplate(
                'members/edit-form-field-textarea.html',
                [
                    'label' => $label,
                    'title' => $name,
                    'value' => $value,
                ],
            ) . PHP_EOL;
        }

        return $PAGE->parseTemplate(
            'members/edit-form-field-text.html',
            [
                'label' => $label,
                'title' => $name,
                'value' => $value,
            ],
        ) . PHP_EOL;
    }

    public function heading($value)
    {
        global $PAGE;

        return $PAGE->parseTemplate(
            'members/edit-heading.html',
            [
                'value' => $value,
            ],
        );
    }
}
