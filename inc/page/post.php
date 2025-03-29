<?php

// IMPORTANT TO DO: fix file uploading so that it checks
// permissions within the forum
// I've already hidden the attach files button,
// but it's possible for people to still upload.
$PAGE->metadefs['post-preview'] = $PAGE->meta('box', '', 'Post Preview', '%s');

new POST();
final class POST
{
    public $canmod;
    public $postdata = '';

    public $postpreview = '';

    /**
     * @var false
     */
    public $nopost = true;

    public $tid;

    public $fid;

    public $pid;

    public $how;

    public function __construct()
    {
        global $JAX,$PAGE;
        $this->tid = $JAX->b['tid'] ?? 0;
        $this->fid = $JAX->b['fid'] ?? 0;
        $this->pid = $JAX->b['pid'] ?? 0;
        $this->how = $JAX->b['how'] ?? '';

        if (isset($JAX->p['postdata']) && $JAX->p['postdata']) {
            $this->nopost = false;
            $this->postdata = $JAX->p['postdata'];
        }

        if ($this->postdata) {
            // Linkify stuff before sending it.
            $this->postdata = str_replace("\t", '    ', $this->postdata);
            $codes = $JAX->startcodetags($this->postdata);
            $this->postdata = $JAX->linkify($this->postdata);
            $this->postdata = $JAX->finishcodetags($this->postdata, $codes, true);
            // Poor forum martyr.
            $this->postdata = str_replace('youtube]', 'video]', $this->postdata);
        }

        if (
            isset($_FILES['Filedata'], $_FILES['Filedata']['tmp_name'])
            && $_FILES['Filedata']['tmp_name']
        ) {
            $JAX->p['postdata'] .= '[attachment]'
                . $this->upload($_FILES['Filedata'])
                . '[/attachment]';
        }

        if (
            isset($JAX->p['submit'])
            && ($JAX->p['submit'] === 'Preview'
            || $JAX->p['submit'] === 'Full Reply')
        ) {
            $this->showpostform();
            $this->previewpost();
        } elseif ($this->pid && is_numeric($this->pid)) {
            $this->editpost();
        } elseif (!$this->nopost) {
            $this->submitpost();
        } elseif (
            $this->fid && is_numeric($this->fid)
            || $this->tid && is_numeric($this->tid)
            && $this->how === 'edit'
        ) {
            $this->showtopicform();
        } elseif ($this->tid && is_numeric($this->tid)) {
            $this->showpostform();
        } else {
            $PAGE->location('?');
        }
    }

    public function upload($fileobj, $uid = false): string
    {
        global $CFG,$DB,$USER,$JAX;
        if ($uid === false) {
            $uid = $USER['id'];
        }

        if ($uid === false && !$USER) {
            return 'must be logged in';
        }

        $size = filesize($fileobj['tmp_name']);
        $hash = hash_file('sha512', $fileobj['tmp_name']);
        $uploadpath = BOARDPATH . 'Uploads/';

        $ext = explode('.', (string) $fileobj['name']);
        $ext = count($ext) === 1 ? '' : mb_strtolower(array_pop($ext));

        if (!in_array($ext, $CFG['images'])) {
            $ext = '';
        }

        if ($ext !== '' && $ext !== '0') {
            $ext = '.' . $ext;
        }

        $file = $uploadpath . $hash . $ext;
        if (!is_file($file)) {
            move_uploaded_file($fileobj['tmp_name'], $file);
            $DB->safeinsert(
                'files',
                [
                    'hash' => $hash,
                    'ip' => $JAX->ip2bin(),
                    'name' => $fileobj['name'],
                    'size' => $size,
                    'uid' => $uid,
                ],
            );
            $id = $DB->insert_id(1);
        } else {
            $result = $DB->safeselect(
                '`id`',
                'files',
                'WHERE `hash`=?',
                $hash,
            );
            $thisrow = $DB->arow($result);
            $id = array_pop($thisrow);
            $DB->disposeresult($result);
        }

        return (string) $id;
    }

