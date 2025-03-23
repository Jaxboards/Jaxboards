<?php

if (!defined(INACP)) {
    exit;
}

new groups();
final class groups
{
    public $updatePermissions = true;

    public function __construct()
    {
        global $JAX,$PAGE;
        $links = [
            'create' => 'Create Group',
            'delete' => 'Delete Group',
            'perms' => 'Edit Permissions',
        ];
        $sidebarLinks = '';
        foreach ($links as $do => $title) {
            $sidebarLinks .= $PAGE->parseTemplate(
                'sidebar-list-link.html',
                [
                    'title' => $title,
                    'url' => '?act=groups&do=' . $do,
                ],
            ) . PHP_EOL;
        }

        $PAGE->sidebar(
            $PAGE->parseTemplate(
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
        global $PAGE,$DB;
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
            $PAGE->addContentBox(
                'Error',
                $PAGE->error($error),
            );
        } else {
            $PAGE->addContentBox(
                'Success!',
                $PAGE->success(
                    'Changes Saved successfully.',
                ),
            );
        }

        $this->updatePermissions = false;

        return $this->showperms();
    }

    public function showperms()
    {
        global $DB,$PAGE,$JAX;

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
                <<<'EOT'
                    `id`,`title`,`can_post`,`can_edit_posts`,`can_post_topics`,`can_edit_topics`,
                    `can_add_comments`,`can_delete_comments`,`can_view_board`,
                    `can_view_offline_board`,`flood_control`,`can_override_locked_topics`,
                    `icon`,`can_shout`,`can_moderate`,`can_delete_shouts`,`can_delete_own_shouts`,
                    `can_karma`,`can_im`,`can_pm`,`can_lock_own_topics`,`can_delete_own_topics`,
                    `can_use_sigs`,`can_attach`,`can_delete_own_posts`,`can_poll`,`can_access_acp`,
                    `can_view_shoutbox`,`can_view_stats`,`legend`,`can_view_fullprofile`
                    EOT
                ,
                'member_groups',
                'WHERE `id` IN ? ORDER BY `id` ASC',
                explode(',', (string) $JAX->b['grouplist']),
            )
            : $DB->safeselect(
                <<<'EOT'
                    `id`,`title`,`can_post`,`can_edit_posts`,`can_post_topics`,`can_edit_topics`,
                    `can_add_comments`,`can_delete_comments`,`can_view_board`,
                    `can_view_offline_board`,`flood_control`,`can_override_locked_topics`,
                    `icon`,`can_shout`,`can_moderate`,`can_delete_shouts`,`can_delete_own_shouts`,
                    `can_karma`,`can_im`,`can_pm`,`can_lock_own_topics`,`can_delete_own_topics`,
                    `can_use_sigs`,`can_attach`,`can_delete_own_posts`,`can_poll`,`can_access_acp`,
                    `can_view_shoutbox`,`can_view_stats`,`legend`,`can_view_fullprofile`
                    EOT
                ,
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
            $PAGE->addContentBox(
                'Error',
                $PAGE->error(
                    "Don't play with my variables!",
                ),
            );
        }

        $grouplist = mb_substr($grouplist, 0, -1);
        $widthPercent = 1 / $numgroups * 100;
        $groupHeadings = '';
        foreach ($perms as $groupId => $groupData) {
            $groupHeadings .= $PAGE->parseTemplate(
                'groups/show-permissions-group-heading.html',
                [
                    'id' => $groupId,
                    'title' => $groupData['title'],
                    'width_percent' => $widthPercent,
                ],
            ) . PHP_EOL;
        }

        $permissionsChart = [
            'breaker1' => 'Global',

            'breaker2' => 'Members',

            'breaker3' => 'Posts',

            'breaker4' => 'Topics',

            'breaker5' => 'Profiles',

            'breaker6' => 'Shoutbox',

            'breaker7' => 'Private/Instant Messaging',

            'breaker8' => 'Statistics',
            'can_access_acp' => 'Access ACP',
            'can_add_comments' => 'Add Comments',
            'can_attach' => 'Attach files',
            'can_delete_comments' => '*Delete own Comments',
            'can_delete_own_posts' => '*Delete Own Posts',
            'can_delete_own_shouts' => 'Delete Own Shouts',
            'can_delete_own_topics' => '*Delete Own Topics',
            'can_delete_shouts' => 'Delete All Shouts',
            'can_edit_posts' => 'Edit',
            'can_edit_topics' => 'Edit',
            'can_im' => 'Can IM',
            'can_karma' => '*Change Karma',
            'can_lock_own_topics' => '*Lock Own Topics',
            'can_moderate' => 'Global Moderator',
            'can_override_locked_topics' => 'Post in locked topics',
            'can_pm' => 'Can PM',
            'can_poll' => 'Add Polls',
            'can_post' => 'Create',
            'can_post_topics' => 'Create',
            'can_shout' => 'Can Shout',
            'can_use_sigs' => '*Can have signatures',
            'can_view_board' => 'View Online Board',
            'can_view_fullprofile' => 'Can View Full Profile',
            'can_view_offline_board' => 'View Offline Board',
            'can_view_shoutbox' => 'View Shoutbox',
            'can_view_stats' => 'View Board Stats',
            'legend' => 'Display in Legend',
        ];
        $permissionsTable = '';
        foreach ($permissionsChart as $k => $v) {
            if (mb_substr($k, 0, 7) === 'breaker') {
                $permissionsTable .= $PAGE->parseTemplate(
                    'groups/show-permissions-breaker-row.html',
                    [
                        'column_count' => 1 + $numgroups,
                        'title' => $v,
                    ],
                ) . PHP_EOL;
            } else {
                $groupColumns = '';
                foreach ($perms as $groupId => $groupData) {
                    $groupColumns .= $PAGE->parseTemplate(
                        'groups/show-permissions-permission-row-group-column.html',
                        [
                            'checked' => $groupData[$k]
                            ? 'checked="checked" ' : '',
                            'group_id' => $groupId,
                            'permission' => $k,
                        ],
                    ) . PHP_EOL;
                }

                $permissionsTable .= $PAGE->parseTemplate(
                    'groups/show-permissions-permission-row.html',
                    [
                        'group_columns' => $groupColumns,
                        'title' => $v,
                    ],
                ) . PHP_EOL;
            }
        }

        $page .= $PAGE->parseTemplate(
            'groups/show-permissions.html',
            [
                'group_headings' => $groupHeadings,
                'group_list' => $grouplist,
                'permissions_table' => $permissionsTable,
            ],
        );

        $PAGE->addContentBox('Perms', $page);

        return null;
    }

