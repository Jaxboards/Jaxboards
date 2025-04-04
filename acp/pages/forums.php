<?php

declare(strict_types=1);

if (!defined(INACP)) {
    exit;
}

new forums();
final class forums
{
    /**
     * Saves the posted tree to mysql.
     *
     * @param array  $tree  The tree to save
     * @param string $path  The path in the tree
     * @param int    $order where the tree is place n the database
     */
    public static function mysqltree($tree, $path = '', $order = 0): void
    {
        global $DB;
        if (!is_array($tree)) {
            return;
        }

        foreach ($tree as $k => $v) {
            $k = mb_substr($k, 1);
            ++$order;
            $childPath = $path . $k . ' ';
            sscanf($childPath, 'c_%d', $cat);
            $children = mb_strstr($path, ' ');
            $formattedPath = $children ? trim($children) : '';
            if (is_array($v)) {
                self::mysqltree($v, $childPath . ' ', $order);
            }

            if ($k[0] === 'c') {
                $DB->safeupdate(
                    'categories',
                    [
                        'order' => $order,
                    ],
                    'WHERE `id`=?',
                    $cat,
                );
            } else {
                $DB->safeupdate(
                    'forums',
                    [
                        'cat_id' => $cat,
                        'order' => $order,
                        'path' => preg_replace(
                            '@\s+@',
                            ' ',
                            $formattedPath,
                        ),
                    ],
                    'WHERE `id`=?',
                    $k,
                );
            }
        }
    }

    public static function printtree(
        $tree,
        $data,
        $class = false,
        $highlight = 0,
    ) {
        global $PAGE;

        $html = '';
        if (count($tree) > 0) {
            foreach ($tree as $id => $children) {
                if (!isset($data[$id])) {
                    continue;
                }

                if (!is_array($data[$id])) {
                    continue;
                }

                $classes = [];
                $classes[] = is_string($id) && $id[0] === 'c'
                    ? 'parentlock'
                    : 'nofirstlevel';

                if ($highlight && $id === $highlight) {
                    $classes[] = 'highlight';
                }

                $classes = implode(' ', $classes);
                $trashcan = isset($data[$id]['trashcan']) && $data[$id]['trashcan'] ? $PAGE->parseTemplate(
                    'forums/order-forums-tree-item-trashcan.html',
                ) : '';

                if (
                    isset($data[$id]['mods'])
                    && is_array($data[$id]['mods'])
                    && !empty($data[$id]['mods'])
                ) {
                    $modCount = count(explode(',', $data[$id]['mods']));
                    $mods = $PAGE->parseTemplate(
                        'forums/order-forums-tree-item-mods.html',
                        [
                            'content' => 'moderator' . ($modCount === 1 ? '' : 's'),
                            'mod_count' => $modCount,
                        ],
                    );
                } else {
                    $mods = '';
                }

                $content = '';
                if (is_array($children)) {
                    $content = '' . self::printtree(
                        $children,
                        $data,
                        '',
                        $highlight,
                    );
                }

                $title = $data[$id]['title'];
                $html .= $PAGE->parseTemplate(
                    'forums/order-forums-tree-item.html',
                    [
                        'class' => $classes,
                        'content' => $content,
                        'id' => $id,
                        'mods' => $mods,
                        'title' => $title,
                        'trashcan' => $trashcan,
                    ],
                );
            }

            return $PAGE->parseTemplate(
                'forums/order-forums-tree.html',
                [
                    'class' => $class ?: '',
                    'content' => $html,
                ],
            );
        }

        return '';
    }

