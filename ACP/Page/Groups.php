<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Constants\Groups as ConstantsGroups;
use Jax\Database;
use Jax\Request;
use Jax\TextFormatting;

use function _\keyBy;
use function array_flip;
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
        private readonly Page $page,
        private readonly Request $request,
        private readonly TextFormatting $textFormatting,
    ) {}

    public function render(): void
    {
        $this->page->sidebar([
            'create' => 'Create Group',
            'delete' => 'Delete Group',
            'perms' => 'Edit Permissions',
        ]);

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
            'can_access_acp',
            'can_post',
            'can_edit_posts',
            'can_add_comments',
            'can_delete_comments',
            'can_delete_own_posts',
            'can_post_topics',
            'can_edit_topics',
            'can_view_board',
            'can_view_offline_board',
            'flood_control',
            'can_override_locked_topics',
            'can_view_shoutbox',
            'can_shout',
            'can_moderate',
            'can_delete_shouts',
            'can_delete_own_shouts',
            'can_karma',
            'can_im',
            'can_pm',
            'can_lock_own_topics',
            'can_delete_own_topics',
            'can_use_sigs',
            'can_attach',
            'can_poll',
            'can_view_stats',
            'legend',
            'can_view_fullprofile',
        ];

        // Set anything not sent to 0.
        foreach ($permsInput as $groupId => $groupPerms) {
            foreach ($columns as $column) {
                if (!isset($groupPerms[$column])) {
                    $groupPerms[$column] = false;
                }

                $permsInput[$groupId][$column] = $groupPerms[$column] ? 1 : 0;
            }
        }

        // Remove any columns that don't exist silently.
        $columns = array_flip($columns);
        foreach ($permsInput as $groupId => $groupPerms) {
            foreach (array_keys($groupPerms) as $field) {
                if (isset($columns[$field])) {
                    continue;
                }

                unset($permsInput[$groupId][$field]);
            }
        }

        foreach ($permsInput as $groupId => $groupPermissions) {
            // Ensure admins can't remove their own access to the ACP :D
            if ($groupId === ConstantsGroups::Admin->value) {
                $groupPermissions['can_access_acp'] = 1;
            }

            if (!$groupId) {
                continue;
            }

            $this->database->update(
                'member_groups',
                $groupPermissions,
                Database::WHERE_ID_EQUALS,
                $groupId,
            );
        }

        $this->page->addContentBox(
            'Success!',
            $this->page->success(
                'Changes Saved successfully.',
            ),
        );

        $this->updatePermissions = false;

        $this->showPerms();
    }

    /**
     * @param null|list<int> $groupIds
     *
     * @return array<string,array<string,null|int|string>>
     */
    private function fetchGroups(?array $groupIds): array
    {
        $result = $this->database->select(
            [
                'can_access_acp',
                'can_add_comments',
                'can_attach',
                'can_delete_comments',
                'can_delete_own_posts',
                'can_delete_own_shouts',
                'can_delete_own_topics',
                'can_delete_shouts',
                'can_edit_posts',
                'can_edit_topics',
                'can_im',
                'can_karma',
                'can_lock_own_topics',
                'can_moderate',
                'can_override_locked_topics',
                'can_pm',
                'can_poll',
                'can_post_topics',
                'can_post',
                'can_shout',
                'can_use_sigs',
                'can_view_board',
                'can_view_fullprofile',
                'can_view_offline_board',
                'can_view_shoutbox',
                'can_view_stats',
                'flood_control',
                'icon',
                'id',
                'legend',
                'title',
            ],
            'member_groups',
            ($groupIds ? 'WHERE `id` IN ? ' : '') . 'ORDER BY `id` ASC',
            ...($groupIds ? [$groupIds] : []),
        );

        return keyBy($this->database->arows($result), static fn($group) => $group['id']);
    }

    private function showPerms(): void
    {
        $page = '';

        $permInput = $this->request->post('perm');
        $groupListInput = $this->request->post('grouplist');
        $groupList = is_string($groupListInput)
            ? array_map(static fn($gid): int => (int) $gid, explode(',', $groupListInput))
            : null;

        if (
            $this->updatePermissions
            && is_array($permInput)
            && is_array($groupList)
        ) {
            foreach ($groupList as $groupId) {
                if (
                    isset($permInput[$groupId])
                    && $permInput[$groupId]
                ) {
                    continue;
                }

                $permInput[$groupId] = [];
            }

            $this->updatePerms($permInput);

            return;
        }

        $perms = $this->fetchGroups($groupList);
        $numgroups = count($perms);

        if ($numgroups === 0) {
            $this->page->addContentBox(
                'Error',
                $this->page->error(
                    "Don't play with my variables!",
                ),
            );
        }

        $widthPercent = 1 / $numgroups * 100;
        $groupHeadings = '';
        foreach ($perms as $groupId => $groupData) {
            $groupHeadings .= $this->page->parseTemplate(
                'groups/show-permissions-group-heading.html',
                [
                    'id' => $groupId,
                    'title' => (string) $groupData['title'],
                    'width_percent' => $widthPercent,
                ],
            );
        }

        $permissionsChart = [
            'Global' => [
                'can_access_acp' => 'Access ACP',
                'can_moderate' => 'Global Moderator',
                'can_view_board' => 'View Online Board',
                'can_view_offline_board' => 'View Offline Board',
            ],

            'Members' => [
                'can_karma' => '*Change Karma',
            ],

            'Posts' => [
                'can_attach' => 'Attach files',
                'can_delete_own_posts' => '*Delete Own Posts',
                'can_edit_posts' => 'Edit',
                'can_post' => 'Create',
                'can_use_sigs' => '*Can have signatures',
            ],

            'Private/Instant Messaging' => [
                'can_im' => 'Can IM',
                'can_pm' => 'Can PM',
            ],

            'Profiles' => [
                'can_add_comments' => 'Add Comments',
                'can_delete_comments' => '*Delete own Comments',
                'can_view_fullprofile' => 'Can View Full Profile',
            ],

            'Shoutbox' => [
                'can_delete_own_shouts' => 'Delete Own Shouts',
                'can_delete_shouts' => 'Delete All Shouts',
                'can_shout' => 'Can Shout',
                'can_view_shoutbox' => 'View Shoutbox',
            ],

            'Statistics' => [
                'can_view_stats' => 'View Board Stats',
                'legend' => 'Display in Legend',
            ],

            'Topics' => [
                'can_delete_own_topics' => '*Delete Own Topics',
                'can_edit_topics' => 'Edit',
                'can_lock_own_topics' => '*Lock Own Topics',
                'can_override_locked_topics' => 'Post in locked topics',
                'can_poll' => 'Add Polls',
                'can_post_topics' => 'Create',
            ],
        ];
        $permissionsTable = '';
        foreach ($permissionsChart as $category => $permissions) {
            $permissionsTable .= $this->page->parseTemplate(
                'groups/show-permissions-breaker-row.html',
                [
                    'column_count' => 1 + $numgroups,
                    'title' => $category,
                ],
            );

            foreach ($permissions as $field => $title) {
                $groupColumns = '';
                foreach ($perms as $groupId => $groupData) {
                    $groupColumns .= $this->page->parseTemplate(
                        'groups/show-permissions-permission-row-group-column.html',
                        [
                            'checked' => $groupData[$field]
                                ? 'checked="checked" ' : '',
                            'group_id' => $groupId,
                            'permission' => $field,
                        ],
                    );
                }

                $permissionsTable .= $this->page->parseTemplate(
                    'groups/show-permissions-permission-row.html',
                    [
                        'group_columns' => $groupColumns,
                        'title' => $title,
                    ],
                );
            }
        }

        $page .= $this->page->parseTemplate(
            'groups/show-permissions.html',
            [
                'group_headings' => $groupHeadings,
                'group_list' => implode(',', array_keys($perms)),
                'permissions_table' => $permissionsTable,
            ],
        );

        $this->page->addContentBox('Perms', $page);
    }

    private function create(?int $gid = null): void
    {
        $page = '';
        $error = null;

        $groupNameInput = $this->request->post('groupname');
        $groupIconInput = $this->request->post('groupicon');
        $groupName = is_string($groupNameInput) ? $groupNameInput : null;
        $groupIcon = is_string($groupIconInput) ? $groupIconInput : null;

        if ($this->request->post('submit') !== null) {
            $error = match (true) {
                !$groupName => 'Group name required!',
                mb_strlen($groupName) > 250 => 'Group name must not exceed 250 characters!',
                mb_strlen((string) $groupIcon) > 250 => 'Group icon must not exceed 250 characters!',
                $groupIcon && !filter_var($groupIcon, FILTER_VALIDATE_URL) => 'Group icon must be a valid image url',
                default => null,
            };

            if ($error !== null) {
                $page .= $this->page->error($error);
            } else {
                $write = [
                    'icon' => $groupIcon,
                    'title' => $groupName,
                ];
                if ($gid) {
                    $this->database->update(
                        'member_groups',
                        $write,
                        Database::WHERE_ID_EQUALS,
                        $gid,
                    );
                } else {
                    $this->database->insert(
                        'member_groups',
                        $write,
                    );
                }

                $this->page->addContentBox(
                    $write['title'] . ' ' . ($gid ? 'edited' : 'created'),
                    $this->page->success(
                        'Data saved.',
                    ),
                );

                $this->showPerms();

                return;
            }
        }

        $gdata = [];
        if ($gid) {
            $result = $this->database->select(
                ['title', 'icon'],
                'member_groups',
                Database::WHERE_ID_EQUALS,
                $gid,
            );
            $gdata = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        $page .= $this->page->parseTemplate(
            'groups/create.html',
            [
                'icon_url' => $gdata ? $this->textFormatting->blockhtml($gdata['icon']) : '',
                'submit' => $gdata ? 'Edit' : 'Create',
                'title' => $gdata ? $this->textFormatting->blockhtml($gdata['title']) : '',
            ],
        );
        $this->page->addContentBox(
            $gdata ? 'Editing group: ' . $gdata['title'] : 'Create a group!',
            $page,
        );
    }

    private function delete(): void
    {
        $page = '';
        $delete = (int) $this->request->asString->both('delete');
        if ($delete > 5) {
            $this->database->delete(
                'member_groups',
                Database::WHERE_ID_EQUALS,
                $delete,
            );
            $this->database->update(
                'members',
                [
                    'group_id' => 1,
                ],
                'WHERE `group_id`=?',
                $delete,
            );
        }

        $result = $this->database->select(
            ['id', 'title'],
            'member_groups',
            'WHERE `id`>5',
        );
        $found = false;
        while ($group = $this->database->arow($result)) {
            $found = true;
            $page .= $this->page->parseTemplate(
                'groups/delete.html',
                [
                    'id' => $group['id'],
                    'title' => $group['title'],
                ],
            );
        }

        if (!$found) {
            $page .= $this->page->error(
                "You haven't created any groups to delete. "
                    . "(Hint: default groups can't be deleted)",
            );
        }

        $this->page->addContentBox('Delete Groups', $page);
    }
}
