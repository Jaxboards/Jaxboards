<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\Jax;

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

final readonly class Members
{
    private const DEFAULT_AVATAR = '/Service/Themes/Default/avatars/default.gif';

    public function __construct(
        private Config $config,
        private Page $page,
        private Database $database,
        private Jax $jax,
    ) {}

    public function route(): void
    {
        if (!isset($this->jax->b['do'])) {
            $this->jax->b['do'] = null;
        }

        match ($this->jax->b['do']) {
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
            $sidebarLinks .= $this->page->parseTemplate(
                'sidebar-list-link.html',
                [
                    'title' => $title,
                    'url' => '?act=members&do=' . $do,
                ],
            ) . PHP_EOL;
        }

        /*
            $sidebarLinks .= $this->page->parseTemplate(
            'sidebar-list-link.html',
            array(
                'url' => '?act=stats',
                'title' => 'Recount Statistics',
            )
            ) . PHP_EOL;
         */

        $this->page->sidebar(
            $this->page->parseTemplate(
                'sidebar-list.html',
                [
                    'content' => $sidebarLinks,
                ],
            ),
        );
    }

    public function showmain(): void
    {
        $result = $this->database->safespecial(
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
        while ($f = $this->database->arow($result)) {
            $rows .= $this->page->parseTemplate(
                'members/show-main-row.html',
                [
                    'avatar_url' => $this->jax->pick(
                        $f['avatar'],
                        self::DEFAULT_AVATAR,
                    ),
                    'group_title' => $f['group_title'],
                    'id' => $f['id'],
                    'title' => $f['display_name'],
                ],
            ) . PHP_EOL;
        }

        $this->page->addContentBox(
            'Member List',
            $this->page->parseTemplate(
                'members/show-main.html',
                [
                    'rows' => $rows,
                ],
            ),
        );
    }

    public function editmem()
    {
        $userData = $this->database->getUser();
        $page = '';
        if (
            (isset($this->jax->b['mid']) && $this->jax->b['mid'])
            || (isset($this->jax->p['submit']) && $this->jax->p['submit'])
        ) {
            if (
                isset($this->jax->b['mid'])
                && $this->jax->b['mid']
                && is_numeric($this->jax->b['mid'])
            ) {
                $result = $this->database->safeselect(
                    ['group_id'],
                    'members',
                    'WHERE `id`=?',
                    $this->database->basicvalue($this->jax->b['mid']),
                );
                $data = $this->database->arow($result);
                $this->database->disposeresult($result);
                if (
                    isset($this->jax->p['savedata'])
                    && $this->jax->p['savedata']
                ) {
                    if (
                        $data['group_id'] !== 2 || $userData['id'] === 1
                    ) {
                        $write = [];
                        if ($this->jax->p['password']) {
                            $write['pass'] = password_hash(
                                (string) $this->jax->p['password'],
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
                            if (!isset($this->jax->p[$field])) {
                                continue;
                            }

                            $write[$field] = $this->jax->p[$field];
                        }

                        // Make it so root admins can't get out of admin.
                        if ($this->jax->b['mid'] === 1) {
                            $write['group_id'] = 2;
                        }

                        $this->database->safeupdate(
                            'members',
                            $write,
                            'WHERE `id`=?',
                            $this->database->basicvalue($this->jax->b['mid']),
                        );
                        $page = $this->page->success('Profile data saved');
                    } else {
                        $page = $this->page->error(
                            'You do not have permission to edit this profile.'
                            . $this->page->back(),
                        );
                    }
                }

                $result = $this->database->safeselect(
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
                    $this->database->basicvalue($this->jax->b['mid']),
                );
            } else {
                $result = $this->database->safeselect(
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
                    $this->database->basicvalue($this->jax->p['name'] . '%'),
                );
            }

            $data = [];
            while ($f = $this->database->arow($result)) {
                $data[] = $f;
            }

            $nummembers = count($data);
            if ($nummembers > 1) {
                foreach ($data as $v) {
                    $page .= $this->page->parseTemplate(
                        'members/edit-select-option.html',
                        [
                            'avatar_url' => $this->jax->pick(
                                $v['avatar'],
                                self::DEFAULT_AVATAR,
                            ),
                            'id' => $v['id'],
                            'title' => $v['display_name'],
                        ],
                    ) . PHP_EOL;
                }

                return $this->page->addContentBox('Select Member to Edit', $page);
            }

            if ($nummembers === 0) {
                return $this->page->addContentBox(
                    'Error',
                    $this->page->error('This member does not exist. ' . $this->page->back()),
                );
            }

            $data = array_pop($data);
            if ($data['group_id'] === 2 && $userData['id'] !== 1) {
                $page = $this->page->error(
                    'You do not have permission to edit this profile. '
                    . $this->page->back(),
                );
            } else {
                $page .= $this->jax->hiddenFormFields(['mid' => $data['id']]);
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
                $page = $this->page->parseTemplate(
                    'members/edit-form.html',
                    ['content' => $page],
                );
            }
        } else {
            $page = $this->page->parseTemplate(
                'members/edit.html',
            );
        }

        $this->page->addContentBox(
            isset($data['name']) && $data['name']
            ? 'Editing ' . $data['name'] . "'s details" : 'Edit Member',
            $page,
        );

        return null;
    }

    public function preregister(): void
    {
        $page = '';
        $e = '';
        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            if (
                !$this->jax->p['username']
                || !$this->jax->p['displayname']
                || !$this->jax->p['pass']
            ) {
                $e = 'All fields required.';
            } elseif (
                mb_strlen((string) $this->jax->p['username']) > 30
                || $this->jax->p['displayname'] > 30
            ) {
                $e = 'Display name and username must be under 30 characters.';
            } else {
                $result = $this->database->safeselect(
                    ['name', 'display_name'],
                    'members',
                    'WHERE `name`=? OR `display_name`=?',
                    $this->database->basicvalue($this->jax->p['username']),
                    $this->database->basicvalue($this->jax->p['displayname']),
                );
                if ($f = $this->database->arow($result)) {
                    $e = 'That ' . ($f['name'] === $this->jax->p['username']
                        ? 'username' : 'display name') . ' is already taken';
                }

                $this->database->disposeresult($result);
            }

            if ($e !== '' && $e !== '0') {
                $page .= $this->page->error($e);
            } else {
                $member = [
                    'birthdate' => '0000-00-00',
                    'display_name' => $this->jax->p['displayname'],
                    'group_id' => 1,
                    'last_visit' => gmdate('Y-m-d H:i:s'),
                    'name' => $this->jax->p['username'],
                    'pass' => password_hash(
                        (string) $this->jax->p['pass'],
                        PASSWORD_DEFAULT,
                    ),
                    'posts' => 0,
                ];
                $result = $this->database->safeinsert('members', $member);
                $error = $this->database->error();
                $this->database->disposeresult($result);
                if (!$error) {
                    $page .= $this->page->success('Member registered.');
                } else {
                    $page .= $this->page->error(
                        'An error occurred while processing your request. '
                        . $error,
                    );
                }
            }
        }

        $page .= $this->page->parseTemplate(
            'members/pre-register.html',
        );
        $this->page->addContentBox('Pre-Register', $page);
    }

    public function getGroups($group_id = 0): ?string
    {
        $page = '';
        $result = $this->database->safeselect(
            ['id', 'title'],
            'member_groups',
            'ORDER BY `title` DESC',
        );
        while ($f = $this->database->arow($result)) {
            $page .= $this->page->parseTemplate(
                'select-option.html',
                [
                    'label' => $f['title'],
                    'selected' => $group_id === $f['id'] ? ' selected="selected"' : '',
                    'value' => $f['id'],
                ],
            ) . PHP_EOL;
        }

        return $this->page->parseTemplate(
            'members/get-groups.html',
            [
                'content' => $page,
            ],
        );
    }

    public function merge(): void
    {
        $page = '';
        $e = '';
        if (!isset($this->jax->p['submit'])) {
            $this->jax->p['submit'] = false;
        }

        if ($this->jax->p['submit']) {
            if (!$this->jax->p['mid1'] || !$this->jax->p['mid2']) {
                $e = 'All fields are required';
            } elseif (
                !is_numeric($this->jax->p['mid1'])
                || !is_numeric($this->jax->p['mid2'])
            ) {
                $e = 'An error occurred in processing your request';
            } elseif ($this->jax->p['mid1'] === $this->jax->p['mid2']) {
                $e = "Can't merge a member with her/himself";
            }

            if ($e !== '' && $e !== '0') {
                $page .= $this->page->error($e);
            } else {
                $mid1 = $this->database->basicvalue($this->jax->p['mid1']);
                $mid1int = $this->jax->p['mid1'];
                $mid2 = $this->jax->p['mid2'];

                // Files.
                $this->database->safeupdate(
                    'files',
                    [
                        'uid' => $mid2,
                    ],
                    'WHERE `uid`=?',
                    $mid1,
                );
                // PMs.
                $this->database->safeupdate(
                    'messages',
                    [
                        'to' => $mid2,
                    ],
                    'WHERE `to`=?',
                    $mid1,
                );
                $this->database->safeupdate(
                    'messages',
                    [
                        'from' => $mid2,
                    ],
                    'WHERE `from`=?',
                    $mid1,
                );
                // Posts.
                $this->database->safeupdate(
                    'posts',
                    [
                        'auth_id' => $mid2,
                    ],
                    'WHERE `auth_id`=?',
                    $mid1,
                );
                // Profile comments.
                $this->database->safeupdate(
                    'profile_comments',
                    [
                        'to' => $mid2,
                    ],
                    'WHERE `to`=?',
                    $mid1,
                );
                $this->database->safeupdate(
                    'profile_comments',
                    [
                        'from' => $mid2,
                    ],
                    'WHERE `from`=?',
                    $mid1,
                );
                // Topics.
                $this->database->safeupdate(
                    'topics',
                    [
                        'auth_id' => $mid2,
                    ],
                    'WHERE `auth_id`=?',
                    $mid1,
                );
                $this->database->safeupdate(
                    'topics',
                    [
                        'lp_uid' => $mid2,
                    ],
                    'WHERE `lp_uid`=?',
                    $mid1,
                );

                // Forums.
                $this->database->safeupdate(
                    'forums',
                    [
                        'lp_uid' => $mid2,
                    ],
                    'WHERE `lp_uid`=?',
                    $mid1,
                );

                // Shouts.
                $this->database->safeupdate(
                    'shouts',
                    [
                        'uid' => $mid2,
                    ],
                    'WHERE `uid`=?',
                    $mid1,
                );

                // Session.
                $this->database->safeupdate(
                    'session',
                    [
                        'uid' => $mid2,
                    ],
                    'WHERE `uid`=?',
                    $mid1,
                );

                // Sum post count on account being merged into.
                $result = $this->database->safeselect(
                    ['id', 'posts'],
                    'members',
                    'WHERE `id`=?',
                    $mid1,
                );
                $posts = $this->database->arow($result);
                $this->database->disposeresult($result);
                $posts = $posts ? $posts['posts'] : 0;

                $this->database->safespecial(
                    'UPDATE %t SET `posts` = `posts` + ? WHERE `id`=?',
                    ['members'],
                    $posts,
                    $mid2,
                );

                // Delete the account.
                $this->database->safedelete('members', 'WHERE `id`=?', $mid1);

                // Update stats.
                $this->database->safespecial(
                    <<<'EOT'
                        UPDATE %t
                        SET `members` = `members` - 1,
                            `last_register` = (SELECT MAX(`id`) FROM %t)
                        EOT
                    ,
                    ['stats', 'members'],
                );
                $page .= $this->page->success('Successfully merged the two accounts.');
            }
        }

        $page .= '';
        $this->page->addContentBox(
            'Account Merge',
            $page . PHP_EOL
            . $this->page->parseTemplate(
                'members/merge.html',
            ),
        );
    }

    public function deletemem(): void
    {
        $page = '';
        $e = '';
        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            if (!$this->jax->p['mid']) {
                $e = 'All fields are required';
            } elseif (!is_numeric($this->jax->p['mid'])) {
                $e = 'An error occurred in processing your request';
            }

            if ($e !== '' && $e !== '0') {
                $page .= $this->page->error($e);
            } else {
                $mid = $this->database->basicvalue($this->jax->p['mid']);

                // PMs.
                $this->database->safedelete('messages', 'WHERE `to`=?', $mid);
                $this->database->safedelete('messages', 'WHERE `from`=?', $mid);
                // Posts.
                $this->database->safedelete('posts', 'WHERE `auth_id`=?', $mid);
                // Profile comments.
                $this->database->safedelete('profile_comments', 'WHERE `to`=?', $mid);
                $this->database->safedelete('profile_comments', 'WHERE `from`=?', $mid);
                // Topics.
                $this->database->safedelete('topics', 'WHERE `auth_id`=?', $mid);

                // Forums.
                $this->database->safeupdate(
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
                $this->database->safedelete('shouts', 'WHERE `uid`=?', $mid);

                // Session.
                $this->database->safedelete('session', 'WHERE `uid`=?', $mid);

                // Delete the account.
                $this->database->safedelete('members', 'WHERE `id`=?', $mid);

                $this->database->fixAllForumLastPosts();

                // Update stats.
                $this->database->safespecial(
                    <<<'EOT'
                        UPDATE %t
                        SET `members` = `members` - 1,
                            `last_register` = (SELECT MAX(`id`) FROM %t)
                        EOT
                    ,
                    ['stats', 'members'],
                );
                $page .= $this->page->success(
                    'Successfully deleted the member account. '
                    . 'Board Stat Recount suggested.',
                );
            }
        }

        $this->page->addContentBox(
            'Delete Account',
            $page . PHP_EOL
            . $this->page->parseTemplate(
                'members/delete.html',
            ),
        );
    }

    public function ipbans(): void
    {
        if (isset($this->jax->p['ipbans'])) {
            $data = explode(PHP_EOL, (string) $this->jax->p['ipbans']);
            foreach ($data as $k => $v) {
                $iscomment = false;
                // Check to see if each line is an ip, if it isn't,
                // add a comment.
                if ($v && $v[0] === '#') {
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

        $this->page->addContentBox(
            'IP Bans',
            $this->page->parseTemplate(
                'members/ip-bans.html',
                [
                    'content' => htmlspecialchars($data),
                ],
            ),
        );
    }

    public function massmessage(): void
    {
        $userData = $this->database->getUser();
        $page = '';
        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            if (
                !trim((string) $this->jax->p['title'])
                || !trim((string) $this->jax->p['message'])
            ) {
                $page .= $this->page->error('All fields required!');
            } else {
                $q = $this->database->safeselect(
                    ['id'],
                    'members',
                    'WHERE (?-UNIX_TIMESTAMP(`last_visit`))<?',
                    time(),
                    60 * 60 * 24 * 31 * 6,
                );
                $num = 0;
                while ($f = $this->database->arow($q)) {
                    $this->database->safeinsert(
                        'messages',
                        [
                            'date' => gmdate('Y-m-d H:i:s'),
                            'del_recipient' => 0,
                            'del_sender' => 0,
                            'flag' => 0,
                            'from' => $userData['id'],
                            'message' => $this->jax->p['message'],
                            'read' => 0,
                            'title' => $this->jax->p['title'],
                            'to' => $f['id'],
                        ],
                    );
                    ++$num;
                }

                $page .= $this->page->success("Successfully delivered {$num} messages");
            }
        }

        $this->page->addContentBox(
            'Mass Message',
            $page . PHP_EOL
            . $this->page->parseTemplate(
                'members/mass-message.html',
            ),
        );
    }

    public function validation(): void
    {
        if (isset($_POST['submit1'])) {
            $this->config->write(
                [
                    'membervalidation' => isset($_POST['v_enable'])
                    && $_POST['v_enable'] ? 1 : 0,
                ],
            );
        }

        $page = $this->page->parseTemplate(
            'members/validation.html',
            [
                'checked' => $this->config->getSetting('membervalidation')
                ? 'checked="checked"' : '',
            ],
        ) . PHP_EOL;
        $this->page->addContentBox('Enable Member Validation', $page);

        if (isset($_POST['mid']) && $_POST['action'] === 'Allow') {
            $this->database->safeupdate(
                'members',
                [
                    'group_id' => 1,
                ],
                'WHERE `id`=?',
                $this->database->basicvalue($_POST['mid']),
            );
        }

        $result = $this->database->safeselect(
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
        while ($f = $this->database->arow($result)) {
            $page .= $this->page->parseTemplate(
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

        $page = $page !== '' && $page !== '0' ? $this->page->parseTemplate(
            'members/validation-list.html',
            [
                'content' => $page,
            ],
        ) : 'There are currently no members awaiting validation.';
        $this->page->addContentBox('Members Awaiting Validation', $page);
    }

    public function formfield($label, $name, $value, $which = false): string
    {
        if (mb_strtolower((string) $which) === 'textarea') {
            return $this->page->parseTemplate(
                'members/edit-form-field-textarea.html',
                [
                    'label' => $label,
                    'title' => $name,
                    'value' => $value,
                ],
            ) . PHP_EOL;
        }

        return $this->page->parseTemplate(
            'members/edit-form-field-text.html',
            [
                'label' => $label,
                'title' => $name,
                'value' => $value,
            ],
        ) . PHP_EOL;
    }

    public function heading($value): ?string
    {
        return $this->page->parseTemplate(
            'members/edit-heading.html',
            [
                'value' => $value,
            ],
        );
    }
}
