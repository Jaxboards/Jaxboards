<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Nav;
use ACP\Page;
use Jax\Constants\Groups as ConstantsGroups;
use Jax\Database\Database;
use Jax\Lodash;
use Jax\Models\Group;
use Jax\Request;
use Jax\TextFormatting;

use function array_flip;
use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function explode;
use function filter_var;
use function implode;
use function is_array;
use function is_string;
use function mb_strlen;

use const FILTER_VALIDATE_URL;

final class Groups
{
    private bool $updatePermissions = true;

    public function __construct(
        private readonly Database $database,
        private readonly Nav $nav,
        private readonly Page $page,
        private readonly Request $request,
        private readonly TextFormatting $textFormatting,
    ) {}

    public function render(): void
    {
        $this->page->sidebar($this->nav->getMenu('Groups'));

        $edit = $this->request->get('edit');
        if (is_string($edit)) {
            $this->create((int) $edit);

            return;
        }

        match ($this->request->get('do')) {
            'perms' => $this->showPerms(),
            'create' => $this->create(),
            'delete' => $this->delete(),
            default => $this->showPerms(),
        };
    }

    /**
     * @param array<array<int|string>> $permsInput
     */
    private function updatePerms(array $permsInput): void
    {
        $columns = [
            'canAccessACP',
            'canPost',
            'canEditPosts',
            'canAddComments',
            'canDeleteComments',
            'canDeleteOwnPosts',
            'canCreateTopics',
            'canEditTopics',
            'canViewBoard',
            'canViewOfflineBoard',
            'floodControl',
            'canOverrideLockedTopics',
            'canViewShoutbox',
            'canShout',
            'canModerate',
            'canDeleteShouts',
            'canDeleteOwnShouts',
            'canKarma',
            'canIM',
            'canPM',
            'canLockOwnTopics',
            'canDeleteOwnTopics',
            'canUseSignatures',
            'canAttach',
            'canPoll',
            'canViewStats',
            'legend',
            'canViewFullProfile',
        ];

        // Set anything not sent to 0.
        foreach ($permsInput as $groupId => $groupPerms) {
            foreach ($columns as $column) {
                if (!array_key_exists($column, $groupPerms)) {
                    $groupPerms[$column] = false;
                }

                $permsInput[$groupId][$column] = $groupPerms[$column] ? 1 : 0;
            }
        }

        // Remove any columns that don't exist silently.
        $columns = array_flip($columns);
        foreach ($permsInput as $groupId => $groupPerms) {
            foreach (array_keys($groupPerms) as $field) {
                if (array_key_exists($field, $columns)) {
                    continue;
                }

                unset($permsInput[$groupId][$field]);
            }
        }

        foreach ($permsInput as $groupId => $groupPermissions) {
            // Ensure admins can't remove their own access to the ACP :D
            if ($groupId === ConstantsGroups::Admin->value) {
                $groupPermissions['canAccessACP'] = 1;
            }

            if (!$groupId) {
                continue;
            }

            $this->database->update('member_groups', $groupPermissions, Database::WHERE_ID_EQUALS, $groupId);
        }

        $this->page->addContentBox('Success!', $this->page->success('Changes Saved successfully.'));

        $this->updatePermissions = false;

        $this->showPerms();
    }

    /**
     * @param null|list<int> $groupIds
     *
     * @return array<Group>
     */
    private function fetchGroups(?array $groupIds): array
    {
        $groups = Group::selectMany(
            ($groupIds ? 'WHERE `id` IN ? ' : '') . 'ORDER BY `id` ASC',
            ...$groupIds ? [$groupIds] : [],
        );

        return Lodash::keyBy($groups, static fn($group): int => $group->id);
    }

