<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Database;
use Jax\Jax;
use Jax\Request;
use Jax\TextFormatting;

use function array_flip;
use function explode;
use function is_numeric;
use function mb_strlen;
use function mb_strpos;
use function mb_substr;
use function preg_match;

use const PHP_EOL;

/**
 * @psalm-api
 */
final class Groups
{
    private $updatePermissions = true;

    public function __construct(
        private readonly Database $database,
        private readonly Jax $jax,
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


        $do = null;
        if ($this->request->get('edit')) {
            $do = 'edit';
        }

        match ($this->request->get('do')) {
            'perms' => $this->showperms(),
            'create' => $this->create(),
            'edit' => $this->create($this->request->get('edit')),
            'delete' => $this->delete(),
            default => $this->showperms(),
        };
    }

    public function updateperms($perms)
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
        foreach ($perms as $k => $v2) {
            foreach ($columns as $v) {
                if (!isset($v2[$v])) {
                    $v2[$v] = false;
                }

                $perms[$k][$v] = $v2[$v] ? 1 : 0;
            }
        }

        // Remove any columns that don't exist silently.
        $columns = array_flip($columns);
        foreach ($perms as $k => $v) {
            foreach ($v as $k2 => $v2) {
                if (isset($columns[$k2])) {
                    continue;
                }

                unset($perms[$k][$k2]);
            }
        }

        // Update this.
        foreach ($perms as $k => $v) {
            if ($k === 2) {
                $v['can_access_acp'] = 1;
            }

            if (!$k) {
                continue;
            }

            $this->database->safeupdate(
                'member_groups',
                $v,
                'WHERE `id`=?',
                $k,
            );
        }

        $error = $this->database->error();
        if ($error !== '') {
            $this->page->addContentBox(
                'Error',
                $this->page->error($error),
            );
        } else {
            $this->page->addContentBox(
                'Success!',
                $this->page->success(
                    'Changes Saved successfully.',
                ),
            );
        }

        $this->updatePermissions = false;

