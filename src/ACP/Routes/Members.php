<?php

declare(strict_types=1);

namespace ACP\Routes;

use ACP\Page;
use Carbon\Carbon;
use Jax\Config;
use Jax\Constants\Groups;
use Jax\Database\Database;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\Interfaces\Route;
use Jax\IPAddress;
use Jax\Models\Forum;
use Jax\Models\Group;
use Jax\Models\Member;
use Jax\Models\Message;
use Jax\Request;
use Jax\User;
use Override;

use function array_map;
use function count;
use function htmlspecialchars;
use function mb_strlen;
use function password_hash;

use const PASSWORD_DEFAULT;

final readonly class Members implements Route
{
    private const string DEFAULT_AVATAR = '/Service/Themes/Default/avatars/default.gif';

    public function __construct(
        private Config $config,
        private Database $database,
        private DomainDefinitions $domainDefinitions,
        private FileSystem $fileSystem,
        private IPAddress $ipAddress,
        private Page $page,
        private Request $request,
        private User $user,
    ) {}

    #[Override]
    public function route(array $params): void
    {
        match ($params['do'] ?? '') {
            'merge' => $this->merge(),
            'edit' => $this->editMember(),
            'delete' => $this->deleteMember(),
            'prereg' => $this->preRegister(),
            'massmessage' => $this->massMessage(),
            'ipbans' => $this->ipBans(),
            'validation' => $this->validation(),
            default => $this->showMain(),
        };
    }

    private function showMain(): void
    {
        $members = Member::selectMany('ORDER BY `displayName` ASC');
        $groups = Group::joinedOn($members, static fn(Member $member): int => $member->groupID);

        $rows = '';
        foreach ($members as $member) {
            $rows .= $this->page->render('members/show-main-row.html', [
                'avatar_url' => $member->avatar ?: self::DEFAULT_AVATAR,
                'group_title' => $groups[$member->groupID]->title,
                'id' => $member->id,
                'title' => $member->displayName,
            ]);
        }

        $this->page->addContentBox('Member List', $this->page->render('members/show-main.html', [
            'rows' => $rows,
        ]));
    }

    private function editMember(): void
    {
        $page = '';
        $memberId = (int) $this->request->asString->both('mid');
        $name = $this->request->asString->post('name');
        $member = null;
        if ($memberId || $this->request->post('submit')) {
            if ($memberId !== 0) {
                $member = Member::selectOne($memberId);
                if ($this->request->post('savedata') && $member) {
                    $page = $this->updateMember($member);
                }

                $members = Member::selectMany(Database::WHERE_ID_EQUALS, $memberId);
            } else {
                $members = Member::selectMany('WHERE `displayName` LIKE ?', $name . '%');
            }

            $numMembers = count($members);
            if ($numMembers > 1) {
                foreach ($members as $member) {
                    $page .= $this->page->render('members/edit-select-option.html', [
                        'avatar_url' => $member->avatar ?: self::DEFAULT_AVATAR,
                        'id' => $member->id,
                        'title' => $member->displayName,
                    ]);
                }

                $this->page->addContentBox('Select Member to Edit', $page);

                return;
            }

            if ($numMembers === 0) {
                $this->page->addContentBox('Error', $this->page->error('This member does not exist. '));

                return;
            }

            $member = $members[0];
            $page =
                $member->groupID === Groups::Admin->value
                && $this->user->get()->id !== 1
                && $this->user->get()->id !== $member->id
                    ? $this->page->error('You do not have permission to edit this profile. ')
                    : $this->page->render('members/edit-form.html', [
                        'content' => $page,
                        'groups' => Group::selectMany('ORDER BY `title` DESC'),
                        'member' => $member,
                    ]);
        } else {
            $page = $this->page->render('members/edit.html');
        }

        $this->page->addContentBox($member?->name ? 'Editing ' . $member->name . "'s details" : 'Edit Member', $page);
    }

    private function updateMember(Member $member): string
    {
        $memberId = (int) $this->request->asString->both('mid');
        $password = $this->request->asString->post('password');

        if ($member->groupID === 2 && $this->user->get()->id !== 1) {
            return $this->page->error('You do not have permission to edit this profile.');
        }

        if ($password) {
            $member->pass = password_hash($password, PASSWORD_DEFAULT);
        }

        $stringFields = [
            'displayName',
            'name',
            'fullName',
            'usertitle',
            'location',
            'avatar',
            'about',
            'sig',
            'email',
            'ucpnotepad',
            'contactAIM',
            'contactBlueSky',
            'contactDiscord',
            'contactGoogleChat',
            'contactMSN',
            'contactSkype',
            'contactSteam',
            'contactTwitter',
            'contactYIM',
            'contactYoutube',
            'website',
        ];
        foreach ($stringFields as $stringField) {
            $value = $this->request->asString->post($stringField);
            if ($value === null) {
                continue;
            }

            $member->{$stringField} = $value;
        }

        // Int fields
        $member->posts = (int) $this->request->asString->post('posts');
        $member->groupID = (int) $this->request->asString->post('groupID');

        // Make it so root admins can't get out of admin.
        if ($memberId === 1) {
            $member->groupID = Groups::Admin->value;
        }

        $member->update();

        return $this->page->success('Profile data saved');
    }

    private function preRegisterSubmit(): ?string
    {
        $username = $this->request->asString->post('username');
        $displayName = $this->request->asString->post('displayname');
        $password = $this->request->asString->post('pass');

        if (!$username || !$displayName || !$password) {
            return 'All fields required.';
        }

        if (mb_strlen($username) > 30 || mb_strlen($displayName) > 30) {
            return 'Display name and username must be under 30 characters.';
        }

        $member = Member::selectOne('WHERE `name`=? OR `displayName`=?', $username, $displayName);
        if ($member !== null) {
            return 'That ' . ($member->name === $username ? 'username' : 'display name') . ' is already taken';
        }

        $member = new Member();
        $member->displayName = $displayName;
        $member->groupID = 1;
        $member->lastVisit = $this->database->datetime();
        $member->name = $username;
        $member->pass = password_hash($password, PASSWORD_DEFAULT);

        $result = $member->insert();

        if ($this->database->affectedRows($result) === 0) {
            return 'An error occurred while processing your request. ';
        }

        return null;
    }

    private function preRegister(): void
    {
        $page = '';

        if ($this->request->post('submit') !== null) {
            $error = $this->preRegisterSubmit();
            $page .= $error ? $this->page->error($error) : $this->page->success('Member registered.');
        }

        $page .= $this->page->render('members/pre-register.html');
        $this->page->addContentBox('Pre-Register', $page);
    }

    private function mergeMembers(int $mid1, int $mid2): ?string
    {
        $member1 = Member::selectOne($mid1);
        $member2 = Member::selectOne($mid2);

        if ($member1 === null || $member2 === null) {
            return 'Invalid input, or the accounts may already be merged';
        }

        // Files.
        $this->database->update(
            'files',
            [
                'uid' => $mid2,
            ],
            'WHERE `uid`=?',
            $mid1,
        );
        // Messages.
        $this->database->update(
            'messages',
            [
                'to' => $mid2,
            ],
            'WHERE `to`=?',
            $mid1,
        );
        $this->database->update(
            'messages',
            [
                'from' => $mid2,
            ],
            'WHERE `from`=?',
            $mid1,
        );
        // Posts.
        $this->database->update(
            'posts',
            [
                'author' => $mid2,
            ],
            'WHERE `author`=?',
            $mid1,
        );
        // Profile comments.
        $this->database->update(
            'profile_comments',
            [
                'to' => $mid2,
            ],
            'WHERE `to`=?',
            $mid1,
        );
        $this->database->update(
            'profile_comments',
            [
                'from' => $mid2,
            ],
            'WHERE `from`=?',
            $mid1,
        );
        // Topics.
        $this->database->update(
            'topics',
            [
                'author' => $mid2,
            ],
            'WHERE `author`=?',
            $mid1,
        );
        $this->database->update(
            'topics',
            [
                'lastPostUser' => $mid2,
            ],
            'WHERE `lastPostUser`=?',
            $mid1,
        );

        // Forums.
        $this->database->update(
            'forums',
            [
                'lastPostUser' => $mid2,
            ],
            'WHERE `lastPostUser`=?',
            $mid1,
        );

        // Shouts.
        $this->database->update(
            'shouts',
            [
                'uid' => $mid2,
            ],
            'WHERE `uid`=?',
            $mid1,
        );

        // Session.
        $this->database->update(
            'session',
            [
                'uid' => $mid2,
            ],
            'WHERE `uid`=?',
            $mid1,
        );

        // Sum post count on account being merged into.
        $member2->posts += $member1->posts;
        $member2->update();

        // Delete the account.
        $member1->delete();

        // Update stats.
        $this->database->special(<<<'SQL'
            UPDATE %t
            SET `members` = `members` - 1,
                `last_register` = (SELECT MAX(`id`) FROM %t)
            SQL, ['stats', 'members']);

        return null;
    }

    private function merge(): void
    {
        $mergeResult = '';

        if ($this->request->post('submit') !== null) {
            $mid1 = (int) $this->request->asString->post('mid1');
            $mid2 = (int) $this->request->asString->post('mid2');
            $error = match (true) {
                !$mid1 || !$mid2 => 'All fields are required',
                $mid1 === $mid2 => "Can't merge a member with her/himself",
                default => $this->mergeMembers($mid1, $mid2),
            };
            $mergeResult = $error ? $this->page->error($error) : $this->page->success('Accounts merged successfully');
        }

        $this->page->addContentBox('Account Merge', $mergeResult . $this->page->render('members/merge.html'));
    }

    private function deleteMember(): void
    {
        $page = '';
        $error = null;
        if ($this->request->post('submit') !== null) {
            $memberId = (int) $this->request->asString->post('mid');
            if ($memberId === 0) {
                $error = 'All fields are required';
            }

            if ($error !== null) {
                $page .= $this->page->error($error);
            } else {
                // PMs.
                $this->database->delete('messages', 'WHERE `to`=?', $memberId);
                $this->database->delete('messages', 'WHERE `from`=?', $memberId);
                // Posts.
                $this->database->delete('posts', 'WHERE `author`=?', $memberId);
                // Profile comments.
                $this->database->delete('profile_comments', 'WHERE `to`=?', $memberId);
                $this->database->delete('profile_comments', 'WHERE `from`=?', $memberId);
                // Topics.
                $this->database->delete('topics', 'WHERE `author`=?', $memberId);

                // Forums.
                $this->database->update(
                    'forums',
                    [
                        'lastPostTopic' => null,
                        'lastPostTopicTitle' => '',
                        'lastPostUser' => null,
                    ],
                    'WHERE `lastPostUser`=?',
                    $memberId,
                );

                // Shouts.
                $this->database->delete('shouts', 'WHERE `uid`=?', $memberId);

                // Session.
                $this->database->delete('session', 'WHERE `uid`=?', $memberId);

                // Delete the account.
                $this->database->delete('members', Database::WHERE_ID_EQUALS, $memberId);

                array_map(static fn(Forum $forum) => Forum::fixLastPost($forum->id), Forum::selectMany());

                // Update stats.
                $this->database->special(<<<'SQL'
                    UPDATE %t
                    SET `members` = `members` - 1,
                        `last_register` = (SELECT MAX(`id`) FROM %t)
                    SQL, ['stats', 'members']);
                $page .= $this->page->success('Successfully deleted the member account. '
                . 'Board Stat Recount suggested.');
            }
        }

        $this->page->addContentBox('Delete Account', $page . $this->page->render('members/delete.html'));
    }

    private function ipBans(): void
    {
        $ipBans = $this->request->asString->post('ipbans');
        $bannedIpsPath = $this->domainDefinitions->getBoardPath() . '/bannedips.txt';
        if ($ipBans !== null) {
            $this->fileSystem->putContents($bannedIpsPath, $ipBans);
        }

        $data = $this->fileSystem->getContents($bannedIpsPath) ?: '';

        $this->page->addContentBox('IP Bans', $this->page->render('members/ip-bans.html', [
            'content' => htmlspecialchars($data),
        ]));
    }

    private function sendMassMessage(): string
    {
        $title = $this->request->asString->post('title');
        $messageBody = $this->request->asString->post('message');
        if (!$title || !$messageBody) {
            return $this->page->error('All fields required!');
        }

        $members = Member::selectMany(
            'WHERE (?-UNIX_TIMESTAMP(`lastVisit`))<?',
            Carbon::now('UTC')->getTimestamp(),
            60 * 60 * 24 * 31 * 6,
        );
        foreach ($members as $member) {
            $message = new Message();
            $message->date = $this->database->datetime();
            $message->deletedRecipient = 0;
            $message->deletedSender = 0;
            $message->flag = 0;
            $message->from = $this->user->get()->id;
            $message->message = $messageBody;
            $message->read = 0;
            $message->title = $title;
            $message->to = $member->id;
            $message->insert();
        }

        $messageCount = count($members);

        return $this->page->success("Successfully delivered {$messageCount} messages");
    }

    private function massMessage(): void
    {
        $page = '';

        if ($this->request->post('submit') !== null) {
            $page .= $this->sendMassMessage();
        }

        $this->page->addContentBox('Mass Message', $page . $this->page->render('members/mass-message.html'));
    }

    private function validation(): void
    {
        if ($this->request->post('submit1') !== null) {
            $this->config->write([
                'membervalidation' => $this->request->asString->post('v_enable') ? 1 : 0,
            ]);
        }

        $page = $this->page->render('members/validation.html', [
            'checked' => $this->page->checked((bool) $this->config->getSetting('membervalidation')),
        ]);
        $this->page->addContentBox('Enable Member Validation', $page);

        $memberId = (int) $this->request->post('mid');
        $action = $this->request->asString->post('action');
        if ($memberId && $action === 'Allow') {
            $this->database->update(
                'members',
                [
                    'groupID' => 1,
                ],
                Database::WHERE_ID_EQUALS,
                $memberId,
            );
        }

        $members = Member::selectMany('WHERE `groupID`=5');
        $page = '';
        foreach ($members as $member) {
            $page .= $this->page->render('members/validation-list-row.html', [
                'email_address' => $member->email,
                'id' => $member->id,
                'ip_address' => $this->ipAddress->asHumanReadable($member->ip),
                'joinDate' => $member->joinDate ?? '',
                'title' => $member->displayName,
            ]);
        }

        $page = $page !== ''
            ? $this->page->render('members/validation-list.html', [
                'content' => $page,
            ]) : 'There are currently no members awaiting validation.';
        $this->page->addContentBox('Members Awaiting Validation', $page);
    }
}