    public function __construct()
    {
        global $JAX,$PAGE;

        $links = [
            'create' => 'Create Forum',
            'createc' => 'Create Category',
            'order' => 'Manage',
        ];
        $sidebarLinks = '';
        foreach ($links as $do => $title) {
            $sidebarLinks .= $PAGE->parseTemplate(
                'sidebar-list-link.html',
                [
                    'title' => $title,
                    'url' => '?act=forums&do=' . $do,
                ],
            ) . PHP_EOL;
        }

        $sidebarLinks .= $PAGE->parseTemplate(
            'sidebar-list-link.html',
            [
                'title' => 'Recount Statistics',
                'url' => '?act=stats',
            ],
        ) . PHP_EOL;

        $PAGE->sidebar(
            $PAGE->parseTemplate(
                'sidebar-list.html',
                [
                    'content' => $sidebarLinks,
                ],
            ),
        );

        if (isset($JAX->b['delete']) && $JAX->b['delete']) {
            if (is_numeric($JAX->b['delete'])) {
                $this->deleteforum($JAX->b['delete']);

                return;
            }

            if (preg_match('@c_(\d+)@', (string) $JAX->b['delete'], $m)) {
                $this->deletecategory($m[1]);

                return;
            }
        } elseif (isset($JAX->b['edit']) && $JAX->b['edit']) {
            if (is_numeric($JAX->b['edit'])) {
                $this->createforum($JAX->b['edit']);

                return;
            }

            if (preg_match('@c_(\d+)@', (string) $JAX->b['edit'], $m)) {
                $this->createcategory($m[1]);

                return;
            }
        }

        if (!isset($JAX->g['do'])) {
            $JAX->g['do'] = null;
        }

        match ($JAX->g['do']) {
            'order' => $this->orderforums(),
            'create' => $this->createforum(),
            'createc' => $this->createcategory(),
            default => $this->orderforums(),
        };
    }

    public function orderforums($highlight = 0): void
    {
        global $PAGE,$DB,$JAX;
        $page = '';
        if ($highlight) {
            $page .= $PAGE->success(
                'Forum Created. Now, just place it wherever you like!',
            );
        }

        if (isset($JAX->p['tree']) && $JAX->p['tree']) {
            $JAX->p['tree'] = json_decode((string) $JAX->p['tree'], true);
            $data = self::mysqltree($JAX->p['tree']);
            if ($JAX->g['do'] === 'create') {
                return;
            }

            $page .= $PAGE->success('Data Saved');
        }

        $forums = [];
        $result = $DB->safeselect(
            [
                'id',
                'title',
                '`order`',
            ],
            'categories',
            'ORDER BY `order`,`id` ASC',
        );
        while ($f = $DB->arow($result)) {
            $forums['c_' . $f['id']] = ['title' => $f['title']];
            $cats[] = $f['id'];
        }

        $DB->disposeresult($result);

        $result = $DB->safeselect(
            [
                'id',
                'cat_id',
                'title',
                'subtitle',
                'lp_uid',
                'UNIX_TIMESTAMP(`lp_date`) AS `lp_date`',
                'lp_tid',
                'lp_topic',
                'path',
                'show_sub',
                'redirect',
                'topics',
                'posts',
                '`order`',
                'perms',
                'orderby',
                'nocount',
                'redirects',
                'trashcan',
                'mods',
                'show_ledby',
            ],
            'forums',
            'ORDER BY `order`,`title`',
        );
        $tree = [$result];
        while ($f = $DB->arow($result)) {
            $forums[$f['id']] = [
                'mods' => $f['mods'],
                'title' => $f['title'],
                'trashcan' => $f['trashcan'],
            ];
            $treeparts = explode(' ', (string) $f['path']);
            array_unshift($treeparts, 'c_' . $f['cat_id']);
            $intree = &$tree;
            foreach ($treeparts as $v) {
                if (trim($v) === '') {
                    continue;
                }

                if (trim($v) === '0') {
                    continue;
                }

                if (!isset($intree[$v]) || !is_array($intree[$v])) {
                    $intree[$v] = [];
                }

                $intree = &$intree[$v];
            }

            if (isset($intree[$f['id']]) && $intree[$f['id']]) {
                continue;
            }

            $intree[$f['id']] = true;
        }

        foreach ($cats as $v) {
            $sortedtree['c_' . $v] = $tree['c_' . $v] ?? null;
        }

        $page .= self::printtree(
            $sortedtree,
            $forums,
            'tree',
            $highlight,
        );
        $page .= $PAGE->parseTemplate(
            'forums/order-forums.html',
        );
        $PAGE->addContentBox('Forums', $page);
    }