        return $this->showperms();
    }

    public function showperms()
    {
        $page = '';

        if (
            $this->updatePermissions
            && $this->request->post('perm')
        ) {
            foreach (explode(',', (string) $this->request->post('grouplist')) as $v) {
                if (
                    isset($this->request->post('perm')[$v])
                    && $this->request->post('perm')[$v]
                ) {
                    continue;
                }

                $this->request->post('perm')[$v] = [];
            }

            return $this->updateperms($this->request->post('perm'));
        }

        $groupList = $this->request->both('grouplist');
        if (
            !$groupList
            || preg_match('@[^\d,]@', (string) $groupList)
            || mb_strpos((string) $groupList, ',,') !== false
        ) {
        }

        $result = $groupList
            ? $this->database->safeselect(
                [
                    'id',
                    'title',
                    'can_post',
                    'can_edit_posts',
                    'can_post_topics',
                    'can_edit_topics',
                    'can_add_comments',
                    'can_delete_comments',
                    'can_view_board',
                    'can_view_offline_board',
                    'flood_control',
                    'can_override_locked_topics',
                    'icon',
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
                    'can_delete_own_posts',
                    'can_poll',
                    'can_access_acp',
                    'can_view_shoutbox',
                    'can_view_stats',
                    'legend',
                    'can_view_fullprofile',
                ],
                'member_groups',
                'WHERE `id` IN ? ORDER BY `id` ASC',
                explode(',', (string) $this->request->both('grouplist')),
            )
            : $this->database->safeselect(
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
                'ORDER BY id ASC',
            );
        $numgroups = 0;
        $grouplist = '';
        while ($f = $this->database->arow($result)) {
            ++$numgroups;
            $perms[$f['id']] = $f;
            $grouplist .= $f['id'] . ',';
        }

        if ($numgroups === 0) {
            $this->page->addContentBox(
                'Error',
                $this->page->error(
                    "Don't play with my variables!",
                ),
            );
        }

        $grouplist = mb_substr($grouplist, 0, -1);
        $widthPercent = 1 / $numgroups * 100;
        $groupHeadings = '';
        foreach ($perms as $groupId => $groupData) {
            $groupHeadings .= $this->page->parseTemplate(
                'groups/show-permissions-group-heading.html',
                [
                    'id' => $groupId,
                    'title' => $groupData['title'],
                    'width_percent' => $widthPercent,
                ],
            ) . PHP_EOL;
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
            ) . PHP_EOL;

            foreach ($permissions as $k => $v) {
                $groupColumns = '';
                foreach ($perms as $groupId => $groupData) {
                    $groupColumns .= $this->page->parseTemplate(
                        'groups/show-permissions-permission-row-group-column.html',
                        [
                            'checked' => $groupData[$k]
                            ? 'checked="checked" ' : '',
                            'group_id' => $groupId,
                            'permission' => $k,
                        ],
                    ) . PHP_EOL;
                }

                $permissionsTable .= $this->page->parseTemplate(
                    'groups/show-permissions-permission-row.html',
                    [
                        'group_columns' => $groupColumns,
                        'title' => $v,
                    ],
                ) . PHP_EOL;
            }
        }

        $page .= $this->page->parseTemplate(
            'groups/show-permissions.html',
            [
                'group_headings' => $groupHeadings,
                'group_list' => $grouplist,
                'permissions_table' => $permissionsTable,
            ],
        );

        $this->page->addContentBox('Perms', $page);

        return null;
    }

    public function create($gid = false)
    {
        if ($gid && !is_numeric($gid)) {
            $gid = false;
        }

        $page = '';
        $error = null;
        if ($this->request->post('submit')) {
            if (!$this->request->post('groupname')) {
                $error = 'Group name required!';
            } elseif (mb_strlen((string) $this->request->post('groupname')) > 250) {
                $error = 'Group name must not exceed 250 characters!';
            } elseif (mb_strlen((string) $this->request->post('groupicon')) > 250) {
                $error = 'Group icon must not exceed 250 characters!';
            } elseif (
                $this->request->post('groupicon')
                && !$this->jax->isurl($this->request->post('groupicon'))
            ) {
                $error = 'Group icon must be a valid image url';
            }

            if ($error !== null) {
                $page .= $this->page->error($error);
            } else {
                $write = [
                    'icon' => $this->request->post('groupicon'),
                    'title' => $this->request->post('groupname'),
                ];
                if ($gid) {
                    $this->database->safeupdate(
                        'member_groups',
                        $write,
                        'WHERE `id`=?',
                        $this->database->basicvalue($gid),
                    );
                } else {
                    $this->database->safeinsert(
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

                return $this->showperms();
            }
        }

        if ($gid) {
            $result = $this->database->safeselect(
                ['title', 'icon'],
                'member_groups',
                'WHERE `id`=?',
                $this->database->basicvalue($gid),
            );
            $gdata = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        $page .= $this->page->parseTemplate(
            'groups/create.html',
            [
                'icon_url' => $gid ? $this->textFormatting->blockhtml($gdata['icon']) : '',
                'submit' => $gid ? 'Edit' : 'Create',
                'title' => $gid ? $this->textFormatting->blockhtml($gdata['title']) : '',
            ],
        );
        $this->page->addContentBox(
            $gid ? 'Editing group: ' . $gdata['title'] : 'Create a group!',
            $page,
        );

        return null;
    }

    public function delete(): void
    {
        $page = '';
        if (
            is_numeric($this->request->both('delete'))
            && $this->request->both('delete') > 5
        ) {
            $this->database->safedelete(
                'member_groups',
                'WHERE `id`=?',
                $this->database->basicvalue($this->request->both('delete')),
            );
            $this->database->safeupdate(
                'members',
                [
                    'group_id' => 1,
                ],
                'WHERE `group_id`=?',
                $this->database->basicvalue($this->request->both('delete')),
            );
        }

        $result = $this->database->safeselect(
            ['id', 'title'],
            'member_groups',
            'WHERE `id`>5',
        );
        $found = false;
        while ($f = $this->database->arow($result)) {
            $found = true;
            $page .= $this->page->parseTemplate(
                'groups/delete.html',
                [
                    'id' => $f['id'],
                    'title' => $f['title'],
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
