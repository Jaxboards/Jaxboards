<?php

if (!defined(INACP)) {
    die();
}

new forums();
class forums
{
    public function __construct()
    {
        global $JAX,$PAGE;

        $links = array(
            'order' => 'Manage',
            'create' => 'Create Forum',
            'createc' => 'Create Category',
        );
        $sidebarLinks = '';
        foreach ($links as $do => $title) {
            $sidebarLinks .= $PAGE->parseTemplate(
                JAXBOARDS_ROOT . '/acp/views/sidebar-list-link.html',
                array(
                    'url' => '?act=forums&do=' . $do,
                    'title' => $title,
                )
            ) . PHP_EOL;
        }
        $sidebarLinks .= $PAGE->parseTemplate(
            JAXBOARDS_ROOT . '/acp/views/sidebar-list-link.html',
            array(
                'url' => '?act=stats',
                'title' => 'Refresh Statistics',
            )
        ) . PHP_EOL;

        $PAGE->sidebar(
            $PAGE->parseTemplate(
                JAXBOARDS_ROOT . '/acp/views/sidebar-list.html',
                array(
                    'content' => $sidebarLinks,
                )
            )
        );

        if (isset($JAX->b['delete']) && $JAX->b['delete']) {
            if (is_numeric($JAX->b['delete'])) {
                return $this->deleteforum($JAX->b['delete']);
            }
            if (preg_match('@c_(\\d+)@', $JAX->b['delete'], $m)) {
                return $this->deletecategory($m[1]);
            }
        } elseif (isset($JAX->b['edit']) && $JAX->b['edit']) {
            if (is_numeric($JAX->b['edit'])) {
                return $this->createforum($JAX->b['edit']);
            }
            if (preg_match('@c_(\\d+)@', $JAX->b['edit'], $m)) {
                return $this->createcategory($m[1]);
            }
        }

        if (!isset($JAX->g['do'])) {
            $JAX->g['do'] = null;
        }
        switch ($JAX->g['do']) {
            case 'order':
                $this->orderforums();
                break;
            case 'create':
                $this->createforum();
                break;
            case 'createc':
                $this->createcategory();
                break;
            default:
                $this->orderforums();
                break;
        }
    }

    public function orderforums($highlight = 0)
    {
        global $PAGE,$DB,$JAX;
        $page = '';
        if ($highlight) {
            $page .= $PAGE->success(
                'Forum Created. Now, just place it wherever you like!'
            );
        }
        if (isset($JAX->p['tree']) && $JAX->p['tree']) {
            $JAX->p['tree'] = json_decode($JAX->p['tree'], true);
            $data = $this->mysqltree($JAX->p['tree']);
            if ('create' == $JAX->g['do']) {
                return;
            }
            $page .= $PAGE->success('Data Saved');
        }
        $forums = array();
        $result = $DB->safeselect(
            '`id`,`title`,`order`',
            'categories',
            'ORDER BY `order`,`id` ASC'
        );
        while ($f = $DB->arow($result)) {
            $forums['c_' . $f['id']] = array('title' => $f['title']);
            $cats[] = $f['id'];
        }
        $DB->disposeresult($result);

        $result = $DB->safeselect(
            <<<'EOT'
`id`,`cat_id`,`title`,`subtitle`,`lp_uid`,
UNIX_TIMESTAMP(`lp_date`) AS `lp_date`,`lp_tid`,`lp_topic`,`path`,`show_sub`,
`redirect`,`topics`,`posts`,`order`,`perms`,`orderby`,`nocount`,`redirects`,
`trashcan`,`mods`,`show_ledby`
EOT
            ,
            'forums',
            'ORDER BY `order`,`title`'
        );
        $tree = array($result);
        while ($f = $DB->arow($result)) {
            $forums[$f['id']] = array(
                'title' => $f['title'],
                'trashcan' => $f['trashcan'],
                'mods' => $f['mods'],
            );
            $treeparts = explode(' ', $f['path']);
            array_unshift($treeparts, 'c_' . $f['cat_id']);
            $intree = &$tree;
            foreach ($treeparts as $v) {
                if (!trim($v)) {
                    continue;
                }

                if (!isset($intree[$v]) || !is_array($intree[$v])) {
                    $intree[$v] = array();
                }

                $intree = &$intree[$v];
            }

            if (!isset($intree[$f['id']]) || !$intree[$f['id']]) {
                $intree[$f['id']] = true;
            }
        }
        foreach ($cats as $v) {
            if (isset($tree['c_' . $v])) {
                $sortedtree['c_' . $v] = $tree['c_' . $v];
            } else {
                $sortedtree['c_' . $v] = null;
            }
        }
        $page .= static::printtree(
            $sortedtree,
            $forums,
            'tree',
            $highlight
        );
        $page .= $PAGE->parseTemplate(
            JAXBOARDS_ROOT . '/acp/views/forums/order-forums.html'
        );
        $PAGE->addContentBox('Forums', $page);
    }