    /**
     * Create & Edit forum.
     *
     * @param int $fid The forum ID. If set, this edits a forum,
     *                 otherwise it creates one.
     */
    public function createforum($fid = 0)
    {
        global $PAGE,$JAX,$DB;
        $page = '';
        $e = '';
        $forumperms = '';
        $fdata = [];
        if ($fid) {
            $result = $DB->safeselect(
                [
                    'id',
                    'cat_id',
                    'title',
                    'subtitle',
                    'lp_uid',
                    'UNIX_TIMESTAMP(`lp_date`) AS `lp_date`',
                    'lp_tid',
                    'lp_topic',
                    'path',
                    'show_sub',
                    'redirect',
                    'topics',
                    'posts',
                    '`order`',
                    'perms',
                    'orderby',
                    'nocount',
                    'redirects',
                    'trashcan',
                    'mods',
                    'show_ledby',
                ],
                'forums',
                'WHERE `id`=?',
                $DB->basicvalue($fid),
            );
            $fdata = $DB->arow($result);
            $DB->disposeresult($result);
        }

        if (isset($JAX->p['tree'])) {
            if ($JAX->p['tree']) {
                $this->orderforums();
            }

            $page .= $PAGE->success('Forum created.');
        }

        // Remove mod from forum.
        if (
            isset($JAX->b['rmod'])
            && is_numeric($JAX->b['rmod'])
            && $fdata['mods']
        ) {
            $exploded = explode(',', (string) $fdata['mods']);
            unset($exploded[array_search($JAX->b['rmod'], $exploded, true)]);
            $fdata['mods'] = implode(',', $exploded);
            $DB->safeupdate(
                'forums',
                [
                    'mods' => $fdata['mods'],
                ],
                'WHERE `id`=?',
                $DB->basicvalue($fid),
            );
            $this->updateperforummodflag();
            $PAGE->location('?act=forums&edit=' . $fid);
        }

        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            // Saves all of the data
            // really should be its own function, but I don't care.
            $grouppermsa = [];
            $groupperms = '';
            $result = $DB->safeselect(
                ['id'],
                'member_groups',
            );
            while ($f = $DB->arow($result)) {
                if (!isset($JAX->p['groups'][$f['id']])) {
                    $JAX->p['groups'][$f['id']] = [];
                }

                $options = ['read', 'start', 'reply', 'upload', 'view', 'poll'];
                $v = $JAX->p['groups'][$f['id']];
                if (isset($v['global']) && $v['global']) {
                    continue;
                }

                foreach ($options as $option) {
                    if (isset($v[$option])) {
                        continue;
                    }

                    $v[$option] = false;
                }

                $grouppermsa[$f['id']]
                    = ($v['read'] ? 8 : 0)
                    + ($v['start'] ? 4 : 0)
                    + ($v['reply'] ? 2 : 0)
                    + ($v['upload'] ? 1 : 0)
                    + ($v['view'] ? 16 : 0)
                    + ($v['poll'] ? 32 : 0);
            }

            foreach ($grouppermsa as $k => $v) {
                $groupperms .= pack('n*', $k, $v);
            }

            $sub = (int) $JAX->p['show_sub'];
            if (is_numeric($JAX->p['orderby'])) {
                $orderby = (int) $JAX->p['orderby'];
            }

            $result = $DB->safeselect(
                ['id'],
                'categories',
            );
            $thisrow = $DB->arow($result);
            $write = [
                'cat_id' => $JAX->pick(
                    $fdata['cat_id'] ?? null,
                    array_pop($thisrow),
                ),
                'mods' => $fdata['mods'] ?? null,
                'nocount' => $JAX->p['nocount'] ? 0 : 1,
                'orderby' => $orderby > 0 && $orderby <= 5 ? $orderby : 0,
                'perms' => $groupperms,
                'redirect' => $JAX->p['redirect'],
                'show_ledby' => (int) (isset($JAX->p['show_ledby']) && $JAX->p['show_ledby']),
                'show_sub' => $sub === 1 || $sub === 2 ? $sub : 0,
                'subtitle' => $JAX->p['description'],
                'title' => $JAX->p['title'],
                'trashcan' => (int) (isset($JAX->p['trashcan']) && $JAX->p['trashcan']),
                // Handling done below.
            ];
            $DB->disposeresult($result);

            // Add per-forum moderator.
            if (is_numeric($JAX->p['modid'])) {
                $result = $DB->safeselect(
                    ['id'],
                    'members',
                    'WHERE `id`=?',
                    $DB->basicvalue($JAX->p['modid']),
                );
                if ($DB->arow($result)) {
                    if (!in_array($JAX->p['modid'], isset($fdata['mods']) ? explode(',', $fdata['mods']) : [])) {
                        $write['mods'] = isset($fdata['mods'])
                            && $fdata['mods']
                            ? $fdata['mods'] . ',' . $JAX->p['modid']
                            : $JAX->p['modid'];
                    }
                } else {
                    $e = "You tried to add a moderator that doesn't exist!";
                }

                $DB->disposeresult($result);
            }

            if (!$write['title']) {
                $e = 'Forum title is required';
            }

            if ($e === '' || $e === '0') {
                // Clear trashcan on other forums.
                if (
                    $write['trashcan']
                    || (!$write['trashcan']
                    && isset($fdata['trashcan'])
                    && $fdata['trashcan'])
                ) {
                    $DB->safeupdate(
                        'forums',
                        [
                            'trashcan' => 0,
                        ],
                    );
                }

                if (!$fdata) {
                    $DB->safeinsert(
                        'forums',
                        $write,
                    );

                    return $this->orderforums($DB->insert_id(1));
                }

                $DB->safeupdate(
                    'forums',
                    $write,
                    'WHERE `id`=?',
                    $fid,
                );
                if ($JAX->p['modid']) {
                    $this->updateperforummodflag();
                }

                $page .= $PAGE->success('Data saved.');
            }

            $fdata = $write;
        }