    private function showPerms(): void
    {
        $page = '';

        $permInput = $this->request->post('perm');
        $groupListInput = $this->request->post('grouplist');
        $groupList = is_string($groupListInput)
            ? array_map(static fn($gid): int => (int) $gid, explode(',', $groupListInput))
            : null;

        if ($this->updatePermissions && is_array($permInput) && is_array($groupList)) {
            foreach ($groupList as $groupId) {
                if (array_key_exists($groupId, $permInput)) {
                    continue;
                }

                $permInput[$groupId] = [];
            }

            $this->updatePerms($permInput);

            return;
        }

        $groups = $this->fetchGroups($groupList);
        $numgroups = count($groups);

        if ($numgroups === 0) {
            $this->page->addContentBox('Error', $this->page->error("Don't play with my variables!"));
        }

        $widthPercent = (1 / $numgroups) * 100;
        $groupHeadings = '';
        foreach ($groups as $groupId => $group) {
            $groupHeadings .= $this->page->render('groups/show-permissions-group-heading.html', [
                'id' => $groupId,
                'title' => $group->title,
                'width_percent' => $widthPercent,
            ]);
        }

        $permissionsChart = [
            'Global' => [
                'canAccessACP' => 'Access ACP',
                'canModerate' => 'Global Moderator',
                'canViewBoard' => 'View Online Board',
                'canViewOfflineBoard' => 'View Offline Board',
            ],

            'Members' => [
                'canKarma' => '*Change Karma',
            ],

            'Posts' => [
                'canAttach' => 'Attach files',
                'canDeleteOwnPosts' => '*Delete Own Posts',
                'canEditPosts' => 'Edit',
                'canPost' => 'Create',
                'canUseSignatures' => 'Can have signatures',
            ],

            'Private/Instant Messaging' => [
                'canIM' => 'Can IM',
                'canPM' => 'Can PM',
            ],

            'Profiles' => [
                'canAddComments' => 'Add Comments',
                'canDeleteComments' => 'Delete own Comments',
                'canViewFullProfile' => 'Can View Full Profile',
            ],

            'Shoutbox' => [
                'canDeleteOwnShouts' => 'Delete Own Shouts',
                'canDeleteShouts' => 'Delete All Shouts',
                'canShout' => 'Can Shout',
                'canViewShoutbox' => 'View Shoutbox',
            ],

            'Statistics' => [
                'canViewStats' => 'View Board Stats',
                'legend' => 'Display in Legend',
            ],

            'Topics' => [
                'canDeleteOwnTopics' => '*Delete Own Topics',
                'canEditTopics' => 'Edit',
                'canLockOwnTopics' => '*Lock Own Topics',
                'canOverrideLockedTopics' => 'Post in locked topics',
                'canPoll' => 'Add Polls',
                'canCreateTopics' => 'Create',
            ],
        ];
        $permissionsTable = '';
        foreach ($permissionsChart as $category => $permissions) {
            $permissionsTable .= $this->page->render('groups/show-permissions-breaker-row.html', [
                'column_count' => 1 + $numgroups,
                'title' => $category,
            ]);

            foreach ($permissions as $field => $title) {
                $groupColumns = '';
                foreach ($groups as $groupId => $group) {
                    $groupColumns .= $this->page->render('groups/show-permissions-permission-row-group-column.html', [
                        'checked' => $this->page->checked((bool) $group->{$field}),
                        'groupID' => $groupId,
                        'permission' => $field,
                    ]);
                }

                $permissionsTable .= $this->page->render('groups/show-permissions-permission-row.html', [
                    'group_columns' => $groupColumns,
                    'title' => $title,
                ]);
            }
        }

        $page .= $this->page->render('groups/show-permissions.html', [
            'group_headings' => $groupHeadings,
            'group_list' => implode(',', array_keys($groups)),
            'permissions_table' => $permissionsTable,
        ]);

        $this->page->addContentBox('Perms', $page);
    }

    private function submitCreate(?int $gid): ?string
    {
        $groupName = $this->request->asString->post('groupname');
        $groupIcon = $this->request->asString->post('groupicon');

        $error = match (true) {
            !$groupName => 'Group name required!',
            mb_strlen($groupName) > 250 => 'Group name must not exceed 250 characters!',
            $groupIcon && mb_strlen($groupIcon) > 250 => 'Group icon must not exceed 250 characters!',
            $groupIcon && !filter_var($groupIcon, FILTER_VALIDATE_URL) => 'Group icon must be a valid image url',
            default => null,
        };

        if ($error !== null) {
            return $error;
        }

        $group = $gid ? Group::selectOne($gid) : null;
        $group ??= new Group();

        $group->icon = $groupIcon ?? '';
        $group->title = $groupName ?? '';

        $group->upsert();

        $this->page->addContentBox(
            $group->title . ' ' . ($gid ? 'edited' : 'created'),
            $this->page->success('Data saved.'),
        );

        $this->showPerms();

        return null;
    }

    private function create(?int $gid = null): void
    {
        $page = '';

        if ($this->request->post('submit') !== null) {
            $error = $this->submitCreate($gid);
            if ($error) {
                $page .= $this->page->error($error);
            }
        }

        $group = null;
        if ($gid) {
            $group = Group::selectOne($gid);
        }

        $page .= $this->page->render('groups/create.html', [
            'icon_url' => $group !== null ? $this->textFormatting->blockhtml($group->icon) : '',
            'submit' => $group !== null ? 'Edit' : 'Create',
            'title' => $group !== null ? $this->textFormatting->blockhtml($group->title) : '',
        ]);
        $this->page->addContentBox($group !== null ? 'Editing group: ' . $group->title : 'Create a group!', $page);
    }

    private function delete(): void
    {
        $page = '';
        $delete = (int) $this->request->asString->both('delete');
        if ($delete > 5) {
            $this->database->delete('member_groups', Database::WHERE_ID_EQUALS, $delete);
            $this->database->update(
                'members',
                [
                    'groupID' => 1,
                ],
                'WHERE `groupID`=?',
                $delete,
            );
        }

        $groups = Group::selectMany('WHERE id>5');
        $found = false;
        foreach ($groups as $group) {
            $found = true;
            $page .= $this->page->render('groups/delete.html', [
                'id' => $group->id,
                'title' => $group->title,
            ]);
        }

        if (!$found) {
            $page .= $this->page->error("You haven't created any groups to delete. "
                . "(Hint: default groups can't be deleted)");
        }

        $this->page->addContentBox('Delete Groups', $page);
    }
}