    /**
     * Saves the posted tree to mysql.
     *
     * @param array $tree The tree to save
     * @param string $path The path in the tree
     * @param int $order Where the tree is place n the database.
     *
     * @return void
     */
    public static function mysqltree($tree, $path = '', $order = 0)
    {
        global $DB;
        $r = array();
        if (!is_array($tree)) {
            return;
        }
        foreach ($tree as $k => $v) {
            $k = mb_substr($k, 1);
            ++$order;
            $childPath = $path . $k . ' ';
            sscanf($childPath, 'c_%d', $cat);
            $formattedPath = trim(mb_strstr($path, ' '));
            if (is_array($v)) {
                self::mysqltree($v, $childPath . ' ', $order);
            }
            if ('c' == $k[0]) {
                $DB->safeupdate(
                    'categories',
                    array(
                        'order' => $order,
                    ),
                    'WHERE `id`=?',
                    $cat
                );
            } else {
                $DB->safeupdate(
                    'forums',
                    array(
                        'path' => preg_replace(
                            '@\\s+@',
                            ' ',
                            $formattedPath
                        ),
                        'order' => $order,
                        'cat_id' => $cat,
                    ),
                    'WHERE `id`=?',
                    $k
                );
            }
        }
    }

    public static function printtree($tree, $data, $class = false, $highlight = 0)
    {
        global $PAGE;

        $html = '';
        if (0 < count($tree)) {
            foreach ($tree as $id => $children) {
                if (!isset($data[$id]) || !is_array($data[$id])) {
                    continue;
                }
                $classes = array();
                if ('c' == $id[0]) {
                    $classes[] = 'parentlock';
                } else {
                    $classes[] = 'nofirstlevel';
                }
                if ($highlight && $id == $highlight) {
                    $classes[] = 'highlight';
                }
                $classes = implode(' ', $classes);
                if (isset($data[$id]['trashcan']) && $data[$id]['trashcan']) {
                    $trashcan =
                        $PAGE->parseTemplate(
                            JAXBOARDS_ROOT . '/acp/views/forums/order-forums-tree-item-trashcan.html'
                        );
                } else {
                    $trashcan = '';
                }
                if (isset($data[$id]['mods'])
                    && is_array($data[$id]['mods'])
                    && !empty($data[$id]['mods'])
                ) {
                    $modCount = count(explode(',', $data[$id]['mods']));
                    $mods = $PAGE->parseTemplate(
                        JAXBOARDS_ROOT . '/acp/views/forums/order-forums-tree-item-mods.html',
                        array(
                            'mod_count' => $modCount,
                            'content' => 'moderator' . (1 == $nummods ? '' : 's'),
                        )
                    );
                } else {
                    $mods = '';
                }
                $content = '';
                if (is_array($children)) {
                    $content = '' . static::printtree(
                        $children,
                        $data,
                        '',
                        $highlight
                    );
                }
                $title = $data[$id]['title'];
                $html .= $PAGE->parseTemplate(
                    JAXBOARDS_ROOT . '/acp/views/forums/order-forums-tree-item.html',
                    array(
                        'class' => $classes,
                        'content' => $content,
                        'id' => $id,
                        'mods' => $mods,
                        'title' => $title,
                        'trashcan' => $trashcan,
                    )
                );
            }

            return $PAGE->parseTemplate(
                JAXBOARDS_ROOT . '/acp/views/forums/order-forums-tree.html',
                array(
                    'class' => $class ? : '',
                    'content' => $html,
                )
            );
        }
        return '';
    }

