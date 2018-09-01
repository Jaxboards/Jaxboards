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
        $sidebar = '';
        foreach ($links as $k => $v) {
            $sidebar .= '<li><a href="?act=forums&do='.$k.'">'.
                $v.'</a></li>'.PHP_EOL;
        }
        $PAGE->sidebar(
            <<<EOT
<ul>
    ${sidebar}
    <li><a href="?act=stats">Refresh Statistics</a></li>
</ul>'
EOT
        );

        if (@$JAX->b['delete']) {
            if (is_numeric($JAX->b['delete'])) {
                return $this->deleteforum($JAX->b['delete']);
            }
            if (preg_match('@c_(\\d+)@', $JAX->b['delete'], $m)) {
                return $this->deletecategory($m[1]);
            }
        } elseif (@$JAX->b['edit']) {
            if (is_numeric($JAX->b['edit'])) {
                return $this->createforum($JAX->b['edit']);
            }
            if (preg_match('@c_(\\d+)@', $JAX->b['edit'], $m)) {
                return $this->createcategory($m[1]);
            }
        }

        switch (@$JAX->g['do']) {
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

    public function showindex()
    {
        global $PAGE;
        $PAGE->addContentBox('Forums', 'Yadda<br />Yadda<br />Yadda');
    }

    public function orderforums($highlight = 0)
    {
        global $PAGE,$DB,$JAX;
        $page = '';
        if ($highlight) {
            $page .= 'Forum Created. Now, just place it wherever you like!<br />';
        }
        if (@$JAX->p['tree']) {
            $JAX->p['tree'] = json_decode($JAX->p['tree'], true);
            $data = $this->mysqltree($JAX->p['tree']);
            if ('create' == $JAX->g['do']) {
                return;
            }
            $page .= "<div class='success'>Data Saved</div>";
        }
        $forums = array();
        $result = $DB->safeselect(
            '`id`,`title`,`order`',
            'categories',
            'ORDER BY `order`,`id` ASC'
        );
        while ($f = $DB->arow($result)) {
            $forums['c_'.$f['id']] = array('title' => $f['title']);
            $cats[] = $f['id'];
        }
        $DB->disposeresult($result);

        $result = $DB->safeselect(
            <<<'EOT'
`id`,`cat_id`,`title`,`subtitle`,`lp_uid`,`lp_date`,`lp_tid`,`lp_topic`,`path`,
`show_sub`,`redirect`,`topics`,`posts`,`order`,`perms`,`orderby`,`nocount`,
`redirects`,`trashcan`,`mods`,`show_ledby`
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
            array_unshift($treeparts, 'c_'.$f['cat_id']);
            $intree = &$tree;
            foreach ($treeparts as $v) {
                if (!trim($v)) {
                    continue;
                }
                if (!is_array(@$intree[$v])) {
                    $intree[$v] = array();
                } /* BUGBUGBUG: Why does this sometimes generate
                    warnings without the @? */
                $intree = &$intree[$v];
            }
            if (!@$intree[$f['id']]) {
                $intree[$f['id']] = true;
            } /* BUGBUGBUG: Why does this sometimes generate
                warnings without the @? */
        }
        foreach ($cats as $v) {
            $sortedtree['c_'.$v] = @$tree['c_'.$v];
        }
        $page = $page.$this->printtree(
            $sortedtree,
            $forums,
            'tree',
            $highlight
        )."<form method='post'><input type='hidden' id='ordered' ".
        "name='tree' /><input type='submit' value='Save' /></form>";
        $page .= "<script type='text/javascript'>".
            "JAX.sortableTree($$('.tree'),'forum_','ordered')</script>";
        $PAGE->addContentBox('Forums', $page);
    }

    //saves the posted tree to mysql
    public static function mysqltree($tree, $p = '', $x = 0)
    {
        global $DB;
        $r = array();
        if (!is_array($tree)) {
            return;
        }
        foreach ($tree as $k => $v) {
            $k = mb_substr($k, 1);
            ++$x;
            $p2 = $p.$k.' ';
            sscanf($p2, 'c_%d', $cat);
            //$f=$p;
            $f = trim(mb_strstr($p, ' '));
            if (is_array($v)) {
                self::mysqltree($v, $p2.' ', $x);
            }
            if ('c' == $k[0]) {
                $DB->safeupdate(
                    'categories',
                    array(
                        'order' => $x,
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
                            $f
                        ),
                        'order' => $x,
                        'cat_id' => $cat,
                    ),
                    'WHERE `id`=?',
                    $k
                );
            }
        }
    }

    public static function printtree($t, $data, $class = false, $highlight = 0)
    {
        $r = '';
        foreach ($t as $k => $v) {
            $classes = array();
            if ('c' == $k[0]) {
                $classes[] = 'parentlock';
            } else {
                $classes[] = 'nofirstlevel';
            }
            if ($highlight && $k == $highlight) {
                $classes[] = 'highlight';
            }
            $r .= "<li id='forum_${k}' ".
                (!empty($classes) ? 'class="'.implode(' ', $classes).'"' : '').
                '>'.
                /* BUGBUGBUG: Why does this sometimes generate warnings
                 * without the @? */
                ((@$data[$k]['trashcan']) ?
                '<span class="icons trashcan"></span>' : '').
                $data[$k]['title'].
                ((@$data[$k]['mods']) ?
                ' - <i>'.($nummods = count(explode(',', $data[$k]['mods']))).
                /* BUGBUGBUG: Why does this sometimes generate warnings
                 * without the @? */
                ' moderator'.(1 == $nummods ? '' : 's').'</i>' : '').
                " <a href='?act=forums&delete=${k}' class='icons delete' ".
                "title='Delete'></a> <a href='?act=forums&edit=${k}' ".
                "class='icons edit' title='Edit'></a>".
                (is_array($v) ? self::printtree($v, $data, '', $highlight) : '').
                '</li>';
        }

        return '<ul '.($class ? "class='${class}'" : '').'>'.$r.'</ul>';
    }

    //also used to edit forum
    //btw, this function is so super messy right now but I don't care
    //I'm working 2 jobs and have NO TIME to clean this up
    //sorry if you have to see this abomination
    public function createforum($fid = false)
    {
        global $PAGE,$JAX,$DB;
        $page = '';
        $e = '';
        $forumperms = '';
        $fdata = array();
        if ($fid) {
            $result = $DB->safeselect(
                <<<'EOT'
`id`,`cat_id`,`title`,`subtitle`,`lp_uid`,`lp_date`,`lp_tid`,`lp_topic`,`path`,
`show_sub`,`redirect`,`topics`,`posts`,`order`,`perms`,`orderby`,`nocount`,
`redirects`,`trashcan`,`mods`,`show_ledby`
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
        if (is_numeric(@$JAX->b['rmod'])) {
            //remove mod from forum
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
                $PAGE->location('?act=forums&edit='.$fid);
            }
        }

        if (@$JAX->p['submit']) {
            //saves all of the data
            //really should be its own function, but I don't gaf
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
                'cat_id' => $JAX->pick(@$fdata['cat_id'], array_pop($thisrow)),
                'subtitle' => $JAX->p['description'],
                'perms' => $groupperms,
                'redirect' => $JAX->p['redirect'],
                'show_sub' => 1 == $sub || 2 == $sub ? $sub : 0,
                'nocount' => $JAX->p['nocount'] ? 0 : 1,
                'orderby' => ($orderby > 0 && $orderby <= 5) ? $orderby : 0,
                'trashcan' => @$JAX->p['trashcan'] ? 1 : 0,
                'show_ledby' => @$JAX->p['show_ledby'] ? 1 : 0,
                'mods' => @$fdata['mods'], //handling done below
            );
            $DB->disposeresult($result);

            //add per-forum moderator
            if (is_numeric($JAX->p['modid'])) {
                $result = $DB->safeselect(
                    <<<'EOT'
`id`,`name`,`pass`,`email`,`sig`,`posts`,`group_id`,`avatar`,`usertitle`,
`join_date`,`last_visit`,`contact_skype`,`contact_yim`,`contact_msn`,
`contact_gtalk`,`contact_aim`,`website`,`dob_day`,`dob_month`,`dob_year`,
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
                            $fdata['mods'].','.$JAX->p['modid'] :
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
                //clear trashcan on other forums
                if ($write['trashcan']
                    || (!$write['trashcan']
                    && @$fdata['trashcan'])
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

        //do perms table
        function checkbox($id, $name, $checked)
        {
            return '<input type="checkbox" class="switch yn" name="groups['.
                $id.']['.$name.']" '.
                ($checked ? 'checked="checked" ' : '').
                ('global' == $name ?
                ' onchange="globaltoggle(this.parentNode.parentNode,this.checked)" '
                : '').'/>';
        }

        $perms = array();
        if (@$fdata['perms']) {
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
                $p = $JAX->parseperms(@$perms[$f['id']]);
            }
            $groupperms .= '<tr><td>'.$f['title'].'</td><td>'.
                checkbox($f['id'], 'global', $global).'</td><td>'.
                checkbox($f['id'], 'view', $global ? 1 : $p['view']).
                '</td><td>'.checkbox(
                    $f['id'],
                    'read',
                    $global ? 1 : $p['read']
                ).
                '</td><td>'.checkbox(
                    $f['id'],
                    'start',
                    $global ? $f['can_post_topics'] :
                    $p['start']
                ).'</td><td>'.checkbox(
                    $f['id'],
                    'reply',
                    $global ? $f['can_post'] :
                    $p['reply']
                ).'</td><td>'.
                checkbox(
                    $f['id'],
                    'upload',
                    $global ? $f['can_attach'] : $p['upload']
                ).
                '</td><td>'.
                checkbox($f['id'], 'poll', $global ? $f['can_poll'] : $p['poll']).
                '</td></tr>';
        }
        $page .= ($e ? $PAGE->error($e) : '').
            "<form method='post'><table class='settings'>
            <tr><td>Forum Title:</td><td><input type='text' name='title' value='".
            $JAX->blockhtml(@$fdata['title'])."' /></td></tr>
            <tr><td>Description:</td><td><textarea name='description'>".
            $JAX->blockhtml(@$fdata['subtitle'])."</textarea></td></tr>
            <tr><td>Redirect URL:</td><td><input type='text' ".
            "name='redirect' value='".
            $JAX->blockhtml(@$fdata['redirect'])."' /></td></tr>
<tr><td>Show Subforums:</td><td><select name='showsub'>";
        $optionArray = array('Not at all', 'One level below', 'All subforums');
        foreach ($optionArray as $k => $v) {
            $page .= '<option value="'.$k.'"'.
                (($k == @$fdata['show_sub']) ? ' selected="selected"' : '').
                '>'.$v.'</option>';
        }
        $page .= "</select></td></tr>
<tr><td>Order Topics By:</td><td><select name='orderby'>";
        $optionsArray = array(
            'Last Post, Descending',
            'Last Post, Ascending',
            'Topic Creation Time, Descending',
            'Topic Creation Time, Ascending',
            'Topic Title, Descending',
            'Topic Title, Ascending',
        );
        foreach ($optionsArray as $k => $v) {
            $page .= "<option value='".$k."'".
                ((@$fdata['orderby'] == $k) ? " selected='selected'" : '').
                '>'.$v.'</option>';
        }
        $page .= "</select></td></tr>
<tr><td>Posts count towards post count?</td><td><input type='checkbox' ".
        "class='switch yn' name='nocount'".
        ((@$fdata['nocount']) ? '' : ' checked="checked"').
        " /></td></tr>
<tr><td>Trashcan?</td><td><input type='checkbox' class='switch yn' ".
        "name='trashcan'".
        ((@$fdata['trashcan']) ? ' checked="checked"' : '').
        ' /></td></tr>
</table>';

        $moderators = '<table class="settings">
<tr><td>Moderators:</td><td>';
        if (@$fdata['mods']) {
            $result = $DB->safeselect(
                '`display_name`,`id`',
                'members',
                'WHERE `id` IN ?',
                explode(',', $fdata['mods'])
            );
            $mods = '';
            while ($f = $DB->arow($result)) {
                $mods .= $f['display_name'].
                    ' <a href="?act=forums&edit='.$fid.'&rmod='.$f['id'].
                    '">X</a>, ';
            }
            $moderators .= mb_substr($mods, 0, -2);
        } else {
            $moderators .= 'No forum-specific moderators added!';
        }
        $moderators .= '<br /><input type="text" name="name" '.
            'onkeyup="$(\'validname\').className=\'bad\';'.
            'JAX.autoComplete(\'act=searchmembers&term=\'+'.
            'this.value,this,$(\'modid\'),event);" />
            <input type="hidden" id="modid" name="modid" '.
            'onchange="$(\'validname\').className=\'good\'"/>'.
            '<span id="validname"></span>'.
            '<input type="submit" name="submit" value="Add Moderator" />'.
            '</td></tr>
            <tr><td>Show "Forum Led By":</td><td>'.
            '<input type="checkbox" class="switch yn" name="show_ledby" '.
            ((@$fdata['show_ledby']) ? ' checked="checked"' : '').
            '/></td></tr>
            </table>';

        $forumperms .= "<table id='perms'>
<tr> <th>Group</th> <th>Use Global?</th> <th>View</th> <th>Read</th> ".
        '<th>Start</th> <th>Reply</th> <th>Upload</th> <th>Polls</th></tr>'.
        $groupperms.
        "
</table><br /><div class='center'><input type='submit' value='".
        ($fid ? 'Save' : 'Next').
        "' name='submit' /></div>
</form>
<script type='text/javascript'>
function globaltoggle(a,checked){
for(var x=0;x<6;x++) a.cells[x+2].style.visibility=checked?'hidden':'visible'
}
var perms=$('perms')
for(var x=1;x<perms.rows.length;x++){
 globaltoggle(perms.rows[x],perms.rows[x].getElementsByTagName('input')[0].checked)
}
</script>";

        $PAGE->addContentBox(
            ($fid ? 'Edit' : 'Create').' Forum'.
            ($fid ? ' - '.$JAX->blockhtml($fdata['title']) : ''),
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
                $page .= ($JAX->p['moveto'] ? 'Moved' : 'Deleted').
                    " ${topics} topics".((isset($posts) && $posts) ?
                    " and ${posts} posts" : '');
            }

            return $PAGE->addContentBox(
                'Forum Deletion',
                $PAGE->success(
                    $page.'<br /><br />'.
                    "<a href='?act=stats'>Statistics recount</a>".
                    ' suggested.<br /><br />'.
                    "<a href='?act=forums&do=order'>Back</a>"
                )
            );
        }
        $result = $DB->safeselect(
            <<<'EOT'
`id`,`cat_id`,`title`,`subtitle`,`lp_uid`,`lp_date`,`lp_tid`,`lp_topic`,`path`,
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
            $page = "Forum doesn't exist.";
        } else {
            $page = "<form method='post'>".
                "<input type='submit' name='submit' value='Delete' /></form>";
        }

        $result = $DB->safeselect(
            <<<'EOT'
`id`,`cat_id`,`title`,`subtitle`,`lp_uid`,`lp_date`,`lp_tid`,`lp_topic`,`path`,
`show_sub`,`redirect`,`topics`,`posts`,`order`,`perms`,`orderby`,`nocount`,
`redirects`,`trashcan`,`mods`,`show_ledby`
EOT
            ,
            'forums'
        );
        $forums = '<option value="">Nowhere! (delete)</option>';
        while ($f = $DB->arow($result)) {
            $forums .= '<option value="'.$f['id'].'">'.$f['title'].'</option>';
        }
        $page = "<form method='post'>Move all topics to: ".
            "<select name='moveto'>".$forums.
            "</select><br /><br /><input name='submit' ".
            "type='submit' value='Confirm Deletion' />".
            "<input name='submit' type='submit' value='Cancel' /></form>";
        $PAGE->addContentBox('Deleting Forum: '.$fdata['title'], $page);
    }

    public function createcategory($cid = false)
    {
        global $JAX,$DB,$PAGE;
        $page = '';
        $cdata = array();
        if ($cid) {
            $result = $DB->safeselect(
                '`id`,`title`,`order`',
                'categories',
                'WHERE `id`=?',
                $DB->basicvalue($cid)
            );
            $cdata = $DB->arow($result);
            $DB->disposeresult($result);
        }
        if (@$JAX->p['submit']) {
            if (!trim($JAX->p['cat_name'])) {
                $page .= $PAGE->error('All fields required');
            } else {
                $stuff = array('title' => $JAX->p['cat_name']);
                if ($cdata) {
                    $DB->safeupdate(
                        'categories',
                        $stuff,
                        'WHERE `id`=?',
                        $DB->basicvalue($cid)
                    );
                } else {
                    $DB->safeinsert(
                        'categories',
                        $stuff
                    );
                }
                $cdata = $stuff;

                $page .= $PAGE->success(
                    'Category '.($cdata ? 'edit' : 'creat').'ed.'
                );
            }
        }
        $page .= '<form method="post">
  <label>Category Title:</label><input type="text" name="cat_name" value="'.
        $JAX->blockhtml(@$cdata['title']).'" /><br />
  <input type="submit" name="submit" value="'.($cdata ? 'Edit' : 'Create').
        '" />
  </form>';

        $PAGE->addContentBox(($cdata ? 'Edit' : 'Create').' Category', $page);
    }

    public function deletecategory($id)
    {
        global $PAGE,$DB,$JAX;
        $page = '';
        $e = '';
        $result = $DB->safeselect(
            '`id`,`title`,`order`',
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
            $page .= '<form method="post"><label>Move all forums to:</label>'.
                '<select name="moveto">';
            foreach ($categories as $k => $v) {
                $page .= '<option value="'.$k.'">'.$v.'</option>';
            }
            $page .= '</select><br /><input type="submit" value="Delete \''.
                $JAX->blockhtml($cattitle).'\'" name="submit" /></select></form>';
        }
        $PAGE->addContentBox('Category Deletion', $page);
    }

    //this function updates all of the user->mod flags
    //that specify whether or not a user is a per-forum mod
    //based on the comma delimited list of mods for each forum
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
        //build an array of mods
        $mods = array();
        while ($f = $DB->arow($result)) {
            foreach (explode(',', $f['mods']) as $v) {
                if ($v) {
                    $mods[$v] = 1;
                }
            }
        }
        //update
        $DB->safeupdate(
            'members',
            array(
                'mod' => 1,
            ),
            'WHERE `id` IN ?',
            array_keys($mods)
        );
    }
}