        $perms = [];
        if (isset($fdata['perms']) && $fdata['perms']) {
            $unpack = unpack('n*', (string) $fdata['perms']);
            $counter = count($unpack);
            for ($x = 1; $x < $counter; $x += 2) {
                $perms[$unpack[$x]] = $unpack[$x + 1];
            }
        }

        $result = $DB->safeselect(
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
        );

        $groupperms = '';
        while ($f = $DB->arow($result)) {
            $global = !isset($perms[$f['id']]);
            if (!$global) {
                $p = isset($perms[$f['id']])
                    ? $JAX->parseperms($perms[$f['id']])
                    : null;
            }

            $groupperms .= $PAGE->parseTemplate(
                'forums/create-forum-permissions-row.html',
                [
                    'global' => $this->checkbox($f['id'], 'global', $global),
                    'poll' => $this->checkbox(
                        $f['id'],
                        'poll',
                        $global ? $f['can_poll'] : $p['poll'],
                    ),
                    'read' => $this->checkbox(
                        $f['id'],
                        'read',
                        $global ? 1 : $p['read'],
                    ),
                    'reply' => $this->checkbox(
                        $f['id'],
                        'reply',
                        $global ? $f['can_post']
                        : $p['reply'],
                    ),
                    'start' => $this->checkbox(
                        $f['id'],
                        'start',
                        $global ? $f['can_post_topics']
                        : $p['start'],
                    ),
                    'title' => $f['title'],
                    'upload' => $this->checkbox(
                        $f['id'],
                        'upload',
                        $global ? $f['can_attach'] : $p['upload'],
                    ),
                    'view' => $this->checkbox(
                        $f['id'],
                        'view',
                        $global ? 1 : $p['view'],
                    ),
                ],
            ) . PHP_EOL;
        }

        if ($e !== '' && $e !== '0') {
            $page .= $PAGE->error($e);
        }

        $subforumOptionsArray = [
            0 => 'Not at all',
            1 => 'One level below',
            2 => 'All subforums',
        ];

