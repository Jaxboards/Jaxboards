<?php

$PAGE->loadmeta('modcp');

new modcontrols();
final class modcontrols
{
    public static function load(): void
    {
        global $PAGE;
        $script = file_get_contents('dist/modcontrols.js');
        if (!$PAGE || !$PAGE->jsaccess) {
            header('Content-Type: application/javascript; charset=utf-8');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2_592_000) . ' GMT');

            echo $script;

            exit(0);
        }

        $PAGE->JS('softurl');
        $PAGE->JS('script', $script);
    }

    public $perms;

    public function __construct()
    {
        global $JAX,$PAGE,$USER;

        $this->perms = $JAX->getPerms();
        if (!$this->perms['can_moderate'] && !$USER['mod']) {
            $PAGE->JS('softurl');
            $PAGE->JS(
                'alert',
                'Your account does not have moderator permissions.',
            );

            return;
        }

        if (isset($JAX->b['cancel']) && $JAX->b['cancel']) {
            $this->cancel();

            return;
        }

        if ($PAGE->jsupdate && empty($JAX->p)) {
            return;
        }

        if (isset($JAX->p['dot']) && $JAX->p['dot']) {
            $this->dotopics($JAX->p['dot']);

            return;
        }

        if (isset($JAX->p['dop']) && $JAX->p['dop']) {
            $this->doposts($JAX->p['dop']);

            return;
        }

        switch ($JAX->b['do']) {
            case 'modp':
                $this->modpost($JAX->b['pid']);

                break;

            case 'modt':
                $this->modtopic($JAX->b['tid']);

                break;

            case 'load':
                self::load();

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
            default:
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
                    [
                        'cat_id',
                        'id',
                        'lp_tid',
                        'lp_topic',
                        'lp_uid',
                        'mods',
                        'nocount',
                        '`order`',
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
                    $DB->basicvalue($JAX->p['id']),
                );
                $rowfound = $DB->arow($result);
                $DB->disposeresult($result);
                if (!is_numeric($JAX->p['id']) || !$rowfound) {
                    return;
                }

                $result = $DB->safeselect(
                    ['fid'],
                    'topics',
                    'WHERE `id` IN ?',
                    explode(',', (string) $SESS->vars['modtids']),
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
                    explode(',', (string) $SESS->vars['modtids']),
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
                    explode(',', (string) $SESS->vars['modtids']),
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
                    explode(',', (string) $SESS->vars['modtids']),
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
                    explode(',', (string) $SESS->vars['modtids']),
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
                    explode(',', (string) $SESS->vars['modtids']),
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
            default:
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
                    explode(',', (string) $SESS->vars['modpids']),
                );
                $this->cancel();
                $PAGE->location('?act=vt' . $JAX->p['id']);

                break;

            case 'delete':
                $this->deleteposts();
                $this->cancel();

                break;
            default:
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
            return null;
        }

        $pid = (int) $pid;

        $result = $DB->safeselect(
            [
                'auth_id',
                'editby',
                'id',
                'newtopic',
                'post',
                'rating',
                'showemotes',
                'showsig',
                'tid',
                'INET6_NTOA(`ip`) AS `ip`',
                'UNIX_TIMESTAMP(`date`) AS `date`',
                'UNIX_TIMESTAMP(`edit_date`) AS `edit_date`',
            ],
            'posts',
            'WHERE id=?',
            $DB->basicvalue($pid),
        );
        $postdata = $DB->arow($result);
        $DB->disposeresult($result);

        if (!$postdata) {
            return null;
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
                return null;
            }

            $mods = explode(',', (string) $mods['mods']);
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
            if (!is_numeric($currentPid)) {
                continue;
            }

            $pids[] = (int) $currentPid;
        }

        if (in_array($pid, $pids, true)) {
            $pids = array_diff($pids, [$pid]);
        } else {
            $pids[] = $pid;
        }

        $SESS->addvar('modpids', implode(',', $pids));

        $this->sync();

        return null;
    }

    public function modtopic($tid)
    {
        global $PAGE,$SESS,$DB,$USER,$PERMS;
        $PAGE->JS('softurl');
        if (!is_numeric($tid)) {
            return null;
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

            $mods = explode(',', (string) $mods['mods']);
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
            if (!is_numeric($currentTid)) {
                continue;
            }

            $tids[] = (int) $currentTid;
        }

        if (in_array($tid, $tids, true)) {
            $tids = array_diff($tids, [$tid]);
        } else {
            $tids[] = $tid;
        }

        $SESS->addvar('modtids', implode(',', $tids));

        $this->sync();

        return null;
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
            explode(',', (string) $SESS->vars['modpids']),
        );

        // Build list of topic ids that the posts were in.
        $tids = [];
        $pids = explode(',', (string) $SESS->vars['modpids']);
        while ($f = $DB->arow($result)) {
            $tids[] = (int) $f['tid'];
        }

        $tids = array_unique($tids);

        if ($trashcan !== 0) {
            // Get first & last post.
            foreach ($pids as $v) {
                if (!isset($op) || !$op || $v < $op) {
                    $op = $v;
                }

                if (isset($lp) && $lp && $v <= $lp) {
                    continue;
                }

                $lp = $v;
            }

            $result = $DB->safeselect(
                ['auth_id'],
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
                    'auth_id' => $USER['id'],
                    'fid' => $trashcan,
                    'lp_date' => date('Y-m-d H:i:s', time()),
                    'lp_uid' => $lp['auth_id'],
                    'op' => $op,
                    'replies' => 0,
                    'title' => 'Posts deleted from: '
                        . implode(',', $tids),
                ],
            );
            $tid = $DB->insert_id(1);
            $DB->safeupdate(
                'posts',
                [
                    'newtopic' => 0,
                    'tid' => $tid,
                ],
                'WHERE `id` IN ?',
                explode(',', (string) $SESS->vars['modpids']),
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
                explode(',', (string) $SESS->vars['modpids']),
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
        if ($trashcan !== 0) {
            $fids[] = $trashcan;
        }

        $result = $DB->safeselect(
            ['fid'],
            'topics',
            'WHERE `id` IN ?',
            $tids,
        );
        while ($f = $DB->arow($result)) {
            if (!is_numeric($f['fid'])) {
                continue;
            }

            if ($f['fid'] <= 0) {
                continue;
            }

            $fids[] = (int) $f['fid'];
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

        return null;
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
            ['id'],
            'forums',
            'WHERE `trashcan`=1 LIMIT 1',
        );
        $trashcan = $DB->arow($result);
        $DB->disposeresult($result);

        $trashcan = $trashcan['id'] ?? false;
        $result = $DB->safeselect(
            ['id', 'fid'],
            'topics',
            'WHERE `id` IN ?',
            explode(',', (string) $SESS->vars['modtids']),
        );
        $delete = [];
        while ($f = $DB->arow($result)) {
            if (!isset($data[$f['fid']])) {
                $data[$f['fid']] = 0;
            }

            ++$data[$f['fid']];
            if (!$trashcan) {
                continue;
            }

            if ($trashcan !== $f['fid']) {
                continue;
            }

            $delete[] = $f['id'];
        }

        if ($trashcan) {
            $DB->safeupdate(
                'topics',
                [
                    'fid' => $trashcan,
                ],
                'WHERE `id` IN ?',
                explode(',', (string) $SESS->vars['modtids']),
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
                explode(',', (string) $delete),
            );
            $DB->safedelete(
                'topics',
                'WHERE `id` IN ?',
                explode(',', (string) $delete),
            );
        }

        foreach (array_keys($data) as $k) {
            $DB->fixForumLastPost($k);
        }

        $SESS->delvar('modtids');
        $PAGE->JS('modcontrols_clearbox');
        $PAGE->JS('alert', 'topics deleted!');

        return null;
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
                    'newtopic' => '0',
                    'tid' => $JAX->p['ot'],
                ],
                'WHERE `tid` IN ?',
                explode(',', (string) $SESS->vars['modtids']),
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
            unset($exploded[array_search($JAX->p['ot'], $exploded, true)]);
            if ($exploded !== []) {
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
                ['id', 'title'],
                'topics',
                'WHERE `id` IN ?',
                explode(',', $SESS->vars['modtids']),
            );
            $titles = [];
            while ($f = $DB->arow($result)) {
                $titles[$f['id']] = $f['title'];
            }

            foreach ($exploded as $v) {
                if (!isset($titles[$v])) {
                    continue;
                }

                $page .= '<input type="radio" name="ot" value="' . $v . '" /> '
                    . $titles[$v] . '<br />';
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

    public function editmembers(): void
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
                    'act' => 'modcontrols',
                    'do' => 'emem',
                    'submit' => 'showform',
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
        if (isset($JAX->p['submit']) && $JAX->p['submit'] === 'save') {
            if (
                trim((string) $JAX->p['display_name']) === ''
                || trim((string) $JAX->p['display_name']) === '0'
            ) {
                $page .= $PAGE->meta('error', 'Display name is invalid.');
            } else {
                $DB->safeupdate(
                    'members',
                    [
                        'about' => $JAX->p['about'],
                        'avatar' => $JAX->p['avatar'],
                        'display_name' => $JAX->p['display_name'],
                        'full_name' => $JAX->p['full_name'],
                        'sig' => $JAX->p['signature'],
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
            && $JAX->p['submit'] === 'showform')
            || isset($JAX->b['mid'])
        ) {
            // Get the member data.
            if (is_numeric($JAX->b['mid'])) {
                $result = $DB->safeselect(
                    [
                        'about',
                        'avatar',
                        'birthdate',
                        'contact_aim',
                        'contact_bluesky',
                        'contact_discord',
                        'contact_gtalk',
                        'contact_msn',
                        'contact_skype',
                        'contact_steam',
                        'contact_twitter',
                        'contact_yim',
                        'contact_youtube',
                        'display_name',
                        'email_settings',
                        'email',
                        'enemies',
                        'friends',
                        'full_name',
                        'gender',
                        'group_id',
                        'id',
                        'location',
                        '`mod`',
                        'name',
                        'notify_pm',
                        'notify_postinmytopic',
                        'notify_postinsubscribedtopic',
                        'nowordfilter',
                        'pass',
                        'posts',
                        'sig',
                        'skin_id',
                        'sound_im',
                        'sound_pm',
                        'sound_postinmytopic',
                        'sound_postinsubscribedtopic',
                        'sound_shout',
                        'ucpnotepad',
                        'usertitle',
                        'website',
                        'wysiwyg',
                        'DAY(`birthdate`) AS `dob_day`',
                        'INET6_NTOA(`ip`) AS `ip`',
                        'MONTH(`birthdate`) AS `dob_month`',
                        'UNIX_TIMESTAMP(`join_date`) AS `join_date`',
                        'UNIX_TIMESTAMP(`last_visit`) AS `last_visit`',
                        'YEAR(`birthdate`) AS `dob_year`',
                    ],
                    'members',
                    'WHERE `id`=?',
                    $DB->basicvalue($JAX->b['mid']),
                );
                $data = $DB->arow($result);
                $DB->disposeresult($result);
            } elseif ($JAX->p['mname']) {
                $result = $DB->safeselect(
                    [
                        'about',
                        'avatar',
                        'birthdate',
                        'contact_aim',
                        'contact_bluesky',
                        'contact_discord',
                        'contact_gtalk',
                        'contact_msn',
                        'contact_skype',
                        'contact_steam',
                        'contact_twitter',
                        'contact_yim',
                        'contact_youtube',
                        'display_name',
                        'email_settings',
                        'email',
                        'enemies',
                        'friends',
                        'full_name',
                        'gender',
                        'group_id',
                        'id',
                        'location',
                        '`mod`',
                        'name',
                        'notify_pm',
                        'notify_postinmytopic',
                        'notify_postinsubscribedtopic',
                        'nowordfilter',
                        'pass',
                        'posts',
                        'sig',
                        'skin_id',
                        'sound_im',
                        'sound_pm',
                        'sound_postinmytopic',
                        'sound_postinsubscribedtopic',
                        'sound_shout',
                        'ucpnotepad',
                        'usertitle',
                        'website',
                        'wysiwyg',
                        'DAY(`birthdate`) AS `dob_day`',
                        'INET6_NTOA(`ip`) AS `ip`',
                        'MONTH(`birthdate`) AS `dob_month`',
                        'UNIX_TIMESTAMP(`join_date`) AS `join_date`',
                        'UNIX_TIMESTAMP(`last_visit`) AS `last_visit`',
                        'YEAR(`birthdate`) AS `dob_year`',
                    ],
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
                && $USER['group_id'] !== 2
                || $data['group_id'] === 2
                && ($USER['id'] !== 1
                && $data['id'] !== $USER['id'])
            ) {
                $e = 'You do not have permission to edit this profile.';
            }

            if ($e !== '' && $e !== '0') {
                $page .= $PAGE->meta('error', $e);
            } else {
                function field($label, $name, $value, $type = 'input'): string
                {
                    return '<tr><td><label for="m_' . $name . '">' . $label
                        . '</label></td><td>'
                        . ($type === 'textarea' ? '<textarea name="' . $name
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
                unset($JAX->ipbancache[array_search($entry, $JAX->ipbancache, true)]);
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
                    'act' => 'modcontrols',
                    'do' => 'iptools',
                    'ip' => $ip,
                ],
            );
            $banCode = $JAX->ipbanned($ip) ? <<<'EOT'
                <span style="color:#900">
                    banned
                </span>
                <input type="submit" name="unban"
                    onclick="this.form.submitButton=this" value="Unban" />
                EOT : <<<'EOT'
                <span style="color:#090">
                    not banned
                </span>
                <input type="submit" name="ban"
                    onclick="this.form.submitButton=this" value="Ban" />
                EOT;

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
                [
                    'display_name',
                    'group_id',
                    'id',
                ],
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
                ['post'],
                'posts',
                'WHERE `ip`=INET6_ATON(?) ORDER BY `id` DESC LIMIT 5',
                $DB->basicvalue($ip),
            );
            while ($f = $DB->arow($result)) {
                $content .= "<div class='post'>"
                    . nl2br((string) $JAX->blockhtml($JAX->textonly($f['post'])))
                    . '</div>';
            }

            $page .= $this->box('Last 5 posts:', $content);
        }

        $this->showmodcp($form . $page);
    }

    private function box(string $title, string $content): string
    {
        $content = ($content ?: '--No Data--');

        return <<<EOT
            <div class='minibox'>
                <div class='title'>{$title}</div>
                <div class='content'>{$content}</div>
            </div>
            EOT;
    }
}