    /**
     * Create & Edit forum
     *
     * @param int $fid The forum ID. If set, this edits a forum,
     *                 otherwise it creates one.
     *
     * @return void
     */
    public function createforum($fid = 0)
    {
        global $PAGE,$JAX,$DB;
        $page = '';
        $e = '';
        $forumperms = '';
        $fdata = array();
        if ($fid) {
            $result = $DB->safeselect(
                <<<'EOT'
`id`,`cat_id`,`title`,`subtitle`,`lp_uid`,
UNIX_TIMESTAMP(`lp_date`) AS `lp_date`,`lp_tid`,`lp_topic`,`path`,`show_sub`,
`redirect`,`topics`,`posts`,`order`,`perms`,`orderby`,`nocount`,`redirects`,
`trashcan`,`mods`,`show_ledby`
EOT
                ,
                'forums',
                'WHERE `id`=?',
                $DB->basicvalue($fid)
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
        if (isset($JAX->b['rmod']) && is_numeric($JAX->b['rmod'])) {
            // Remove mod from forum.
            if ($fdata['mods']) {
                $exploded = explode(',', $fdata['mods']);
                unset($exploded[array_search($JAX->b['rmod'], $exploded)]);
                $fdata['mods'] = implode(',', $exploded);
                $DB->safeupdate(
                    'forums',
                    array(
                        'mods' => $fdata['mods'],
                    ),
                    'WHERE `id`=?',
                    $DB->basicvalue($fid)
                );
                $this->updateperforummodflag();
                $PAGE->location('?act=forums&edit=' . $fid);
            }
        }

        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            // Saves all of the data
            // really should be its own function, but I don't care.
            $grouppermsa = array();
            $groupperms = '';
            $result = $DB->safeselect(
                '`id`',
                'member_groups'
            );
            while ($f = $DB->arow($result)) {
                if (!isset($JAX->p['groups'][$f['id']])) {
                    $JAX->p['groups'][$f['id']] = array();
                }
                $options = array('read', 'start', 'reply', 'upload', 'view', 'poll');
                $v = $JAX->p['groups'][$f['id']];
                if (!isset($v['global']) || !$v['global']) {
                    foreach ($options as $option) {
                        if (!isset($v[$option])) {
                            $v[$option] = false;
                        }
                    }
                    $grouppermsa[$f['id']]
                        = ($v['read'] ? 8 : 0) +
                        ($v['start'] ? 4 : 0) +
                        ($v['reply'] ? 2 : 0) +
                        ($v['upload'] ? 1 : 0) +
                        ($v['view'] ? 16 : 0) +
                        ($v['poll'] ? 32 : 0);
                }
            }
            foreach ($grouppermsa as $k => $v) {
                $groupperms .= pack('n*', $k, $v);
            }
            $sub = $JAX->p['showsub'];
            if (is_numeric($JAX->p['orderby'])) {
                $orderby = $JAX->p['orderby'];
            }
            $result = $DB->safeselect(
                '`id`',
                'categories'
            );
            $thisrow = $DB->arow($result);
            $write = array(
                'title' => $JAX->p['title'],
                'cat_id' => $JAX->pick(
                    isset($fdata['cat_id']) ? $fdata['cat_id'] : null,
                    array_pop($thisrow)
                ),
                'subtitle' => $JAX->p['description'],
                'perms' => $groupperms,
                'redirect' => $JAX->p['redirect'],
                'show_sub' => 1 == $sub || 2 == $sub ? $sub : 0,
                'nocount' => $JAX->p['nocount'] ? 0 : 1,
                'orderby' => ($orderby > 0 && $orderby <= 5) ? $orderby : 0,
                'trashcan' =>
                    (int) (isset($JAX->p['trashcan']) && $JAX->p['trashcan']),
                'show_ledby' =>
                    (int) (isset($JAX->p['show_ledby']) && $JAX->p['show_ledby']),
                'mods' => isset($fdata['mods']) ? $fdata['mods'] : null,
                // Handling done below.
            );
            $DB->disposeresult($result);

            // Add per-forum moderator.
            if (is_numeric($JAX->p['modid'])) {
                $result = $DB->safeselect(
                    <<<'EOT'
`id`,`name`,`pass`,`email`,`sig`,`posts`,`group_id`,`avatar`,`usertitle`,
UNIX_TIMESTAMP(`join_date`) AS `join_date`,
UNIX_TIMESTAMP(`last_visit`) AS `last_visit`,`contact_skype`,`contact_yim`,
`contact_msn`,`contact_gtalk`,`contact_aim`,`website`,
`birthdate`, DAY(`birthdate`) AS `dob_day`,
MONTH(`birthdate`) AS `dob_month`, YEAR(`birthdate`) AS `dob_year`,
`about`,`display_name`,`full_name`,`contact_steam`,`location`,`gender`,
`friends`,`enemies`,`sound_shout`,`sound_im`,`sound_pm`,`sound_postinmytopic`,
`sound_postinsubscribedtopic`,`notify_pm`,`notify_postinmytopic`,
`notify_postinsubscribedtopic`,`ucpnotepad`,`skin_id`,`contact_twitter`,
`email_settings`,`nowordfilter`,INET6_NTOA(`ip`) AS `ip`,`mod`,`wysiwyg`
EOT
                    ,
                    'members',
                    'WHERE `id`=?',
                    $DB->basicvalue($JAX->p['modid'])
                );
                if ($DB->arow($result)) {
                    if (false === array_search(
                        $JAX->p['modid'],
                        isset($fdata['mods']) ?
                        explode(',', $fdata['mods']) : array()
                    )
                    ) {
                        $write['mods'] = (isset($fdata['mods'])
                            && $fdata['mods']) ?
                            $fdata['mods'] . ',' . $JAX->p['modid'] :
                            $JAX->p['modid'];
                    }
                } else {
                    $e = "You tried to add a moderator that doesn't exist!";
                }
                $DB->disposeresult($result);
            }
            if (!$write['title']) {
                $e = 'Forum title is required';
            }

            if (!$e) {
                // Clear trashcan on other forums.
                if ($write['trashcan']
                    || (!$write['trashcan']
                    && isset($fdata['trashcan'])
                    && $fdata['trashcan'])
                ) {
                    $DB->safeupdate(
                        'forums',
                        array(
                            'trashcan' => 0,
                        )
                    );
                }

                if ($fdata) {
                    $DB->safeupdate(
                        'forums',
                        $write,
                        'WHERE `id`=?',
                        $fid
                    );
                    if ($JAX->p['modid']) {
                        $this->updateperforummodflag();
                    }
                    $page .= $PAGE->success('Data saved.');
                } else {
                    $DB->safeinsert(
                        'forums',
                        $write
                    );

                    return $this->orderforums($DB->insert_id(1));
                }
            }
            $fdata = $write;
        }

        $perms = array();
        if (isset($fdata['perms']) && $fdata['perms']) {
            $unpack = unpack('n*', $fdata['perms']);
            for ($x = 1; $x < count($unpack); $x += 2) {
                $perms[$unpack[$x]] = $unpack[$x + 1];
            }
        }
        $result = $DB->safeselect(
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
            'member_groups'
        );

        $groupperms = '';
        while ($f = $DB->arow($result)) {
            $global = !isset($perms[$f['id']]);
            if (!$global) {
                if (isset($perms[$f['id']])) {
                    $p = $JAX->parseperms($perms[$f['id']]);
                } else {
                    $p = null;
                }
            }
            $groupperms .= $PAGE->parseTemplate(
                JAXBOARDS_ROOT . '/acp/views/forums/create-forum-permissions-row.html',
                array(
                    'title' => $f['title'],
                    'global' => $this->checkbox($f['id'], 'global', $global),
                    'view' => $this->checkbox(
                        $f['id'],
                        'view',
                        $global ? 1 : $p['view']
                    ),
                    'read' => $this->checkbox(
                        $f['id'],
                        'read',
                        $global ? 1 : $p['read']
                    ),
                    'start' => $this->checkbox(
                        $f['id'],
                        'start',
                        $global ? $f['can_post_topics'] :
                        $p['start']
                    ),
                    'reply' => $this->checkbox(
                        $f['id'],
                        'reply',
                        $global ? $f['can_post'] :
                        $p['reply']
                    ),
                    'upload' => $this->checkbox(
                        $f['id'],
                        'upload',
                        $global ? $f['can_attach'] : $p['upload']
                    ),
                    'poll' => $this->checkbox(
                        $f['id'],
                        'poll',
                        $global ? $f['can_poll'] : $p['poll']
                    ),
                )
            ) . PHP_EOL;
        }
        if ($e) {
            $page .= $PAGE->error($e);
        }
        $subforumOptionsArray = array(
            0 => 'Not at all',
            1 => 'One level below',
            2 => 'All subforums',
        );
        $subforumOptions = '';
        foreach ($subforumOptionsArray as $value => $label) {
            $subforumOptions .= $PAGE->parseTemplate(
                JAXBOARDS_ROOT . '/acp/views/select-option.html',
                array(
                    'value' => $value,
                    'label' => $label,
                    'selected' => isset($fdata['show_sub']) && $k == $fdata['show_sub'] ?
                    'selected="selected"' : '',
                )
            ) . PHP_EOL;
        }
        $orderByOptionsArray = array(
            0 => 'Last Post, Descending',
            1 => 'Last Post, Ascending',
            2 => 'Topic Creation Time, Descending',
            3 => 'Topic Creation Time, Ascending',
            4 => 'Topic Title, Descending',
            5 => 'Topic Title, Ascending',
        );
        $orderByOptions = '';
        foreach ($orderByOptionsArray as $value => $label) {
            $orderByOptions .= $PAGE->parseTemplate(
                JAXBOARDS_ROOT . '/acp/views/select-option.html',
                array(
                    'value' => $value,
                    'label' => $label,
                    'selected' => isset($fdata['show_sub']) && $k == $fdata['show_sub'] ?
                    'selected="selected"' : '',
                )
            ) . PHP_EOL;
        }


        $page .= $PAGE->parseTemplate(
            JAXBOARDS_ROOT . '/acp/views/forums/create-forum.html',
            array(
                'title' => isset($fdata['title']) ? $JAX->blockhtml($fdata['title']) : '',
                'description' => isset($fdata['subtitle']) ? $JAX->blockhtml($fdata['subtitle']) : '',
                'redirect_url' => isset($fdata['redirect']) ? $JAX->blockhtml($fdata['redirect']) : '',
                'subforum_options' => $subforumOptions,
                'order_by_options' => $orderByOptions,
                'no_count' => isset($fdata['nocount']) && $fdata['nocount'] ?
                '' : ' checked="checked"',
                'trashcan' => isset($fdata['trashcan']) && $fdata['trashcan']  ?
                ' checked="checked"' : '',
            )
        ) . PHP_EOL;

        if (isset($fdata['mods']) && $fdata['mods']) {
            $result = $DB->safeselect(
                '`display_name`,`id`',
                'members',
                'WHERE `id` IN ?',
                explode(',', $fdata['mods'])
            );
            $modList = '';
            while ($f = $DB->arow($result)) {
                $modList .= $PAGE->parseTemplate(
                    JAXBOARDS_ROOT . '/acp/views/forums/create-forum-moderators-mod.html',
                    array(
                        'username' => $f['display_name'],
                        'delete_link' => '?act=forums&edit=' . $fid . '&rmod=' . $f['id'],
                    )
                ) . PHP_EOL;
            }
        } else {
            $modList = 'No forum-specific moderators added!';
        }

        $moderators = $PAGE->parseTemplate(
            JAXBOARDS_ROOT . '/acp/views/forums/create-forum-moderators.html',
            array(
                'mod_list' => $modList,
                'show_led_by' => isset($fdata['show_ledby']) && $fdata['show_ledby'] ?
                     'checked="checked"' : '',
            )
        );

        $forumperms = $PAGE->parseTemplate(
            JAXBOARDS_ROOT . '/acp/views/forums/create-forum-permissions.html',
            array(
                'content' => $groupperms,
                'submit' => $fid ? 'Save' : 'Next',
            )
        );

        $PAGE->addContentBox(
            ($fid ? 'Edit' : 'Create') . ' Forum' .
            ($fid ? ' - ' . $JAX->blockhtml($fdata['title']) : ''),
            $page
        );
        $PAGE->addContentBox('Moderators', $moderators);
        $PAGE->addContentBox('Forum Permissions', $forumperms);
    }

    public function deleteforum($id)
    {
        global $JAX,$DB,$PAGE;
        if (isset($JAX->p['submit']) && 'Cancel' == $JAX->p['submit']) {
            $PAGE->location('?act=forums&do=order');
        } elseif (isset($JAX->p['submit']) && $JAX->p['submit']) {
            $DB->safedelete(
                'forums',
                'WHERE `id`=?',
                $DB->basicvalue($id)
            );
            if ($JAX->p['moveto']) {
                $DB->safeupdate(
                    'topics',
                    array(
                        'fid' => $JAX->p['moveto'],
                    ),
                    ' WHERE `fid`=?',
                    $DB->basicvalue($id)
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
                    array('posts', 'topics'),
                    $DB->basicvalue($id)
                );

                $posts = $DB->affected_rows(1);
                $DB->safedelete(
                    'topics',
                    'WHERE `fid`=?',
                    $DB->basicvalue($id)
                );
                $topics = $DB->affected_rows(1);
            }
            $page = '';
            if ($topics > 0) {
                $page .= ($JAX->p['moveto'] ? 'Moved' : 'Deleted') .
                    " ${topics} topics" . ((isset($posts) && $posts) ?
                    " and ${posts} posts" : '');
            }

            return $PAGE->addContentBox(
                'Forum Deletion',
                $PAGE->success(
                    $PAGE->parseTemplate(
                        JAXBOARDS_ROOT . '/acp/views/forums/delete-forum-deleted.html',
                        array(
                            'content' => $page,
                        )
                    )
                )
            );
        }
        $result = $DB->safeselect(
            <<<'EOT'
`id`,`cat_id`,`title`,`subtitle`,`lp_uid`,
UNIX_TIMESTAMP(`lp_date`) AS `lp_date`,`lp_tid`,`lp_topic`,`path`,
`show_sub`,`redirect`,`topics`,`posts`,`order`,`perms`,`orderby`,`nocount`,
`redirects`,`trashcan`,`mods`,`show_ledby`
EOT
            ,
            'forums',
            'WHERE `id`=?',
            $DB->basicvalue($id)
        );
        $fdata = $DB->arow($result);
        $DB->disposeresult($result);

        if (!$fdata) {
            return $PAGE->addContentBox(
                'Deleting Forum: ' . $id,
                $PAGE->error("Forum doesn't exist.")
            );
        }

        $result = $DB->safeselect(
            <<<'EOT'
`id`,`cat_id`,`title`,`subtitle`,`lp_uid`,
UNIX_TIMESTAMP(`lp_date`) AS `lp_date`,`lp_tid`,`lp_topic`,`path`,
`show_sub`,`redirect`,`topics`,`posts`,`order`,`perms`,`orderby`,`nocount`,
`redirects`,`trashcan`,`mods`,`show_ledby`
EOT
            ,
            'forums'
        );
        $forums = '';
        while ($f = $DB->arow($result)) {
            $forums .= $PAGE->parseTemplate(
                JAXBOARDS_ROOT . '/acp/views/select-option.html',
                array(
                    'value' => $f['id'],
                    'label' => $f['title'],
                    'selected' => '',
                )
            ) . PHP_EOL;
        }
        $PAGE->addContentBox(
            'Deleting Forum: ' . $fdata['title'],
            $PAGE->parseTemplate(
                JAXBOARDS_ROOT . '/acp/views/forums/delete-forum.html',
                array(
                    'forum_options' => $forums,
                )
            )
        );
    }

    public function createcategory($cid = false)
    {
        global $JAX,$DB,$PAGE;
        $page = '';
        $cdata = array();
        if (!$cid && isset($JAX->p['cat_id'])) {
            $cid = (int) $JAX->p['cat_id'];
        }
        if ($cid) {
            $result = $DB->safeselect(
                '`id`,`title`',
                'categories',
                'WHERE `id`=?',
                $DB->basicvalue($cid)
            );
            $cdata = $DB->arow($result);
            $DB->disposeresult($result);
        }
        if (isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (!trim($JAX->p['cat_name'])) {
                $page .= $PAGE->error('All fields required');
            } else {
                $data = array('title' => $JAX->p['cat_name']);
                if (!empty($cdata)) {
                    $DB->safeupdate(
                        'categories',
                        $data,
                        'WHERE `id`=?',
                        $DB->basicvalue($cid)
                    );
                    $page .= $PAGE->success(
                        'Category edited.'
                    );
                } else {
                    $DB->safeinsert(
                        'categories',
                        $data
                    );
                    $page .= $PAGE->success(
                        'Category created.'
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
                JAXBOARDS_ROOT . '/acp/views/forums/create-category.html',
                array(
                    'id' => $cdata && isset($cdata['id']) ? $cdata['id'] : 0,
                    'title' => $categoryTitle,
                    'submit' => isset($cdata) && $cdata ? 'Edit' : 'Create',
                )
            )
        );
    }

    public function deletecategory($id)
    {
        global $PAGE,$DB,$JAX;
        $page = '';
        $e = '';
        $result = $DB->safeselect(
            '`id`,`title`',
            'categories'
        );
        $categories = array();
        $cattitle = false;
        while ($f = $DB->arow($result)) {
            if ($f['id'] != $id) {
                $categories[$f['id']] = $f['title'];
            } else {
                $cattitle = $f['title'];
            }
        }
        if (false === $cattitle) {
            $e = "The category you're trying to delete does not exist.";
        }

        if (!$e && isset($JAX->p['submit']) && $JAX->p['submit']) {
            if (!isset($categories[$JAX->p['moveto']])) {
                $e = 'Invalid category to move forums to.';
            } else {
                $DB->safeupdate(
                    'forums',
                    array(
                        'cat_id' => $JAX->p['moveto'],
                    ),
                    'WHERE `cat_id`=?',
                    $DB->basicvalue($id)
                );
                $DB->safedelete(
                    'categories',
                    'WHERE `id`=?',
                    $DB->basicvalue($id)
                );
                $page .= $PAGE->success('Category deleted!');
            }
        }
        if (empty($categories)) {
            $e = 'You cannot delete the only category you have left.';
        }
        if ($e) {
            $page .= $PAGE->error($e);
        } else {
            $categoryOptions = '';
            foreach ($categories as $categoryId => $categoryName) {
                $categoryOptions .= $PAGE->parseTemplate(
                    JAXBOARDS_ROOT . '/acp/views/select-option.html',
                    array(
                        'value' => '' . $categoryId,
                        'label' => $categoryName,
                        'selected' => '',
                    )
                ) . PHP_EOL;
            }
            $page .= $PAGE->parseTemplate(
                JAXBOARDS_ROOT . '/acp/views/forums/delete-category.html',
                array(
                    'category_options' => $categoryOptions,
                )
            );
        }
        $PAGE->addContentBox('Category Deletion', $page);
    }

    /**
     * This function updates all of the user->mod flags
     * that specify whether or not a user is a per-forum mod
     * based on the comma delimited list of mods for each forum.
     *
     * @return void
     */
    public function updateperforummodflag()
    {
        global $DB;
        $DB->safeupdate(
            'members',
            array(
                'mod' => 0,
            )
        );
        $result = $DB->safeselect(
            '`mods`',
            'forums'
        );
        // Build an array of mods.
        $mods = array();
        while ($f = $DB->arow($result)) {
            foreach (explode(',', $f['mods']) as $v) {
                if ($v) {
                    $mods[$v] = 1;
                }
            }
        }
        // Update.
        $DB->safeupdate(
            'members',
            array(
                'mod' => 1,
            ),
            'WHERE `id` IN ?',
            array_keys($mods)
        );
    }

    public function checkbox($id, $name, $checked)
    {
        global $PAGE;

        return $PAGE->parseTemplate(
            JAXBOARDS_ROOT . '/acp/views/forums/create-forum-permissions-row-checkbox.html',
            array(
                'id' => $id,
                'name' => $name,
                'checked' => $checked ? 'checked="checked" ' : '',
                'global' => 'global' === $name
                    ? 'onchange="globaltoggle(this.parentNode.parentNode,this.checked);"'
                    : '',
            )
        );
    }
}