    public function previewpost(): void
    {
        global $JAX,$PAGE;
        $post = $this->postdata;
        if (trim((string) $post) !== '' && trim((string) $post) !== '0') {
            $post = $JAX->theworks($post);
            $post = $PAGE->meta('post-preview', $post);
            $this->postpreview = $post;
        }

        if (!$PAGE->jsaccess || $this->how === 'qreply') {
            $this->showpostform();
        }

        $PAGE->JS('update', 'post-preview', $post);
    }

    public function showtopicform(): void
    {
        global $JAX,$PAGE,$DB,$PERMS,$USER;
        $e = '';
        if ($PAGE->jsupdate) {
            return;
        }

        $postdata = $this->postdata;
        $page = '<div id="post-preview">' . $this->postpreview . '</div>';
        $fid = $this->fid;

        if ($this->how === 'edit') {
            $result = $DB->safeselect(
                <<<'EOT'
                    `id`,`title`,`subtitle`,`lp_uid`,UNIX_TIMESTAMP(`lp_date`) AS `lp_date`,
                    `fid`,`auth_id`,`replies`,`views`,
                    `pinned`,`poll_choices`,`poll_results`,`poll_q`,`poll_type`,`summary`,
                    `locked`,UNIX_TIMESTAMP(`date`) AS `date`,`op`,`cal_event`
                    EOT
                ,
                'topics',
                'WHERE `id`=?',
                $DB->basicvalue($this->tid),
            );
            $tdata = $DB->arow($result);
            $DB->disposeresult($result);

            if (!$tdata) {
                $e = "The topic you're trying to edit does not exist.";
            } else {
                $result = $DB->safeselect(
                    '`post`',
                    'posts',
                    'WHERE `id`=?',
                    $DB->basicvalue($tdata['op']),
                );
                $postdata = $DB->arow($result);
                $DB->disposeresult($result);

                if ($postdata) {
                    $postdata = $postdata[0];
                }
            }

            $fid = $tdata['fid'];
        }

        $result = $DB->safeselect(
            '`title`,`perms`',
            'forums',
            'WHERE `id`=?',
            $fid,
        );
        $fdata = $DB->arow($result);
        $DB->disposeresult($result);

        $fdata['perms'] = $JAX->parsePerms(
            $fdata['perms'],
            $USER ? $USER['group_id'] : 3,
        );

        if (!$fdata) {
            $e = "This forum doesn't exist. Weird.";
        }

        if ($e !== '' && $e !== '0') {
            $page .= $PAGE->meta('error', $e);
        } else {
            if (!isset($tdata)) {
                $tdata = [
                    'subtitle' => '',
                    'title' => '',
                ];
            }

            $form = '<form method="post" data-ajax-form="true"
                onsubmit="if(this.submitButton.value.match(/post/i)) this.submitButton.disabled=true;">
 <div class="topicform">
 <input type="hidden" name="act" value="post" />
 <input type="hidden" name="how" value="newtopic" />
 <input type="hidden" name="fid" value="' . $fid . '" />
  <label for="ttitle">Topic title:</label>
<input type="text" name="ttitle" id="ttitle" title="Topic Title" value="' . $tdata['title'] . '" />
<br>
  <label for="tdesc">Description:</label>
<input
    id="tdesc"
    name="tdesc"
    title="Topic Description (extra information about your topic)"
    type="text"
    value="' . $tdata['subtitle'] . '"
    />
<br>
  <textarea
    name="postdata"
    id="postdata"
    title="Type your post here"
    class="bbcode-editor"
    >' . $JAX->blockhtml($postdata) . '</textarea>
<br><div class="postoptions">
  ' . ($fdata['perms']['poll'] ? '<label class="addpoll" for="addpoll">Add a
Poll</label> <select name="poll_type" title="Add a poll"  onchange="document.querySelector(\'#polloptions\').'
            . 'style.display=this.value?\'block\':\'none\'">
<option value="">No</option>
<option value="single">Yes, single-choice</option>
<option value="multi">Yes, multi-choice</option></select><br>
  <div id="polloptions" style="display:none">
   <label for="pollq">Poll Question:</label><input type="text" id="pollq" name="pollq" title="Poll Question"/><br>
   <label for="pollc">Poll Choices:</label> (one per line)
<textarea id="pollc" name="pollchoices" title="Poll Choices"></textarea></div>' : '')
            . ($fdata['perms']['upload'] ? '<div id="attachfiles" class="addfile">
   Add Files <input type="file" name="Filedata" title="Browse for file" /></div>' : '')
            . '<div class="buttons"><input type="submit" name="submit"
   value="Post New Topic" title="Submit your post" onclick="this.form.submitButton=this;"
id="submitbutton" /> <input type="submit" name="submit" value="Preview" title="See a preview of your post"
onclick="this.form.submitButton=this" /></div>
 </div>
</form>';
            $page .= $PAGE->meta('box', '', $fdata['title'] . ' > New Topic', $form);
        }

        $PAGE->append('page', $page);
        $PAGE->JS('update', 'page', $page);
        if ($e !== '' && $e !== '0') {
            return;
        }

        if ($fdata['perms']['upload']) {
            $PAGE->JS('attachfiles');
        }

        $PAGE->JS('SCRIPT', "document.querySelector('#pollchoices').style.display='none'");
    }

    public function showpostform(): void
    {
        global $PAGE,$JAX,$DB,$SESS,$USER;
        $page = '';
        $tid = $this->tid;
        if ($PAGE->jsupdate && $this->how !== 'qreply') {
            return;
        }

        if ($USER && $this->how === 'qreply') {
            $PAGE->JS('closewindow', '#qreply');
        }

        if ($tid) {
            $result = $DB->safespecial(
                <<<'EOT'
                    SELECT t.`title` AS `title`,f.`perms` AS `perms`
                    FROM %t t
                    LEFT JOIN %t f
                        ON t.`fid`=f.`id`
                    WHERE t.`id`=?
                    EOT
                ,
                ['topics', 'forums'],
                $DB->basicvalue($tid),
            );
            $tdata = $DB->arow($result);
            $DB->disposeresult($result);
            if (!$tdata) {
                $page .= $PAGE->meta(
                    'error',
                    "The topic you're attempting to reply in no longer exists.",
                );
            }

            $tdata['title'] = $JAX->wordfilter($tdata['title']);
            $tdata['perms'] = $JAX->parseperms(
                $tdata['perms'],
                $USER ? $USER['group_id'] : 3,
            );
        }

        $page .= '<div id="post-preview">' . $this->postpreview . '</div>';
        $postdata = $JAX->blockhtml($this->postdata);
        $varsarray = [
            'act' => 'post',
            'how' => 'fullpost',
        ];
        if ($this->pid) {
            $varsarray['pid'] = $this->pid;
        } else {
            $varsarray['tid'] = $tid;
        }

        $vars = '';
        foreach ($varsarray as $k => $v) {
            $vars .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />';
        }

        if (isset($SESS->vars['multiquote']) && $SESS->vars['multiquote']) {
            $postdata = '';

            $result = $DB->safespecial(
                <<<'EOT'
                    SELECT p.`id` AS `id`,p.`auth_id` AS `auth_id`,p.`post` AS `post`,
                        UNIX_TIMESTAMP(p.`date`) AS `date`,p.`showsig` AS `showsig`,
                        p.`showemotes` AS `showemotes`,
                    	p.`tid` AS `tid`,p.`newtopic` AS `newtopic`,INET6_NTOA(p.`ip`) AS `ip`,
                        UNIX_TIMESTAMP(p.`edit_date`) AS `edit_date`,p.`editby` AS `editby`,
                        p.`rating` AS `rating`,m.`display_name` AS `name`
                    FROM %t p
                    LEFT JOIN %t m
                        ON p.`auth_id`=m.`id`
                    WHERE p.`id` IN ?
                    EOT
                ,
                ['posts', 'members'],
                $SESS->vars['multiquote'],
            );

            while ($f = $DB->arow($result)) {
                $postdata .= '[quote=' . $f['name'] . ']' . $f['post'] . '[/quote]' . PHP_EOL;
            }

            $SESS->delvar('multiquote');
        }

        $form = '<div class="postform">
<form method="post" data-ajax-form="true" onsubmit="if(this.submitButton.value.match(/post/i)) '
            . 'this.submitButton.disabled=true;" '
            . 'enctype="multipart/form-data">
 ' . $vars . '
  <textarea name="postdata" id="post" title="Type your post here" class="bbcode-editor">' . $postdata
        . '</textarea><br>'
        . ($tdata['perms']['upload'] ? '<div id="attachfiles">Add Files
  <input type="file" name="Filedata" title="Browse for file" /></div>' : '')
        . '<div class="buttons"><input type="submit" name="submit"
  value="Post" title="Submit your post" onclick="this.form.submitButton=this"
id="submitbutton"/><input type="submit" name="submit" value="Preview" title="See a preview of your post"
onclick="this.form.submitButton=this"/></div>
</form></div>';
        $page .= $PAGE->meta('box', '', $tdata['title'] . ' &gt; Reply', $form);
        $PAGE->append('page', $page);
        $PAGE->JS('update', 'page', $page);
        if (!$tdata['perms']['upload']) {
            return;
        }

        $PAGE->JS('attachfiles');
    }

    public function canedit($post): bool
    {
        global $PERMS,$USER;

        return ($post['auth_id']
            && ($post['newtopic'] ? $PERMS['can_edit_topics']
            : $PERMS['can_edit_posts'])
            && $post['auth_id'] === $USER['id'])
            || $this->canmoderate($post['tid']);
    }

    public function canmoderate($tid)
    {
        global $PAGE,$PERMS,$USER,$DB;
        if ($this->canmod) {
            return $this->canmod;
        }

        $canmod = false;
        if ($PERMS['can_moderate']) {
            $canmod = true;
        }

        if ($USER['mod']) {
            $result = $DB->safespecial(
                'SELECT mods FROM %t WHERE id=(SELECT fid FROM %t WHERE id=?)',
                ['forums', 'topics'],
                $DB->basicvalue($tid),
            );
            $mods = $DB->arow($result);
            $DB->disposeresult($result);
            if (in_array($USER['id'], explode(',', (string) $mods['mods']))) {
                $canmod = true;
            }
        }

        return $this->canmod = $canmod;
    }

    public function editpost(): ?bool
    {
        global $DB,$JAX,$PAGE,$USER,$PERMS;
        $pid = $this->pid;
        $tid = $this->tid;
        $e = '';
        $editingpost = false;
        if (!$pid || !is_numeric($pid)) {
            $e = 'Invalid post to edit.';
        }

        if ($this->postdata) {
            if (!$this->nopost && trim((string) $this->postdata) === '') {
                $e = "You didn't supply a post!";
            } elseif (mb_strlen((string) $this->postdata) > 65535) {
                $e = 'Post must not exceed 65,535 bytes.';
            }
        }

        if ($e === '' || $e === '0') {
            $result = $DB->safeselect(
                <<<'EOT'
                    `id`,`auth_id`,`post`,UNIX_TIMESTAMP(`date`) AS `date`,`showsig`,`showemotes`,
                        `tid`,`newtopic`,INET6_NTOA(`ip`) AS `ip`,
                        UNIX_TIMESTAMP(`edit_date`) AS `edit_date`,`editby`,`rating`
                    EOT
                ,
                'posts',
                'WHERE `id`=?',
                $pid,
            );
            $tmp = $DB->arow($result);
            $DB->disposeresult($result);

            if (!$tmp) {
                $e = 'The post you are trying to edit does not exist.';
            } elseif (!$this->canedit($tmp)) {
                $e = "You don't have permission to edit that post!";
            } elseif ($this->postdata === null) {
                $editingpost = true;
                $this->postdata = $tmp['post'];
            }
        }

        if ($tid && !$e) {
            if (!is_numeric($tid) || !$tid) {
                $e = 'Invalid post to edit.';
            } else {
                $result = $DB->safeselect(
                    <<<'EOT'
                        `id`,`title`,`subtitle`,`lp_uid`,UNIX_TIMESTAMP(`lp_date`) AS `lp_date`,
                        `fid`,`auth_id`,`replies`,`views`,
                        `pinned`,`poll_choices`,`poll_results`,`poll_q`,`poll_type`,`summary`,
                        `locked`,UNIX_TIMESTAMP(`date`) AS `date`,`op`,`cal_event`
                        EOT
                    ,
                    'topics',
                    'WHERE `id`=?',
                    $tid,
                );
                $tmp = $DB->arow($result);

                $DB->disposeresult($result);

                if (!$tmp) {
                    $e = "The topic you are trying to edit doesn't exist.";
                } elseif (trim((string) $JAX->p['ttitle']) === '') {
                    $e = 'You must supply a topic title!';
                } else {
                    $DB->safeupdate(
                        'topics',
                        [
                            'subtitle' => $JAX->blockhtml($JAX->p['tdesc']),
                            'summary' => mb_substr(
                                preg_replace(
                                    '@\s+@',
                                    ' ',
                                    (string) $JAX->wordfilter(
                                        $JAX->blockhtml(
                                            $JAX->textonly(
                                                $this->postdata,
                                            ),
                                        ),
                                    ),
                                ),
                                0,
                                50,
                            ),
                            'title' => $JAX->blockhtml($JAX->p['ttitle']),
                        ],
                        'WHERE `id`=?',
                        $tid,
                    );
                }
            }
        }

        if ($e !== '' && $e !== '0') {
            $PAGE->JS('error', $e);
            $PAGE->append('PAGE', $PAGE->error($e));
        }

        if ($e || $editingpost) {
            $this->showpostform();

            return false;
        }

        $DB->safeupdate(
            'posts',
            [
                'editby' => $USER['id'],
                'edit_date' => date('Y-m-d H:i:s', time()),
                'post' => $this->postdata,
            ],
            'WHERE `id`=?',
            $DB->basicvalue($pid),
        );
        $PAGE->JS(
            'update',
            "#pid_{$pid} .post_content",
            $JAX->theworks($this->postdata),
        );
        $PAGE->JS('softurl');

        return null;
    }

    public function submitpost(): ?bool
    {
        global $JAX,$PAGE,$DB,$SESS,$USER,$PERMS;
        $SESS->act();
        $tid = $this->tid;
        $fid = $this->fid;
        $postdata = $this->postdata;
        $fdata = false;
        $newtopic = false;
        $time = time();
        $uid = $USER['id'];
        $e = '';

        if (!$this->nopost && trim((string) $postdata) === '') {
            $e = "You didn't supply a post!";
        } elseif (mb_strlen((string) $postdata) > 50000) {
            $e = 'Post must not exceed 50,000 characters.';
        }

        if (!$e && $this->how === 'newtopic') {
            if (!$fid || !is_numeric($fid)) {
                $e = 'No forum specified exists.';
            } elseif (
                !isset($JAX->p['ttitle'])
                || trim($JAX->p['ttitle']) === ''
            ) {
                $e = "You didn't specify a topic title!";
            } elseif (
                isset($JAX->p['ttitle'])
                && mb_strlen($JAX->p['ttitle']) > 255
            ) {
                $e = 'Topic title must not exceed 255 characters';
            } elseif (
                isset($JAX->p['subtitle'])
                && mb_strlen($JAX->p['subtitle']) > 255
            ) {
                $e = 'Subtitle must not exceed 255 characters';
            } elseif (isset($JAX->p['poll_type']) && $JAX->p['poll_type']) {
                $pollchoices = [];
                $pollChoice = preg_split("@[\r\n]+@", (string) $JAX->p['pollchoices']);
                foreach ($pollChoice as $v) {
                    if (trim($v) === '' || trim($v) === '0') {
                        continue;
                    }

                    $pollchoices[] = $JAX->blockhtml($v);
                }

                if (trim((string) $JAX->p['pollq']) === '') {
                    $e = "You didn't specify a poll question!";
                } elseif (count($pollchoices) > 10) {
                    $e = 'Poll choices must not exceed 10.';
                } elseif ($pollchoices === []) {
                    $e = "You didn't provide any poll choices!";
                }
            }

            // Perms.
            $result = $DB->safeselect(
                '`perms`',
                'forums',
                'WHERE `id`=?',
                $fid,
            );
            $fdata = $DB->arow($result);
            $DB->disposeresult($result);

            if (!$fdata) {
                $e = "The forum you're trying to post in does not exist.";
            } else {
                $fdata['perms'] = $JAX->parseperms(
                    $fdata['perms'],
                    $USER ? $USER['group_id'] : 3,
                );
                if (!$fdata['perms']['start']) {
                    $e = <<<'EOT'
                        You don't have permission to post a new topic in that forum.
                        EOT;
                }

                if (
                    ((isset($JAX->p['poll_type']) && $JAX->p['poll_type'])
                    || (isset($JAX->p['pollq']) && $JAX->p['pollq']))
                    && !$fdata['perms']['poll']
                ) {
                    $e = "You don't have permission to post a poll in that forum";
                }
            }

            if ($e === '' || $e === '0') {
                $DB->safeinsert(
                    'topics',
                    [
                        'auth_id' => $uid,
                        'date' => date('Y-m-d H:i:s', $time),
                        'fid' => $fid,
                        'lp_date' => date('Y-m-d H:i:s', $time),
                        'lp_uid' => $uid,
                        'poll_choices' => isset($pollchoices) && $pollchoices
                            ? $JAX->json_encode($pollchoices)
                            : '',
                        'poll_q' => isset($JAX->p['pollq'])
                            ? $JAX->blockhtml($JAX->p['pollq'])
                            : '',
                        'poll_type' => $JAX->p['poll_type'] ?? '',
                        'replies' => 0,
                        'subtitle' => $JAX->blockhtml($JAX->p['tdesc']),
                        'summary' => mb_substr(
                            preg_replace(
                                '@\s+@',
                                ' ',
                                (string) $JAX->blockhtml(
                                    $JAX->textonly(
                                        $this->postdata,
                                    ),
                                ),
                            ),
                            0,
                            50,
                        ),
                        'title' => $JAX->blockhtml($JAX->p['ttitle']),
                        'views' => 0,
                    ],
                );
                $tid = $DB->insert_id(1);
            }

            $newtopic = true;
        }

        if ($e !== '' && $e !== '0') {
            $PAGE->append('PAGE', $PAGE->error($e));
            $PAGE->JS('error', $e);
            $PAGE->JS('enable', 'submitbutton');
            if ($this->how === 'newtopic') {
                $this->showtopicform();
            } else {
                $this->showpostform();
            }

            return null;
        }

        if ($tid && is_numeric($tid)) {
            $result = $DB->safespecial(
                <<<'EOT'
                    SELECT t.`title` AS `topictitle`,f.`id` AS `id`,f.`path` AS `path`,
                        f.`perms` AS `perms`,f.`nocount` AS `nocount`,t.`locked` AS `locked`
                    FROM %t t
                    LEFT JOIN %t f
                        ON t.`fid`=f.`id`
                        WHERE t.`id`=?
                    EOT
                ,
                ['topics', 'forums'],
                $tid,
            );
            $fdata = $DB->arow($result);
            $DB->disposeresult($result);
        }

        if (!$fdata) {
            $e = "The topic you're trying to reply to does not exist.";
            $PAGE->append('PAGE', $PAGE->error($e));
            $PAGE->JS('error', $e);

            return false;
        }

        $fdata['perms'] = $JAX->parseperms(
            $fdata['perms'],
            $USER ? $USER['group_id'] : 3,
        );
        if (
            !$fdata['perms']['reply']
            || $fdata['locked']
            && !$PERMS['can_override_locked_topics']
        ) {
            $e = "You don't have permission to post here.";
            $PAGE->append('PAGE', $PAGE->error($e));
            $PAGE->JS('error', $e);

            return false;
        }

        // Actually PUT THE POST IN for godsakes.
        $DB->safeinsert(
            'posts',
            [
                'auth_id' => $uid,
                'date' => date('Y-m-d H:i:s', $time),
                'ip' => $JAX->ip2bin(),
                'newtopic' => $newtopic ? 1 : 0,
                'post' => $postdata,
                'tid' => $tid,
            ],
        );

        $pid = $DB->insert_id(1);
        // Set op.
        if ($newtopic) {
            $DB->safeupdate(
                'topics',
                [
                    'op' => $pid,
                ],
                'WHERE `id`=?',
                $tid,
            );
        }

        // Update activity history.
        $DB->safeinsert(
            'activity',
            [
                'arg1' => $fdata['topictitle'],
                'date' => date('Y-m-d H:i:s', $time),
                'pid' => $pid,
                'tid' => $tid,
                'type' => $newtopic ? 'new_topic' : 'new_post',
                'uid' => $uid,
            ],
        );

        // Update last post info
        // for the topic.
        if (!$newtopic) {
            $DB->safespecial(
                <<<'EOT'
                    UPDATE %t
                    SET `lp_uid` = ?, `lp_date` = ?, `replies` = `replies` + 1
                    WHERE `id`=?
                    EOT
                ,
                ['topics'],
                $uid,
                date('Y-m-d H:i:s', $time),
                $tid,
            );
        }

        // Do some magic to update the tree all the way up (for subforums).
        $path = trim((string) $fdata['path']) !== '' && trim((string) $fdata['path']) !== '0'
            ? explode(' ', (string) $fdata['path'])
            : [];
        if (!in_array($fdata['id'], $path)) {
            $path[] = $fdata['id'];
        }

        if ($newtopic) {
            $DB->safespecial(
                <<<'EOT'
                    UPDATE %t
                    SET `lp_uid`= ?, `lp_tid` = ?, `lp_topic` = ?, `lp_date` = ?,
                        `topics` = `topics` + 1
                    WHERE `id` IN ?
                    EOT
                ,
                ['forums'],
                $uid,
                $tid,
                $fdata['topictitle'],
                date('Y-m-d H:i:s', $time),
                $path,
            );
        } else {
            $DB->safespecial(
                <<<'EOT'
                    UPDATE %t
                    SET `lp_uid`= ?, `lp_tid` = ?, `lp_topic` = ?, `lp_date` = ?,
                        `posts` = `posts` + 1
                    WHERE `id` IN ?
                    EOT
                ,
                ['forums'],
                $uid,
                $tid,
                $fdata['topictitle'],
                date('Y-m-d H:i:s', $time),
                $path,
            );
        }

        // Update statistics.
        if (!$fdata['nocount']) {
            $DB->safespecial(
                <<<'EOT'
                    UPDATE %t
                    SET `posts` = `posts` + 1
                    WHERE `id`=?
                    EOT
                ,
                ['members'],
                $DB->basicvalue($JAX->userData['id']),
            );
        }

        if ($newtopic) {
            $DB->safespecial(
                <<<'EOT'
                    UPDATE %t
                    SET `posts` = `posts` + 1, `topics` = `topics` + 1
                    EOT
                ,
                ['stats'],
            );
        } else {
            $DB->safespecial(
                'UPDATE %t SET `posts` = `posts` + 1',
                ['stats'],
            );
        }

        if ($this->how === 'qreply') {
            $PAGE->JS('closewindow', '#qreply');
            $PAGE->JS('refreshdata');
        } else {
            $PAGE->location('?act=vt' . $tid . '&getlast=1');
        }

        return null;
    }
}
