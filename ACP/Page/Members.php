<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Carbon\Carbon;
use Jax\Config;
use Jax\Constants\Groups;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Request;
use Jax\User;

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
        private Database $database,
        private DomainDefinitions $domainDefinitions,
        private IPAddress $ipAddress,
        private Jax $jax,
        private Page $page,
        private Request $request,
        private User $user,
    ) {}

    public function render(): void
    {
        match ($this->request->both('do')) {
            'merge' => $this->merge(),
            'edit' => $this->editMember(),
            'delete' => $this->deleteMember(),
            'prereg' => $this->preRegister(),
            'massmessage' => $this->massMessage(),
            'ipbans' => $this->ipBans(),
            'validation' => $this->validation(),
            default => $this->showMain(),
        };

        $this->page->sidebar([
            'delete' => 'Delete Account',
            'edit' => 'Edit Members',
            'ipbans' => 'IP Bans',
            'massmessage' => 'Mass Message',
            'merge' => 'Account Merge',
            'prereg' => 'Pre-Register',
            'validation' => 'Validation',
        ]);
    }

    private function showMain(): void
    {
        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT
                    m.`id` AS `id`,
                    m.`avatar` AS `avatar`,
                    m.`display_name` AS `display_name`,
                    m.`group_id` AS `group_id`,
                    g.`title` AS `group_title`
                FROM %t m
                LEFT JOIN %t g
                    ON m.`group_id`=g.`id`
                ORDER BY m.`display_name` ASC
                SQL
            ,
            ['members', 'member_groups'],
        );
        $rows = '';
        while ($member = $this->database->arow($result)) {
            $rows .= $this->page->parseTemplate(
                'members/show-main-row.html',
                [
                    'avatar_url' => $member['avatar'] ?: self::DEFAULT_AVATAR,
                    'group_title' => $member['group_title'],
                    'id' => $member['id'],
                    'title' => $member['display_name'],
                ],
            );
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

    private function editMember(): void
    {
        $page = '';
        if (
            $this->request->both('mid')
            || $this->request->post('submit')
        ) {
            if (is_numeric($this->request->both('mid'))) {
                $result = $this->database->safeselect(
                    ['group_id'],
                    'members',
                    Database::WHERE_ID_EQUALS,
                    $this->database->basicvalue($this->request->both('mid')),
                );
                $member = $this->database->arow($result);
                $this->database->disposeresult($result);
                if (
                    $this->request->post('savedata')
                ) {
                    $page = $this->updateMember($member);
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
                    Database::WHERE_ID_EQUALS,
                    $this->database->basicvalue($this->request->both('mid')),
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
                    $this->database->basicvalue($this->request->post('name') . '%'),
                );
            }

            $member = [];
            while ($f = $this->database->arow($result)) {
                $member[] = $f;
            }

            $nummembers = count($member);
            if ($nummembers > 1) {
                foreach ($member as $v) {
                    $page .= $this->page->parseTemplate(
                        'members/edit-select-option.html',
                        [
                            'avatar_url' => $v['avatar'] ?: self::DEFAULT_AVATAR,
                            'id' => $v['id'],
                            'title' => $v['display_name'],
                        ],
                    );
                }

                $this->page->addContentBox('Select Member to Edit', $page);

                return;
            }

            if ($nummembers === 0) {
                $this->page->addContentBox(
                    'Error',
                    $this->page->error('This member does not exist. '),
                );

                return;
            }

            $member = array_pop($member);
            if (
                $member['group_id'] === Groups::Admin
                && $this->user->get('id') !== 1
            ) {
                $page = $this->page->error('You do not have permission to edit this profile. ');
            } else {
                $page .= $this->jax->hiddenFormFields(['mid' => $member['id']]);
                $page .= $this->formfield('Display Name:', 'display_name', $member['display_name']);
                $page .= $this->formfield('Username:', 'name', $member['name']);
                $page .= $this->formfield('Real Name:', 'full_name', $member['full_name']);
                $page .= $this->formfield('Password:', 'password', '');
                $page .= $this->getGroups($member['group_id']);
                $page .= $this->heading('Profile Fields');
                $page .= $this->formfield('User Title:', 'usertitle', $member['usertitle']);
                $page .= $this->formfield('Location:', 'location', $member['location']);
                $page .= $this->formfield('Website:', 'website', $member['website']);
                $page .= $this->formfield('Avatar:', 'avatar', $member['avatar']);
                $page .= $this->formfield('About:', 'about', $member['about'], 'textarea');
                $page .= $this->formfield('Signature:', 'sig', $member['sig'], 'textarea');
                $page .= $this->formfield('Email:', 'email', $member['email']);
                $page .= $this->formfield('UCP Notepad:', 'ucpnotepad', $member['ucpnotepad'], 'textarea');
                $page .= $this->heading('Contact Details');
                $page .= $this->formfield('AIM:', 'contact_aim', $member['contact_aim']);
                $page .= $this->formfield('Bluesky:', 'contact_bluesky', $member['contact_bluesky']);
                $page .= $this->formfield('Discord:', 'contact_discord', $member['contact_discord']);
                $page .= $this->formfield('Google Chat:', 'contact_gtalk', $member['contact_gtalk']);
                $page .= $this->formfield('MSN:', 'contact_msn', $member['contact_msn']);
                $page .= $this->formfield('Skype:', 'contact_skype', $member['contact_skype']);
                $page .= $this->formfield('Steam:', 'contact_steam', $member['contact_steam']);
                $page .= $this->formfield('Twitter:', 'contact_twitter', $member['contact_twitter']);
                $page .= $this->formfield('YIM:', 'contact_yim', $member['contact_yim']);
                $page .= $this->formfield('YouTube:', 'contact_youtube', $member['contact_youtube']);
                $page .= $this->heading('System-Generated Variables');
                $page .= $this->formfield('Post Count:', 'posts', $member['posts']);
                $page = $this->page->parseTemplate(
                    'members/edit-form.html',
                    ['content' => $page],
                );
            }
        } else {
            $page = $this->page->parseTemplate('members/edit.html');
        }

        $this->page->addContentBox(
            isset($member['name']) && $member['name']
            ? 'Editing ' . $member['name'] . "'s details" : 'Edit Member',
            $page,
        );
    }

    private function updateMember(?array $member): string
    {
        if (
            $member['group_id'] !== 2 || $this->user->get('id') === 1
        ) {
            $write = [];
            if ($this->request->post('password')) {
                $write['pass'] = password_hash(
                    (string) $this->request->post('password'),
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
                if ($this->request->post($field) === null) {
                    continue;
                }

                $write[$field] = $this->request->post($field);
            }

            // Make it so root admins can't get out of admin.
            if ($this->request->both('mid') === '1') {
                $write['group_id'] = Groups::Admin;
            }

            $this->database->safeupdate(
                'members',
                $write,
                Database::WHERE_ID_EQUALS,
                $this->database->basicvalue($this->request->both('mid')),
            );

            return $this->page->success('Profile data saved');
        }

        return $this->page->error(
            'You do not have permission to edit this profile.',
        );
    }

    private function preRegister(): void
    {
        $page = '';
        $error = null;
        if ($this->request->post('submit') !== null) {
            if (
                !$this->request->post('username')
                || !$this->request->post('displayname')
                || !$this->request->post('pass')
            ) {
                $error = 'All fields required.';
            } elseif (
                mb_strlen((string) $this->request->post('username')) > 30
                || $this->request->post('displayname') > 30
            ) {
                $error = 'Display name and username must be under 30 characters.';
            } else {
                $result = $this->database->safeselect(
                    ['name', 'display_name'],
                    'members',
                    'WHERE `name`=? OR `display_name`=?',
                    $this->database->basicvalue($this->request->post('username')),
                    $this->database->basicvalue($this->request->post('displayname')),
                );
                if ($f = $this->database->arow($result)) {
                    $error = 'That ' . ($f['name'] === $this->request->post('username')
                        ? 'username' : 'display name') . ' is already taken';
                }

                $this->database->disposeresult($result);
            }

            if ($error !== null) {
                $page .= $this->page->error($error);
            } else {
                $member = [
                    'birthdate' => '0000-00-00',
                    'display_name' => $this->request->post('displayname'),
                    'group_id' => 1,
                    'last_visit' => $this->database->datetime(),
                    'name' => $this->request->post('username'),
                    'pass' => password_hash(
                        (string) $this->request->post('pass'),
                        PASSWORD_DEFAULT,
                    ),
                    'posts' => 0,
                ];
                $result = $this->database->safeinsert('members', $member);
                $error = $this->database->error();
                $this->database->disposeresult($result);
                if ($error === '' || $error === '0') {
                    $page .= $this->page->success('Member registered.');
                } else {
                    $page .= $this->page->error(
                        'An error occurred while processing your request. '
                        . $error,
                    );
                }
            }
        }

        $page .= $this->page->parseTemplate('members/pre-register.html');
        $this->page->addContentBox('Pre-Register', $page);
    }

    private function getGroups($group_id = 0): string
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
            );
        }

        return $this->page->parseTemplate(
            'members/get-groups.html',
            [
                'content' => $page,
            ],
        );
    }

    private function merge(): void
    {
        $page = '';
        $error = null;

        if ($this->request->post('submit') !== null) {
            if (
                !$this->request->post('mid1')
                || !$this->request->post('mid2')
            ) {
                $error = 'All fields are required';
            } elseif (
                !is_numeric($this->request->post('mid1'))
                || !is_numeric($this->request->post('mid2'))
            ) {
                $error = 'An error occurred in processing your request';
            } elseif ($this->request->post('mid1') === $this->request->post('mid2')) {
                $error = "Can't merge a member with her/himself";
            }

            if ($error !== null) {
                $page .= $this->page->error($error);
            } else {
                $mid1 = $this->database->basicvalue($this->request->post('mid1'));
                $mid1int = $this->request->post('mid1');
                $mid2 = $this->request->post('mid2');

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
                    Database::WHERE_ID_EQUALS,
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
                $this->database->safedelete('members', Database::WHERE_ID_EQUALS, $mid1);

                // Update stats.
                $this->database->safespecial(
                    <<<'SQL'
                        UPDATE %t
                        SET `members` = `members` - 1,
                            `last_register` = (SELECT MAX(`id`) FROM %t)
                        SQL
                    ,
                    ['stats', 'members'],
                );
                $page .= $this->page->success('Successfully merged the two accounts.');
            }
        }

        $page .= '';
        $this->page->addContentBox(
            'Account Merge',
            $page
            . $this->page->parseTemplate(
                'members/merge.html',
            ),
        );
    }

    private function deleteMember(): void
    {
        $page = '';
        $error = null;
        if ($this->request->post('submit') !== null) {
            if (!$this->request->post('mid')) {
                $error = 'All fields are required';
            } elseif (!is_numeric($this->request->post('mid'))) {
                $error = 'An error occurred in processing your request';
            }

            if ($error !== null) {
                $page .= $this->page->error($error);
            } else {
                $mid = $this->database->basicvalue($this->request->post('mid'));

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
                $this->database->safedelete('members', Database::WHERE_ID_EQUALS, $mid);

                $this->database->fixAllForumLastPosts();

                // Update stats.
                $this->database->safespecial(
                    <<<'SQL'
                        UPDATE %t
                        SET `members` = `members` - 1,
                            `last_register` = (SELECT MAX(`id`) FROM %t)
                        SQL
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
            $page
            . $this->page->parseTemplate(
                'members/delete.html',
            ),
        );
    }

    private function ipBans(): void
    {
        if ($this->request->post('ipbans') !== null) {
            $data = explode(PHP_EOL, (string) $this->request->post('ipbans'));
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
            $o = fopen($this->domainDefinitions->getBoardPath() . '/bannedips.txt', 'w');
            fwrite($o, $data);
            fclose($o);
        } elseif (file_exists($this->domainDefinitions->getBoardPath() . '/bannedips.txt')) {
            $data = file_get_contents($this->domainDefinitions->getBoardPath() . '/bannedips.txt');
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

    private function massMessage(): void
    {
        $page = '';
        if ($this->request->post('submit') !== null) {
            if (
                !trim((string) $this->request->post('title'))
                || !trim((string) $this->request->post('message'))
            ) {
                $page .= $this->page->error('All fields required!');
            } else {
                $q = $this->database->safeselect(
                    ['id'],
                    'members',
                    'WHERE (?-UNIX_TIMESTAMP(`last_visit`))<?',
                    Carbon::now()->getTimestamp(),
                    60 * 60 * 24 * 31 * 6,
                );
                $num = 0;
                while ($f = $this->database->arow($q)) {
                    $this->database->safeinsert(
                        'messages',
                        [
                            'date' => $this->database->datetime(),
                            'del_recipient' => 0,
                            'del_sender' => 0,
                            'flag' => 0,
                            'from' => $this->user->get('id'),
                            'message' => $this->request->post('message'),
                            'read' => 0,
                            'title' => $this->request->post('title'),
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
            $page
            . $this->page->parseTemplate(
                'members/mass-message.html',
            ),
        );
    }

    private function validation(): void
    {
        if ($this->request->post('submit1') !== null) {
            $this->config->write(
                [
                    'membervalidation' => $this->request->post('v_enable') !== null
                    && $this->request->post('v_enable') ? 1 : 0,
                ],
            );
        }

        $page = $this->page->parseTemplate(
            'members/validation.html',
            [
                'checked' => $this->config->getSetting('membervalidation')
                ? 'checked="checked"' : '',
            ],
        );
        $this->page->addContentBox('Enable Member Validation', $page);

        if (
            $this->request->post('mid') !== null
            && $this->request->post('action') === 'Allow'
        ) {
            $this->database->safeupdate(
                'members',
                [
                    'group_id' => 1,
                ],
                Database::WHERE_ID_EQUALS,
                $this->database->basicvalue($this->request->post('mid')),
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
                    'ip_address' => $this->ipAddress->asHumanReadable($f['ip']),
                    'join_date' => gmdate('M jS, Y @ g:i A', $f['join_date']),
                    'title' => $f['display_name'],
                ],
            );
        }

        $page = $page !== '' && $page !== '0' ? $this->page->parseTemplate(
            'members/validation-list.html',
            [
                'content' => $page,
            ],
        ) : 'There are currently no members awaiting validation.';
        $this->page->addContentBox('Members Awaiting Validation', $page);
    }

    private function formfield(
        string $label,
        string $name,
        $value,
        $which = false,
    ): string {
        if (mb_strtolower((string) $which) === 'textarea') {
            return $this->page->parseTemplate(
                'members/edit-form-field-textarea.html',
                [
                    'label' => $label,
                    'title' => $name,
                    'value' => $value,
                ],
            );
        }

        return $this->page->parseTemplate(
            'members/edit-form-field-text.html',
            [
                'label' => $label,
                'title' => $name,
                'value' => $value,
            ],
        );
    }

    private function heading(string $value): string
    {
        return $this->page->parseTemplate(
            'members/edit-heading.html',
            [
                'value' => $value,
            ],
        );
    }
}
