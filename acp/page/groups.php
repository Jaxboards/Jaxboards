<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;

use function array_flip;
use function explode;
use function is_numeric;
use function mb_strlen;
use function mb_strpos;
use function mb_substr;
use function preg_match;

use const PHP_EOL;

final class Groups
{
    function __construct(private Page $page) {}
    public $updatePermissions = true;

    public function route(): void
    {
        global $JAX;
        $links = [
            'create' => 'Create Group',
            'delete' => 'Delete Group',
            'perms' => 'Edit Permissions',
        ];
        $sidebarLinks = '';
        foreach ($links as $do => $title) {
            $sidebarLinks .= $this->page->parseTemplate(
                'sidebar-list-link.html',
                [
                    'title' => $title,
                    'url' => '?act=groups&do=' . $do,
                ],
            ) . PHP_EOL;
        }

        $this->page->sidebar(
            $this->page->parseTemplate(
                'sidebar-list.html',
                [
                    'content' => $sidebarLinks,
                ],
            ),
        );
        if (isset($JAX->g['edit']) && $JAX->g['edit']) {
            $JAX->g['do'] = 'edit';
        }

        if (!isset($JAX->g['do'])) {
            $JAX->g['do'] = null;
        }

        match ($JAX->g['do']) {
            'perms' => $this->showperms(),
            'create' => $this->create(),
            'edit' => $this->create($JAX->g['edit']),
            'delete' => $this->delete(),
            default => $this->showperms(),
        };
    }

    public function updateperms($perms)
    {
        global $DB;
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

            $DB->safeupdate(
                'member_groups',
                $v,
                'WHERE `id`=?',
                $k,
            );
        }

        $error = $DB->error();
        if ($error) {
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
        global $DB,$JAX;

        $page = '';

        if (
            $this->updatePermissions
            && isset($JAX->p['perm'])
            && $JAX->p['perm']
        ) {
            foreach (explode(',', (string) $JAX->p['grouplist']) as $v) {
                if (isset($JAX->p['perm'][$v]) && $JAX->p['perm'][$v]) {
                    continue;
                }

                $JAX->p['perm'][$v] = [];
            }

            return $this->updateperms($JAX->p['perm']);
        }

        if (
            !isset($JAX->b['grouplist'])
            || preg_match('@[^\d,]@', $JAX->b['grouplist'])
            || mb_strpos($JAX->b['grouplist'], ',,') !== false
        ) {
            $JAX->b['grouplist'] = '';
        }

        $result = $JAX->b['grouplist']
            ? $DB->safeselect(
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
                explode(',', (string) $JAX->b['grouplist']),
            )
            : $DB->safeselect(
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
        while ($f = $DB->arow($result)) {
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

        global $JAX,$DB;
        $page = '';
        $e = '';
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (!isset($JAX->p['groupname']) || !$JAX->p['groupname']) {
                $e = 'Group name required!';
            } elseif (mb_strlen((string) $JAX->p['groupname']) > 250) {
                $e = 'Group name must not exceed 250 characters!';
            } elseif (mb_strlen((string) $JAX->p['groupicon']) > 250) {
                $e = 'Group icon must not exceed 250 characters!';
            } elseif (
                $JAX->p['groupicon']
                && !$JAX->isurl($JAX->p['groupicon'])
            ) {
                $e = 'Group icon must be a valid image url';
            }

            if ($e !== '' && $e !== '0') {
                $page .= $this->page->error($e);
            } else {
                $write = [
                    'icon' => $JAX->p['groupicon'],
                    'title' => $JAX->p['groupname'],
                ];
                if ($gid) {
                    $DB->safeupdate(
                        'member_groups',
                        $write,
                        'WHERE `id`=?',
                        $DB->basicvalue($gid),
                    );
                } else {
                    $DB->safeinsert(
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
            $result = $DB->safeselect(
                ['title', 'icon'],
                'member_groups',
                'WHERE `id`=?',
                $DB->basicvalue($gid),
            );
            $gdata = $DB->arow($result);
            $DB->disposeresult($result);
        }

        $page .= $this->page->parseTemplate(
            'groups/create.html',
            [
                'icon_url' => $gid ? $JAX->blockhtml($gdata['icon']) : '',
                'submit' => $gid ? 'Edit' : 'Create',
                'title' => $gid ? $JAX->blockhtml($gdata['title']) : '',
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
        global $DB,$JAX;
        $page = '';
        if (
            isset($JAX->b['delete'])
            && is_numeric($JAX->b['delete'])
            && $JAX->b['delete'] > 5
        ) {
            $DB->safedelete(
                'member_groups',
                'WHERE `id`=?',
                $DB->basicvalue($JAX->b['delete']),
            );
            $DB->safeupdate(
                'members',
                [
                    'group_id' => 1,
                ],
                'WHERE `group_id`=?',
                $DB->basicvalue($JAX->b['delete']),
            );
        }

        $result = $DB->safeselect(
            ['id', 'title'],
            'member_groups',
            'WHERE `id`>5',
        );
        $found = false;
        while ($f = $DB->arow($result)) {
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