        $subforumOptions = '';
        foreach ($subforumOptionsArray as $value => $label) {
            $subforumOptions .= $PAGE->parseTemplate(
                'select-option.html',
                [
                    'label' => $label,
                    'selected' => isset($fdata['show_sub']) && $value === $fdata['show_sub'] ? 'selected="selected"' : '',
                    'value' => $value,
                ],
            ) . PHP_EOL;
        }

        $orderByOptionsArray = [
            0 => 'Last Post, Descending',
            1 => 'Last Post, Ascending',
            2 => 'Topic Creation Time, Descending',
            3 => 'Topic Creation Time, Ascending',
            4 => 'Topic Title, Descending',
            5 => 'Topic Title, Ascending',
        ];
        $orderByOptions = '';
        foreach ($orderByOptionsArray as $value => $label) {
            $orderByOptions .= $PAGE->parseTemplate(
                'select-option.html',
                [
                    'label' => $label,
                    'selected' => isset($fdata['orderby']) && $value === $fdata['orderby']
                    ? 'selected="selected"' : '',
                    'value' => $value,
                ],
            ) . PHP_EOL;
        }


        $page .= $PAGE->parseTemplate(
            'forums/create-forum.html',
            [
                'description' => isset($fdata['subtitle']) ? $JAX->blockhtml($fdata['subtitle']) : '',
                'no_count' => isset($fdata['nocount']) && $fdata['nocount']
                ? '' : ' checked="checked"',
                'order_by_options' => $orderByOptions,
                'redirect_url' => isset($fdata['redirect']) ? $JAX->blockhtml($fdata['redirect']) : '',
                'subforum_options' => $subforumOptions,
                'title' => isset($fdata['title']) ? $JAX->blockhtml($fdata['title']) : '',
                'trashcan' => isset($fdata['trashcan']) && $fdata['trashcan']
                ? ' checked="checked"' : '',
            ],
        ) . PHP_EOL;

        if (isset($fdata['mods']) && $fdata['mods']) {
            $result = $DB->safeselect(
                ['display_name', 'id'],
                'members',
                'WHERE `id` IN ?',
                explode(',', (string) $fdata['mods']),
            );
            $modList = '';
            while ($f = $DB->arow($result)) {
                $modList .= $PAGE->parseTemplate(
                    'forums/create-forum-moderators-mod.html',
                    [
                        'delete_link' => '?act=forums&edit=' . $fid . '&rmod=' . $f['id'],
                        'username' => $f['display_name'],
                    ],
                ) . PHP_EOL;
            }
        } else {
            $modList = 'No forum-specific moderators added!';
        }

        $moderators = $PAGE->parseTemplate(
            'forums/create-forum-moderators.html',
            [
                'mod_list' => $modList,
                'show_led_by' => isset($fdata['show_ledby']) && $fdata['show_ledby']
                     ? 'checked="checked"' : '',
            ],
        );

        $forumperms = $PAGE->parseTemplate(
            'forums/create-forum-permissions.html',
            [
                'content' => $groupperms,
                'submit' => $fid ? 'Save' : 'Next',
            ],
        );