    public function create($gid = false)
    {
        if ($gid && !is_numeric($gid)) {
            $gid = false;
        }

        global $PAGE,$JAX,$DB;
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
                $page .= $PAGE->error($e);
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

                $PAGE->addContentBox(
                    $write['title'] . ' ' . ($gid ? 'edited' : 'created'),
                    $PAGE->success(
                        'Data saved.',
                    ),
                );

                return $this->showperms();
            }
        }

        if ($gid) {
            $result = $DB->safeselect(
                '`title`,`icon`',
                'member_groups',
                'WHERE `id`=?',
                $DB->basicvalue($gid),
            );
            $gdata = $DB->arow($result);
            $DB->disposeresult($result);
        }

        $page .= $PAGE->parseTemplate(
            'groups/create.html',
            [
                'icon_url' => $gid ? $JAX->blockhtml($gdata['icon']) : '',
                'submit' => $gid ? 'Edit' : 'Create',
                'title' => $gid ? $JAX->blockhtml($gdata['title']) : '',
            ],
        );
        $PAGE->addContentBox(
            $gid ? 'Editing group: ' . $gdata['title'] : 'Create a group!',
            $page,
        );

        return null;
    }

    public function delete(): void
    {
        global $PAGE,$DB,$JAX;
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
            '`id`,`title`',
            'member_groups',
            'WHERE `id`>5',
        );
        $found = false;
        while ($f = $DB->arow($result)) {
            $found = true;
            $page .= $PAGE->parseTemplate(
                'groups/delete.html',
                [
                    'id' => $f['id'],
                    'title' => $f['title'],
                ],
            );
        }

        if (!$found) {
            $page .= $PAGE->error(
                "You haven't created any groups to delete. "
                . "(Hint: default groups can't be deleted)",
            );
        }

        $PAGE->addContentBox('Delete Groups', $page);
    }
}
