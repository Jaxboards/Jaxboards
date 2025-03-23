<?php

$PAGE->loadmeta('modcp');

new modcontrols();
class modcontrols
{
    public $perms;

    private function box($title, $content)
    {
        $content = ($content ?: '--No Data--');

        return <<<EOT
            <div class='minibox'>
                <div class='title'>{$title}</div>
                <div class='content'>{$content}</div>
            </div>
            EOT;
    }

    public function __construct()
    {
        global $JAX,$PAGE,$USER;

        $this->perms = $JAX->getPerms();
        if (!$this->perms['can_moderate'] && !$USER['mod']) {
            $PAGE->JS('softurl');

            return $PAGE->JS(
                'alert',
                'Your account does not have moderator permissions.',
            );
        }
        if (isset($JAX->b['cancel']) && $JAX->b['cancel']) {
            return $this->cancel();
        }

        if ($PAGE->jsupdate && empty($JAX->p)) {
            return false;
        }

        if (isset($JAX->p['dot']) && $JAX->p['dot']) {
            return $this->dotopics($JAX->p['dot']);
        }
        if (isset($JAX->p['dop']) && $JAX->p['dop']) {
            return $this->doposts($JAX->p['dop']);
        }

        switch ($JAX->b['do']) {
            case 'modp':
                $this->modpost($JAX->b['pid']);

                break;

            case 'modt':
                $this->modtopic($JAX->b['tid']);

                break;

            case 'load':
                $this->load();

                break;

            case 'cp':
                $this->showmodcp();

                break;

            case 'emem':
                $this->editmembers();

                break;

            case 'iptools':
                $this->iptools();

                break;
        }
    }