        $PAGE->addContentBox(
            ($fid ? 'Edit' : 'Create') . ' Forum'
            . ($fid ? ' - ' . $JAX->blockhtml($fdata['title']) : ''),
            $page,
        );
        $PAGE->addContentBox('Moderators', $moderators);
        $PAGE->addContentBox('Forum Permissions', $forumperms);
    }

    public function deleteforum($id)
    {
        global $JAX,$DB,$PAGE;
        if (isset($JAX->p['submit']) && $JAX->p['submit'] === 'Cancel') {
            $PAGE->location('?act=forums&do=order');
        } elseif (isset($JAX->p['submit']) && $JAX->p['submit']) {
            $DB->safedelete(
                'forums',
                'WHERE `id`=?',
                $DB->basicvalue($id),
            );
            if ($JAX->p['moveto']) {
                $DB->safeupdate(
                    'topics',
                    [
                        'fid' => $JAX->p['moveto'],
                    ],
                    ' WHERE `fid`=?',
                    $DB->basicvalue($id),
                );
                $topics = $DB->affected_rows(1);
            } else {
                $result = $DB->safespecial(
                    <<<'EOT'
                        DELETE
                        FROM %t
                        WHERE `tid` IN (
                            SELECT `id`
                            FROM %t
                            WHERE `fid`=?
                        )
                        EOT
                    ,
                    ['posts', 'topics'],
                    $DB->basicvalue($id),
                );

                $posts = $DB->affected_rows(1);
                $DB->safedelete(
                    'topics',
                    'WHERE `fid`=?',
                    $DB->basicvalue($id),
                );
                $topics = $DB->affected_rows(1);
            }

            $page = '';
            if ($topics > 0) {
                $page .= ($JAX->p['moveto'] ? 'Moved' : 'Deleted')
                    . " {$topics} topics" . (isset($posts) && $posts
                    ? " and {$posts} posts" : '');
            }

            return $PAGE->addContentBox(
                'Forum Deletion',
                $PAGE->success(
                    $PAGE->parseTemplate(
                        'forums/delete-forum-deleted.html',
                        [
                            'content' => $page,
                        ],
                    ),
                ),
            );
        }

        $result = $DB->safeselect(
            [
                'cat_id',
                'id',
                'lp_tid',
                'lp_topic',
                'lp_uid',
                'mods',
                'nocount',
                'order',
                'orderby',
                'path',
                'perms',
                'posts',
                'redirect',
                'redirects',
                'show_ledby',
                'show_sub',
                'subtitle',
                'title',
                'topics',
                'trashcan',
                'UNIX_TIMESTAMP(`lp_date`) AS `lp_date`',
            ],
            'forums',
            'WHERE `id`=?',
            $DB->basicvalue($id),
        );
        $fdata = $DB->arow($result);
        $DB->disposeresult($result);

        if (!$fdata) {
            return $PAGE->addContentBox(
                'Deleting Forum: ' . $id,
                $PAGE->error("Forum doesn't exist."),
            );
        }

        $result = $DB->safeselect(
            [
                'cat_id',
                'id',
                'lp_tid',
                'lp_topic',
                'lp_uid',
                'mods',
                'nocount',
                'order',
                'orderby',
                'path',
                'perms',
                'posts',
                'redirect',
                'redirects',
                'show_ledby',
                'show_sub',
                'subtitle',
                'title',
                'topics',
                'trashcan',
                'UNIX_TIMESTAMP(`lp_date`) AS `lp_date`',
            ],
            'forums',
        );
        $forums = '';
        while ($f = $DB->arow($result)) {
            $forums .= $PAGE->parseTemplate(
                'select-option.html',
                [
                    'label' => $f['title'],
                    'selected' => '',
                    'value' => $f['id'],
                ],
            ) . PHP_EOL;
        }

        $PAGE->addContentBox(
            'Deleting Forum: ' . $fdata['title'],
            $PAGE->parseTemplate(
                'forums/delete-forum.html',
                [
                    'forum_options' => $forums,
                ],
            ),
        );

        return null;
    }

    public function createcategory($cid = false): void
    {
        global $JAX,$DB,$PAGE;
        $page = '';
        $cdata = [];
        if (!$cid && isset($JAX->p['cat_id'])) {
            $cid = (int) $JAX->p['cat_id'];
        }

        if ($cid) {
            $result = $DB->safeselect(
                ['id', 'title'],
                'categories',
                'WHERE `id`=?',
                $DB->basicvalue($cid),
            );
            $cdata = $DB->arow($result);
            $DB->disposeresult($result);
        }

        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (
                trim((string) $JAX->p['cat_name']) === ''
                || trim((string) $JAX->p['cat_name']) === '0'
            ) {
                $page .= $PAGE->error('All fields required');
            } else {
                $data = ['title' => $JAX->p['cat_name']];
                if (!empty($cdata)) {
                    $DB->safeupdate(
                        'categories',
                        $data,
                        'WHERE `id`=?',
                        $DB->basicvalue($cid),
                    );
                    $page .= $PAGE->success(
                        'Category edited.',
                    );
                } else {
                    $DB->safeinsert(
                        'categories',
                        $data,
                    );
                    $page .= $PAGE->success(
                        'Category created.',
                    );
                    $data['id'] = (int) $DB->insert_id();
                }

                $cdata = $data;
            }
        }

        $categoryTitle = '';
        if (isset($cdata['title'])) {
            $categoryTitle = $JAX->blockhtml($cdata['title']);
        }

        $PAGE->addContentBox(
            ($cdata ? 'Edit' : 'Create') . ' Category',
            $page . PHP_EOL . $PAGE->parseTemplate(
                'forums/create-category.html',
                [
                    'id' => $cdata && isset($cdata['id']) ? $cdata['id'] : 0,
                    'submit' => isset($cdata) && $cdata ? 'Edit' : 'Create',
                    'title' => $categoryTitle,
                ],
            ),
        );
    }

    public function deletecategory($id): void
    {
        global $PAGE,$DB,$JAX;
        $page = '';
        $e = '';
        $result = $DB->safeselect(
            ['id', 'title'],
            'categories',
        );
        $categories = [];
        $cattitle = false;
        while ($f = $DB->arow($result)) {
            if ($f['id'] !== $id) {
                $categories[$f['id']] = $f['title'];
            } else {
                $cattitle = $f['title'];
            }
        }

        if ($cattitle === false) {
            $e = "The category you're trying to delete does not exist.";
        }

        if (!$e && isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (!isset($categories[$JAX->p['moveto']])) {
                $e = 'Invalid category to move forums to.';
            } else {
                $DB->safeupdate(
                    'forums',
                    [
                        'cat_id' => $JAX->p['moveto'],
                    ],
                    'WHERE `cat_id`=?',
                    $DB->basicvalue($id),
                );
                $DB->safedelete(
                    'categories',
                    'WHERE `id`=?',
                    $DB->basicvalue($id),
                );
                $page .= $PAGE->success('Category deleted!');
            }
        }

        if ($categories === []) {
            $e = 'You cannot delete the only category you have left.';
        }

        if ($e !== '' && $e !== '0') {
            $page .= $PAGE->error($e);
        } else {
            $categoryOptions = '';
            foreach ($categories as $categoryId => $categoryName) {
                $categoryOptions .= $PAGE->parseTemplate(
                    'select-option.html',
                    [
                        'label' => $categoryName,
                        'selected' => '',
                        'value' => '' . $categoryId,
                    ],
                ) . PHP_EOL;
            }

            $page .= $PAGE->parseTemplate(
                'forums/delete-category.html',
                [
                    'category_options' => $categoryOptions,
                ],
            );
        }

        $PAGE->addContentBox('Category Deletion', $page);
    }

    /**
     * This function updates all of the user->mod flags
     * that specify whether or not a user is a per-forum mod
     * based on the comma delimited list of mods for each forum.
     */
    public function updateperforummodflag(): void
    {
        global $DB;
        $DB->safeupdate(
            'members',
            [
                'mod' => 0,
            ],
        );
        $result = $DB->safeselect(
            ['mods'],
            'forums',
        );
        // Build an array of mods.
        $mods = [];
        while ($f = $DB->arow($result)) {
            foreach (explode(',', (string) $f['mods']) as $v) {
                if ($v === '') {
                    continue;
                }

                if ($v === '0') {
                    continue;
                }

                $mods[$v] = 1;
            }
        }

        // Update.
        $DB->safeupdate(
            'members',
            [
                'mod' => 1,
            ],
            'WHERE `id` IN ?',
            array_keys($mods),
        );
    }

    public function checkbox($id, $name, $checked)
    {
        global $PAGE;

        return $PAGE->parseTemplate(
            'forums/create-forum-permissions-row-checkbox.html',
            [
                'checked' => $checked ? 'checked="checked" ' : '',
                'global' => $name === 'global'
                    ? 'onchange="globaltoggle(this.parentNode.parentNode,this.checked);"'
                    : '',
                'id' => $id,
                'name' => $name,
            ],
        );
    }
}