    public function dotopics($do): void
    {
        global $PAGE,$SESS,$JAX,$DB;

        switch ($do) {
            case 'move':
                $PAGE->JS('modcontrols_move', 0);

                break;

            case 'moveto':
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
                    $DB->basicvalue($JAX->p['id']),
                );
                $rowfound = $DB->arow($result);
                $DB->disposeresult($result);
                if (!is_numeric($JAX->p['id']) || !$rowfound) {
                    return;
                }

                $result = $DB->safeselect(
                    '`fid`',
                    'topics',
                    'WHERE `id` IN ?',
                    explode(',', $SESS->vars['modtids']),
                );
                while ($f = $DB->arow($result)) {
                    $fids[$f['fid']] = 1;
                }

                $fids = array_flip($fids);
                $DB->safeupdate(
                    'topics',
                    [
                        'fid' => $JAX->p['id'],
                    ],
                    'WHERE `id` IN ?',
                    explode(',', $SESS->vars['modtids']),
                );
                $this->cancel();
                $fids[] = $JAX->p['id'];
                foreach ($fids as $v) {
                    $DB->fixForumLastPost($v);
                }
                $PAGE->location('?act=vf' . $JAX->p['id']);

                break;

            case 'pin':
                $DB->safeupdate(
                    'topics',
                    [
                        'pinned' => 1,
                    ],
                    'WHERE `id` IN ?',
                    explode(',', $SESS->vars['modtids']),
                );
                $PAGE->JS(
                    'alert',
                    'topics pinned!',
                );
                $this->cancel();

                break;

            case 'unpin':
                $DB->safeupdate(
                    'topics',
                    [
                        'pinned' => 0,
                    ],
                    'WHERE `id` IN ?',
                    explode(',', $SESS->vars['modtids']),
                );
                $PAGE->JS(
                    'alert',
                    'topics unpinned!',
                );
                $this->cancel();

                break;

            case 'lock':
                $DB->safeupdate(
                    'topics',
                    [
                        'locked' => 1,
                    ],
                    'WHERE `id` IN ?',
                    explode(',', $SESS->vars['modtids']),
                );
                $PAGE->JS(
                    'alert',
                    'topics locked!',
                );
                $this->cancel();

                break;

            case 'unlock':
                $DB->safeupdate(
                    'topics',
                    [
                        'locked' => 0,
                    ],
                    'WHERE `id` IN ?',
                    explode(',', $SESS->vars['modtids']),
                );
                $PAGE->JS('alert', 'topics unlocked!');
                $this->cancel();

                break;

            case 'delete':
                $this->deletetopics();
                $this->cancel();

                break;

            case 'merge':
                $this->mergetopics();

                break;
        }
    }

    public function doposts($do): void
    {
        global $PAGE,$JAX,$SESS,$DB;

        switch ($do) {
            case 'move':
                $PAGE->JS('modcontrols_move', 1);

                break;

            case 'moveto':
                $DB->safeupdate(
                    'posts',
                    [
                        'tid' => $JAX->p['id'],
                    ],
                    'WHERE `id` IN ?',
                    explode(',', $SESS->vars['modpids']),
                );
                $this->cancel();
                $PAGE->location('?act=vt' . $JAX->p['id']);

                break;

            case 'delete':
                $this->deleteposts();
                $this->cancel();

                break;
        }
    }

    public function cancel(): void
    {
        global $SESS,$PAGE;
        $SESS->delvar('modpids');
        $SESS->delvar('modtids');
        $this->sync();
        $PAGE->JS('modcontrols_clearbox');
    }

    public function modpost($pid)
    {
        global $PAGE,$SESS,$DB,$USER;
        if (!is_numeric($pid)) {
            return;
        }

        $pid = (int) $pid;

        $result = $DB->safeselect(
            <<<'EOT'
                `id`,`auth_id`,`post`,UNIX_TIMESTAMP(`date`) AS `date`,`showsig`,`showemotes`,
                `tid`,`newtopic`,INET6_NTOA(`ip`) AS `ip`,
                UNIX_TIMESTAMP(`edit_date`) AS `edit_date`,`editby`,`rating`
                EOT
            ,
            'posts',
            'WHERE id=?',
            $DB->basicvalue($pid),
        );
        $postdata = $DB->arow($result);
        $DB->disposeresult($result);

        if (!$postdata) {
            return;
        }
        if ($postdata['newtopic']) {
            return $this->modtopic($postdata['tid']);
        }

        $PAGE->JS('softurl');

        // See if they have permission to manipulate this post.
        if (!$this->perms['can_moderate']) {
            $result = $DB->safespecial(
                <<<'EOT'
                    SELECT `mods`
                    FROM %t
                    WHERE `id`=(
                        SELECT `fid`
                        FROM %t
                        WHERE `id`=?
                    )
                    EOT
                ,
                ['forums', 'topics'],
                $postdata['tid'],
            );

            $mods = $DB->arow($result);
            $DB->disposeresult($result);

            if (!$mods) {
                return;
            }
            $mods = explode(',', $mods['mods']);
            if (!in_array($USER['id'], $mods)) {
                return $PAGE->JS(
                    'error',
                    "You don't have permission to be moderating in this forum",
                );
            }
        }
        $currentPids = isset($SESS->vars['modpids'])
            ? explode(',', $SESS->vars['modpids']) : [];
        $pids = [];
        foreach ($currentPids as $currentPid) {
            if (is_numeric($currentPid)) {
                $pids[] = (int) $currentPid;
            }
        }
        if (in_array($pid, $pids, true)) {
            $pids = array_diff($pids, [$pid]);
        } else {
            $pids[] = $pid;
        }
        $SESS->addvar('modpids', implode(',', $pids));

        $this->sync();
    }

    public function modtopic($tid)
    {
        global $PAGE,$SESS,$DB,$USER,$PERMS;
        $PAGE->JS('softurl');
        if (!is_numeric($tid)) {
            return;
        }
        $tid = (int) $tid;
        if (!$PERMS['can_moderate']) {
            $result = $DB->safespecial(
                <<<'EOT'
                    SELECT `mods`
                    FROM %t
                    WHERE `id`=(
                        SELECT `fid`
                        FROM %t
                        WHERE `id`=?
                    )
                    EOT
                ,
                ['forums', 'topics'],
                $DB->basicvalue($tid),
            );
            $mods = $DB->arow($result);
            $DB->disposeresult($result);

            if (!$mods) {
                return $PAGE->JS('error', $DB->error());
            }
            $mods = explode(',', $mods['mods']);
            if (!in_array($USER['id'], $mods)) {
                return $PAGE->JS(
                    'error',
                    "You don't have permission to be moderating in this forum",
                );
            }
        }
        $currentTids = isset($SESS->vars['modtids'])
            ? explode(',', $SESS->vars['modtids']) : [];
        $tids = [];
        foreach ($currentTids as $currentTid) {
            if (is_numeric($currentTid)) {
                $tids[] = (int) $currentTid;
            }
        }
        if (in_array($tid, $tids, true)) {
            $tids = array_diff($tids, [$tid]);
        } else {
            $tids[] = $tid;
        }
        $SESS->addvar('modtids', implode(',', $tids));

        $this->sync();
    }

    public function sync(): void
    {
        global $SESS,$PAGE;
        $PAGE->JS(
            'modcontrols_postsync',
            $SESS->vars['modpids'] ?? '',
            $SESS->vars['modtids'] ?? '',
        );
    }

    public function deleteposts()
    {
        global $SESS,$PAGE,$DB,$USER;
        if (!isset($SESS->vars['modpids']) || !$SESS->vars['modpids']) {
            return $PAGE->JS('error', 'No posts to delete.');
        }

        // Get trashcan.
        $result = $DB->safeselect(
            '`id`',
            'forums',
            'WHERE `trashcan`=1 LIMIT 1',
        );
        $trashcan = $DB->arow($result);
        $trashcan = isset($trashcan['id']) ? (int) $trashcan['id'] : 0;
        $DB->disposeresult($result);

        $result = $DB->safeselect(
            '`tid`',
            'posts',
            'WHERE `id` IN ?',
            explode(',', $SESS->vars['modpids']),
        );

        // Build list of topic ids that the posts were in.
        $tids = [];
        $pids = explode(',', $SESS->vars['modpids']);
        while ($f = $DB->arow($result)) {
            $tids[] = (int) $f['tid'];
        }
        $tids = array_unique($tids);

        if ($trashcan) {
            // Get first & last post.
            foreach ($pids as $v) {
                if (!isset($op) || !$op || $v < $op) {
                    $op = $v;
                }
                if (!isset($lp) || !$lp || $v > $lp) {
                    $lp = $v;
                }
            }
            $result = $DB->safeselect(
                '`auth_id`',
                'posts',
                'WHERE `id`=?',
                $DB->basicvalue($lp),
            );
            $lp = $DB->arow($result);
            $DB->disposeresult($result);

            // Create a new topic.
            $DB->safeinsert(
                'topics',
                [
                    'title' => 'Posts deleted from: '
                        . implode(',', $tids),
                    'op' => $op,
                    'auth_id' => $USER['id'],
                    'fid' => $trashcan,
                    'lp_date' => date('Y-m-d H:i:s', time()),
                    'lp_uid' => $lp['auth_id'],
                    'replies' => 0,
                ],
            );
            $tid = $DB->insert_id(1);
            $DB->safeupdate(
                'posts',
                [
                    'tid' => $tid,
                    'newtopic' => 0,
                ],
                'WHERE `id` IN ?',
                explode(',', $SESS->vars['modpids']),
            );
            $DB->safeupdate(
                'posts',
                [
                    'newtopic' => 1,
                ],
                'WHERE `id`=?',
                $DB->basicvalue($op),
            );
            $tids[] = $tid;
        } else {
            $DB->safedelete(
                'posts',
                'WHERE `id` IN ?',
                explode(',', $SESS->vars['modpids']),
            );
        }
        foreach ($tids as $tid) {
            // Recount replies.
            $DB->safespecial(
                <<<'EOT'
                    UPDATE %t
                    SET `replies`=(
                        SELECT COUNT(`id`)
                        FROM %t
                        WHERE `tid`=?
                    )-1
                    WHERE `id`=?
                    EOT
                ,
                ['topics', 'posts'],
                $tid,
                $tid,
            );
        }
        // Fix forum last post for all forums topics were in.
        $fids = [];
        // Add trashcan here too just in case.
        if ($trashcan) {
            $fids[] = $trashcan;
        }
        $result = $DB->safeselect(
            '`fid`',
            'topics',
            'WHERE `id` IN ?',
            $tids,
        );
        while ($f = $DB->arow($result)) {
            if (is_numeric($f['fid']) && $f['fid'] > 0) {
                $fids[] = (int) $f['fid'];
            }
        }
        $DB->disposeresult($result);
        $fids = array_unique($fids);
        foreach ($fids as $fid) {
            $DB->fixForumLastPost($fid);
        }
        // Remove them from the page.
        foreach ($pids as $v) {
            $PAGE->JS('removeel', '#pid_' . $v);
        }
    }

    public function deletetopics()
    {
        global $SESS,$DB,$PAGE;
        if (!$SESS->vars['modtids']) {
            return $PAGE->JS('error', 'No topics to delete');
        }
        $data = [];

        // Get trashcan id.
        $result = $DB->safeselect(
            '`id`',
            'forums',
            'WHERE `trashcan`=1 LIMIT 1',
        );
        $trashcan = $DB->arow($result);
        $DB->disposeresult($result);

        $trashcan = $trashcan['id'] ?? false;
        $result = $DB->safeselect(
            '`fid`,`id`',
            'topics',
            'WHERE `id` IN ?',
            explode(',', $SESS->vars['modtids']),
        );
        $delete = [];
        while ($f = $DB->arow($result)) {
            if (!isset($data[$f['fid']])) {
                $data[$f['fid']] = 0;
            }
            ++$data[$f['fid']];
            if ($trashcan && $trashcan == $f['fid']) {
                $delete[] = $f['id'];
            }
        }
        if ($trashcan) {
            $DB->safeupdate(
                'topics',
                [
                    'fid' => $trashcan,
                ],
                'WHERE `id` IN ?',
                explode(',', $SESS->vars['modtids']),
            );
            $delete = implode(',', $delete);
            $data[$trashcan] = 1;
        } else {
            $delete = $SESS->vars['modtids'];
        }
        if (!empty($delete)) {
            $DB->safedelete(
                'posts',
                'WHERE `tid` IN ?',
                explode(',', $delete),
            );
            $DB->safedelete(
                'topics',
                'WHERE `id` IN ?',
                explode(',', $delete),
            );
        }
        foreach ($data as $k => $v) {
            $DB->fixForumLastPost($k);
        }
        $SESS->delvar('modtids');
        $PAGE->JS('modcontrols_clearbox');
        $PAGE->JS('alert', 'topics deleted!');
    }

    public function mergetopics(): void
    {
        global $SESS,$DB,$PAGE,$JAX;
        $page = '';
        $exploded = isset($SESS->vars['modtids'])
            ? explode(',', $SESS->vars['modtids']) : [];
        if (
            isset($JAX->p['ot'])
            && is_numeric($JAX->p['ot'])
            && in_array($JAX->p['ot'], $exploded)
        ) {
            // Move the posts and set all posts to normal (newtopic=0).
            $DB->safeupdate(
                'posts',
                [
                    'tid' => $JAX->p['ot'],
                    'newtopic' => '0',
                ],
                'WHERE `tid` IN ?',
                explode(',', $SESS->vars['modtids']),
            );

            // Make the first post in the topic have newtopic=1.
            // Get the op.
            $result = $DB->safeselect(
                'MIN(`id`)',
                'posts',
                'WHERE `tid`=?',
                $DB->basicvalue($JAX->p['ot']),
            );
            $thisrow = $DB->arow($result);
            $op = array_pop($thisrow);
            $DB->disposeresult($result);

            $DB->safeupdate(
                'posts',
                [
                    'newtopic' => 1,
                ],
                'WHERE `id`=?',
                $op,
            );

            // Also fix op.
            $DB->safeupdate(
                'topics',
                [
                    'op' => $op,
                ],
                'WHERE `id`=?',
                $DB->basicvalue($JAX->p['ot']),
            );
            unset($exploded[array_search($JAX->p['ot'], $exploded)]);
            if (!empty($exploded)) {
                $DB->safedelete(
                    'topics',
                    'WHERE `id` IN ?',
                    $exploded,
                );
            }
            $this->cancel();
            $PAGE->location('?act=vt' . $JAX->p['ot']);
        }
        $page .= '<form method="post" data-ajax-form="true" '
            . 'style="padding:10px;">'
            . 'Which topic should the topics be merged into?<br />';
        $page .= $JAX->hiddenFormFields(
            [
                'act' => 'modcontrols',
                'dot' => 'merge',
            ],
        );

        if (isset($SESS->vars['modtids'])) {
            $result = $DB->safeselect(
                '`id`,`title`',
                'topics',
                'WHERE `id` IN ?',
                explode(',', $SESS->vars['modtids']),
            );
            $titles = [];
            while ($f = $DB->arow($result)) {
                $titles[$f['id']] = $f['title'];
            }
            foreach ($exploded as $v) {
                if (isset($titles[$v])) {
                    $page .= '<input type="radio" name="ot" value="' . $v . '" /> '
                        . $titles[$v] . '<br />';
                }
            }
        }
        $page .= '<input type="submit" value="Merge" /></form>';
        $page = $PAGE->collapsebox('Merging Topics', $page);
        $PAGE->JS('update', 'page', $page);
        $PAGE->append('page', $page);
    }

    public function banposts(): void
    {
        global $PAGE;
        $PAGE->JS('alert', 'under construction');
    }

    public static function load(): void
    {
        global $PAGE;
        $script = file_get_contents('dist/modcontrols.js');
        if ($PAGE && $PAGE->jsaccess) {
            $PAGE->JS('softurl');
            $PAGE->JS('script', $script);
        } else {
            header('Content-Type: application/javascript; charset=utf-8');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT');

            echo $script;

            exit(0);
        }
    }

    public function showmodcp($cppage = ''): void
    {
        global $PAGE,$PERMS;
        if (!$PERMS['can_moderate']) {
            return;
        }
        $page = $PAGE->meta('modcp-index', $cppage);
        $page = $PAGE->meta('box', ' id="modcp"', 'Mod CP', $page);
        $PAGE->append('page', $page);
        $PAGE->JS('update', 'page', $page);
    }

    public function editmembers()
    {
        global $PAGE,$JAX,$DB,$USER,$PERMS;
        if (!$PERMS['can_moderate']) {
            return;
        }
        $e = '';
        $data = [];
        $page = '<form method="post" data-ajax-form="true">'
            . $JAX->hiddenFormFields(
                [
                    'submit' => 'showform',
                    'act' => 'modcontrols',
                    'do' => 'emem',
                ],
            )
            . 'Member name: <input type="text" title="Enter member name" name="mname" '
            . 'data-autocomplete-action="searchmembers" '
            . 'data-autocomplete-output="#mid" '
            . 'data-autocomplete-indicator="#validname" />'
            . '<span id="validname"></span>
            <input type="hidden" name="mid" id="mid" onchange="this.form.onsubmit();" />
            <input type="submit" type="View member details" value="Go" />
            </form>';
        if (isset($JAX->p['submit']) && $JAX->p['submit'] == 'save') {
            if (!trim($JAX->p['display_name'])) {
                $page .= $PAGE->meta('error', 'Display name is invalid.');
            } else {
                $DB->safeupdate(
                    'members',
                    [
                        'sig' => $JAX->p['signature'],
                        'display_name' => $JAX->p['display_name'],
                        'full_name' => $JAX->p['full_name'],
                        'about' => $JAX->p['about'],
                        'avatar' => $JAX->p['avatar'],
                    ],
                    'WHERE `id`=?',
                    $DB->basicvalue($JAX->p['mid']),
                );
                $error = $DB->error();
                if ($error) {
                    $page .= $PAGE->meta(
                        'error',
                        'Error updating profile information.',
                    );
                } else {
                    $page .= $PAGE->meta('success', 'Profile information saved.');
                }
            }
        }
        if (
            (isset($JAX->p['submit'])
            && $JAX->p['submit'] == 'showform')
            || isset($JAX->b['mid'])
        ) {
            // Get the member data.
            if (is_numeric($JAX->b['mid'])) {
                $result = $DB->safeselect(
                    <<<'EOT'
                        `id`,`name`,`pass`,`email`,`sig`,`posts`,`group_id`,`avatar`,`usertitle`,
                        UNIX_TIMESTAMP(`join_date`) AS `join_date`,
                        UNIX_TIMESTAMP(`last_visit`) AS `last_visit`,`contact_skype`,`contact_yim`,
                        `contact_msn`,`contact_gtalk`,`contact_aim`,`website`,`birthdate`,
                        DAY(`birthdate`) AS `dob_day`,MONTH(`birthdate`) AS `dob_month`,
                        YEAR(`birthdate`) AS `dob_year`,`about`,`display_name`,`full_name`,
                        `contact_steam`,`location`,`gender`,`friends`,`enemies`,`sound_shout`,
                        `sound_im`,`sound_pm`,`sound_postinmytopic`,`sound_postinsubscribedtopic`,
                        `notify_pm`,`notify_postinmytopic`,`notify_postinsubscribedtopic`,`ucpnotepad`,
                        `skin_id`,`contact_twitter`,`contact_discord`,`contact_youtube`,`contact_bluesky`,
                        `email_settings`,`nowordfilter`,
                        INET6_NTOA(`ip`) AS `ip`,`mod`,`wysiwyg`
                        EOT
                    ,
                    'members',
                    'WHERE `id`=?',
                    $DB->basicvalue($JAX->b['mid']),
                );
                $data = $DB->arow($result);
                $DB->disposeresult($result);
            } elseif ($JAX->p['mname']) {
                $result = $DB->safeselect(
                    <<<'EOT'
                        `id`,`name`,`pass`,`email`,`sig`,`posts`,`group_id`,`avatar`,`usertitle`,
                        UNIX_TIMESTAMP(`join_date`) AS `join_date`,
                        UNIX_TIMESTAMP(`last_visit`) AS `last_visit`,`contact_skype`,`contact_yim`,
                        `contact_msn`,`contact_gtalk`,`contact_aim`,`website`,`birthdate`,
                        DAY(`birthdate`) AS `dob_day`,MONTH(`birthdate`) AS `dob_month`,
                        YEAR(`birthdate`) AS `dob_year`,`about`,`display_name`,`full_name`,
                        `contact_steam`,`location`,`gender`,`friends`,`enemies`,`sound_shout`,
                        `sound_im`,`sound_pm`,`sound_postinmytopic`,`sound_postinsubscribedtopic`,
                        `notify_pm`,`notify_postinmytopic`,`notify_postinsubscribedtopic`,`ucpnotepad`,
                        `skin_id`,`contact_twitter`,`contact_discord`,`contact_youtube`,`contact_bluesky`,
                        `email_settings`,`nowordfilter`,
                        INET6_NTOA(`ip`) AS `ip`,`mod`,`wysiwyg`
                        EOT
                    ,
                    'members',
                    'WHERE `display_name` LIKE ?',
                    $DB->basicvalue($JAX->p['mname'] . '%'),
                );
                $data = [];
                while ($f = $DB->arow($result)) {
                    $data[] = $f;
                }
                if (count($data) > 1) {
                    $e = 'Many users found!';
                } else {
                    $data = array_shift($data);
                }
            } else {
                $e = 'Member name is a required field.';
            }

            if (!$data) {
                $e = 'No members found that matched the criteria.';
            }
            if (
                (isset($data['can_moderate'])
                && $data['can_moderate'])
                && $USER['group_id'] != 2
                || $data['group_id'] == 2
                && ($USER['id'] != 1
                && $data['id'] != $USER['id'])
            ) {
                $e = 'You do not have permission to edit this profile.';
            }

            if ($e) {
                $page .= $PAGE->meta('error', $e);
            } else {
                function field($label, $name, $value, $type = 'input')
                {
                    return '<tr><td><label for="m_' . $name . '">' . $label
                        . '</label></td><td>'
                        . ($type == 'textarea' ? '<textarea name="' . $name
                        . '" id="m_' . $name . '">' . $value . '</textarea>'
                        : '<input type="text" id="m_' . $name . '" name="' . $name
                        . '" value="' . $value . '" />') . '</td></tr>';
                }
                $page .= '<form method="post" '
                    . 'data-ajax-form="true"><table>';
                $page .= $JAX->hiddenFormFields(
                    [
                        'act' => 'modcontrols',
                        'do' => 'emem',
                        'mid' => $data['id'],
                        'submit' => 'save',
                    ],
                );
                $page .= field(
                    'Display Name',
                    'display_name',
                    $data['display_name'],
                )
                    . field('Avatar', 'avatar', $data['avatar'])
                    . field('Full Name', 'full_name', $data['full_name'])
                    . field(
                        'About',
                        'about',
                        $JAX->blockhtml($data['about']),
                        'textarea',
                    )
                    . field(
                        'Signature',
                        'signature',
                        $JAX->blockhtml($data['sig']),
                        'textarea',
                    );
                $page .= '</table><input type="submit" value="Save" /></form>';
            }
        }

        $this->showmodcp($page);
    }

    public function iptools(): void
    {
        global $PAGE,$DB,$CFG,$JAX,$USER;
        $page = '';

        $ip = $JAX->b['ip'] ?? '';
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '';
        }

        $changed = false;

        if (isset($JAX->p['ban']) && $JAX->p['ban']) {
            if (!$JAX->ipbanned($ip)) {
                $changed = true;
                $JAX->ipbancache[] = $ip;
            }
        } elseif (isset($JAX->p['unban']) && $JAX->p['unban']) {
            if ($entry = $JAX->ipbanned($ip)) {
                $changed = true;
                unset($JAX->ipbancache[array_search($entry, $JAX->ipbancache)]);
            }
        }
        if ($changed) {
            $o = fopen(BOARDPATH . '/bannedips.txt', 'w');
            fwrite($o, implode(PHP_EOL, $JAX->ipbancache));
            fclose($o);
        }

        $hiddenFields = $JAX->hiddenFormFields(
            [
                'act' => 'modcontrols',
                'do' => 'iptools',
            ],
        );
        $form = <<<EOT
            <form method='post' data-ajax-form='true'>
                {$hiddenFields}
                <label>IP:
                <input type='text' name='ip' title="Enter IP address" value='{$ip}' /></label>
                <input type='submit' value='Submit' title="Search for IP" />
            </form>
            EOT;
        if ($ip) {
            $page .= "<h3>Data for {$ip}:</h3>";

            $hiddenFields = $JAX->hiddenFormFields(
                [
                    'ip' => $ip,
                    'act' => 'modcontrols',
                    'do' => 'iptools',
                ],
            );
            if ($JAX->ipbanned($ip)) {
                $banCode = <<<'EOT'
                    <span style="color:#900">
                        banned
                    </span>
                    <input type="submit" name="unban"
                        onclick="this.form.submitButton=this" value="Unban" />
                    EOT;
            } else {
                $banCode = <<<'EOT'
                    <span style="color:#090">
                        not banned
                    </span>
                    <input type="submit" name="ban"
                        onclick="this.form.submitButton=this" value="Ban" />
                    EOT;
            }
            $torDate = date('Y-m-d', strtotime('-2 days'));
            $page .= $this->box(
                'Info',
                <<<EOT
                    <form method='post' data-ajax-form='true'>
                        {$hiddenFields}
                        IP ban status: {$banCode}<br />
                    </form>
                    IP Lookup Services: <ul>
                        <li><a href="https://whois.domaintools.com/{$ip}">DomainTools Whois</a></li>
                        <li><a href="https://www.domaintools.com/research/traceroute/?query={$ip}">
                            DomainTools Traceroute
                        </a></li>
                        <li><a href="https://www.ip2location.com/{$ip}">IP2Location Lookup</a></li>
                        <li><a href="https://www.dan.me.uk/torcheck?ip={$ip}">IP2Location Lookup</a></li>
                        <li><a href="https://metrics.torproject.org/exonerator.html?ip={$ip}&timestamp={$torDate}">
                            ExoneraTor Lookup
                        </a></li>
                        <li><a href="https://www.projecthoneypot.org/ip_{$ip}">Project Honeypot Lookup</a></li>
                        <li><a href="https://www.stopforumspam.com/ipcheck/{$ip}">StopForumSpam Lookup</a></li>
                    </ul>
                    EOT,
            );

            $content = [];
            $result = $DB->safeselect(
                '`group_id`,`display_name`,`id`',
                'members',
                'WHERE `ip`=INET6_ATON(?)',
                $DB->basicvalue($ip),
            );
            while ($f = $DB->arow($result)) {
                $content[] = $PAGE->meta(
                    'user-link',
                    $f['id'],
                    $f['group_id'],
                    $f['display_name'],
                );
            }
            $page .= $this->box('Users with this IP:', implode(', ', $content));

            if ($CFG['shoutbox']) {
                $content = '';
                $result = $DB->safespecial(
                    <<<'EOT'
                        SELECT s.`id` AS `id`,s.`uid` AS `uid`,s.`shout` AS `shout`,
                        UNIX_TIMESTAMP(s.`date`) AS `date`,INET6_NTOA(s.`ip`) AS `ip`,
                        m.`group_id` AS `group_id`, m.`display_name` AS `display_name`
                        FROM %t s
                        LEFT JOIN %t m
                            ON m.`id`=s.`uid`
                        WHERE s.`ip`=INET6_ATON(?)
                        ORDER BY `id`
                        DESC LIMIT 5
                        EOT
                    ,
                    [
                        'shouts',
                        'members',
                    ],
                    $DB->basicvalue($ip),
                );
                while ($f = $DB->arow($result)) {
                    $content .= $PAGE->meta(
                        'user-link',
                        $f['uid'],
                        $f['group_id'],
                        $f['display_name'],
                    );
                    $content .= ' : ' . $f['shout'] . '<br />';
                }
                $page .= $this->box('Last 5 shouts:', $content);
            }
            $content = '';
            $result = $DB->safeselect(
                '`post`',
                'posts',
                'WHERE `ip`=INET6_ATON(?) ORDER BY `id` DESC LIMIT 5',
                $DB->basicvalue($ip),
            );
            while ($f = $DB->arow($result)) {
                $content .= "<div class='post'>"
                    . nl2br($JAX->blockhtml($JAX->textonly($f['post'])))
                    . '</div>';
            }
            $page .= $this->box('Last 5 posts:', $content);
        }
        $this->showmodcp($form . $page);
    }
}
