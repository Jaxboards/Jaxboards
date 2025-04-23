<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Page;
use Jax\Session;

use function array_pop;
use function count;
use function explode;
use function filesize;
use function gmdate;
use function hash_file;
use function in_array;
use function is_file;
use function is_numeric;
use function json_encode;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function move_uploaded_file;
use function preg_replace;
use function preg_split;
use function str_replace;
use function trim;

use const PHP_EOL;

final class Post
{
    private $canmod;

    private $postdata = '';

    private $postpreview = '';

    /**
     * @var false
     */
    private $nopost = true;

    private $tid;

    private $fid;

    private $pid;

    private $how;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly IPAddress $ipAddress,
        private readonly Session $session,
    ) {
        $this->page->metadefs['post-preview'] = $this->page->meta('box', '', 'Post Preview', '%s');
    }

    public function route(): void
    {
        $this->tid = $this->jax->b['tid'] ?? 0;
        $this->fid = $this->jax->b['fid'] ?? 0;
        $this->pid = $this->jax->b['pid'] ?? 0;
        $this->how = $this->jax->b['how'] ?? '';

        if (isset($this->jax->p['postdata']) && $this->jax->p['postdata']) {
            $this->nopost = false;
            $this->postdata = $this->jax->p['postdata'];
        }

        if ($this->postdata) {
            // Linkify stuff before sending it.
            $this->postdata = str_replace("\t", '    ', $this->postdata);
            $codes = $this->jax->startcodetags($this->postdata);
            $this->postdata = $this->jax->linkify($this->postdata);
            $this->postdata = $this->jax->finishcodetags($this->postdata, $codes, true);

            // This is aliases [youtube] to [video] but it probably should not be here
            $this->postdata = str_replace('youtube]', 'video]', $this->postdata);
        }

        if (
            isset($_FILES['Filedata'], $_FILES['Filedata']['tmp_name'])
            && $_FILES['Filedata']['tmp_name']
        ) {
            $this->jax->p['postdata'] .= '[attachment]'
                . $this->upload($_FILES['Filedata'])
                . '[/attachment]';
        }

        if (
            isset($this->jax->p['submit'])
            && ($this->jax->p['submit'] === 'Preview'
            || $this->jax->p['submit'] === 'Full Reply')
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
            $this->page->location('?');
        }
    }

    public function upload($fileobj, $uid = false): string
    {
        global $USER;
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

        if (!in_array($ext, $this->config->getSetting('images') ?? [])) {
            $ext = '';
        }

        if ($ext !== '' && $ext !== '0') {
            $ext = '.' . $ext;
        }

        $file = $uploadpath . $hash . $ext;
        if (!is_file($file)) {
            move_uploaded_file($fileobj['tmp_name'], $file);
            $this->database->safeinsert(
                'files',
                [
                    'hash' => $hash,
                    'ip' => $this->ipAddress->asBinary(),
                    'name' => $fileobj['name'],
                    'size' => $size,
                    'uid' => $uid,
                ],
            );
            $id = $this->database->insert_id(1);
        } else {
            $result = $this->database->safeselect(
                ['id'],
                'files',
                'WHERE `hash`=?',
                $hash,
            );
            $thisrow = $this->database->arow($result);
            $id = array_pop($thisrow);
            $this->database->disposeresult($result);
        }

        return (string) $id;
    }

    public function previewpost(): void
    {
        $post = $this->postdata;
        if (trim((string) $post) !== '' && trim((string) $post) !== '0') {
            $post = $this->jax->theworks($post);
            $post = $this->page->meta('post-preview', $post);
            $this->postpreview = $post;
        }

        if (!$this->page->jsaccess || $this->how === 'qreply') {
            $this->showpostform();
        }

        $this->page->JS('update', 'post-preview', $post);
    }

    public function showtopicform(): void
    {
        global $PERMS,$USER;
        $e = '';
        if ($this->page->jsupdate) {
            return;
        }

        $postdata = $this->postdata;
        $page = '<div id="post-preview">' . $this->postpreview . '</div>';
        $fid = $this->fid;

        if ($this->how === 'edit') {
            $result = $this->database->safeselect(
                [
                    'auth_id',
                    'cal_event',
                    'fid',
                    'id',
                    'locked',
                    'lp_uid',
                    'op',
                    'pinned',
                    'poll_choices',
                    'poll_q',
                    'poll_results',
                    'poll_type',
                    'replies',
                    'subtitle',
                    'summary',
                    'title',
                    'views',
                    'UNIX_TIMESTAMP(`date`) AS `date`',
                    'UNIX_TIMESTAMP(`lp_date`) AS `lp_date`',
                ],
                'topics',
                'WHERE `id`=?',
                $this->database->basicvalue($this->tid),
            );
            $tdata = $this->database->arow($result);
            $this->database->disposeresult($result);

            if (!$tdata) {
                $e = "The topic you're trying to edit does not exist.";
            } else {
                $result = $this->database->safeselect(
                    ['post'],
                    'posts',
                    'WHERE `id`=?',
                    $this->database->basicvalue($tdata['op']),
                );
                $postdata = $this->database->arow($result);
                $this->database->disposeresult($result);

                if ($postdata) {
                    $postdata = $postdata[0];
                }
            }

            $fid = $tdata['fid'];
        }

        $result = $this->database->safeselect(
            ['title', 'perms'],
            'forums',
            'WHERE `id`=?',
            $fid,
        );
        $fdata = $this->database->arow($result);
        $this->database->disposeresult($result);

        $fdata['perms'] = $this->jax->parsePerms(
            $fdata['perms'],
            $USER ? $USER['group_id'] : 3,
        );

        if ($fdata === []) {
            $e = "This forum doesn't exist. Weird.";
        }

        if ($e !== '' && $e !== '0') {
            $page .= $this->page->meta('error', $e);
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
    >' . $this->jax->blockhtml($postdata) . '</textarea>
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
            $page .= $this->page->meta('box', '', $fdata['title'] . ' > New Topic', $form);
        }

        $this->page->append('page', $page);
        $this->page->JS('update', 'page', $page);
        if ($e !== '' && $e !== '0') {
            return;
        }

        if ($fdata['perms']['upload']) {
            $this->page->JS('attachfiles');
        }

        $this->page->JS('SCRIPT', "document.querySelector('#pollchoices').style.display='none'");
    }

    public function showpostform(): void
    {
        global $USER;
        $page = '';
        $tid = $this->tid;
        if ($this->page->jsupdate && $this->how !== 'qreply') {
            return;
        }

        if ($USER && $this->how === 'qreply') {
            $this->page->JS('closewindow', '#qreply');
        }

        if ($tid) {
            $result = $this->database->safespecial(
                <<<'EOT'
                    SELECT t.`title` AS `title`,f.`perms` AS `perms`
                    FROM %t t
                    LEFT JOIN %t f
                        ON t.`fid`=f.`id`
                    WHERE t.`id`=?
                    EOT
                ,
                ['topics', 'forums'],
                $this->database->basicvalue($tid),
            );
            $tdata = $this->database->arow($result);
            $this->database->disposeresult($result);
            if (!$tdata) {
                $page .= $this->page->meta(
                    'error',
                    "The topic you're attempting to reply in no longer exists.",
                );
            }

            $tdata['title'] = $this->jax->wordfilter($tdata['title']);
            $tdata['perms'] = $this->jax->parseperms(
                $tdata['perms'],
                $USER ? $USER['group_id'] : 3,
            );
        }

        $page .= '<div id="post-preview">' . $this->postpreview . '</div>';
        $postdata = $this->jax->blockhtml($this->postdata);
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

        if (
            isset($this->session->vars['multiquote'])
            && $this->session->vars['multiquote']
        ) {
            $postdata = '';

            $result = $this->database->safespecial(
                <<<'EOT'
                    SELECT
                        m.`display_name` AS `name`,
                        p.`post` AS `post`
                    FROM %t p
                    LEFT JOIN %t m
                        ON p.`auth_id`=m.`id`
                    WHERE p.`id` IN (?)
                    EOT
                ,
                ['posts', 'members'],
                $this->session->vars['multiquote'],
            );

            while ($postRow = $this->database->arow($result)) {
                $postdata .= '[quote=' . $postRow['name'] . ']' . $postRow['post'] . '[/quote]' . PHP_EOL;
            }

            $this->session->delvar('multiquote');
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
        $page .= $this->page->meta('box', '', $tdata['title'] . ' &gt; Reply', $form);
        $this->page->append('page', $page);
        $this->page->JS('update', 'page', $page);
        if (!$tdata['perms']['upload']) {
            return;
        }

        $this->page->JS('attachfiles');
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
        global $PERMS,$USER,$DB;
        if ($this->canmod) {
            return $this->canmod;
        }

        $canmod = false;
        if ($PERMS['can_moderate']) {
            $canmod = true;
        }

        if ($USER['mod']) {
            $result = $this->database->safespecial(
                'SELECT mods FROM %t WHERE id=(SELECT fid FROM %t WHERE id=?)',
                ['forums', 'topics'],
                $this->database->basicvalue($tid),
            );
            $mods = $this->database->arow($result);
            $this->database->disposeresult($result);
            if (in_array($USER['id'], explode(',', (string) $mods['mods']))) {
                $canmod = true;
            }
        }

        return $this->canmod = $canmod;
    }

    public function editpost(): ?bool
    {
        global $USER,$PERMS;
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
            $result = $this->database->safeselect(
                [
                    'auth_id',
                    'newtopic',
                    'post',
                    'tid',
                ],
                'posts',
                'WHERE `id`=?',
                $pid,
            );
            $post = $this->database->arow($result);
            $this->database->disposeresult($result);

            if (!$post) {
                $e = 'The post you are trying to edit does not exist.';
            } elseif (!$this->canedit($post)) {
                $e = "You don't have permission to edit that post!";
            } elseif ($this->postdata === null) {
                $editingpost = true;
                $this->postdata = $post['post'];
            }
        }

        if ($tid && !$e) {
            if (!is_numeric($tid) || !$tid) {
                $e = 'Invalid post to edit.';
            } else {
                $result = $this->database->safeselect(
                    [
                        'auth_id',
                        'cal_event',
                        'fid',
                        'id',
                        'locked',
                        'lp_uid',
                        'op',
                        'pinned',
                        'poll_choices',
                        'poll_q',
                        'poll_results',
                        'poll_type',
                        'replies',
                        'subtitle',
                        'summary',
                        'title',
                        'views',
                        'UNIX_TIMESTAMP(`date`) AS `date`',
                        'UNIX_TIMESTAMP(`lp_date`) AS `lp_date`',
                    ],
                    'topics',
                    'WHERE `id`=?',
                    $tid,
                );
                $tmp = $this->database->arow($result);

                $this->database->disposeresult($result);

                if (!$tmp) {
                    $e = "The topic you are trying to edit doesn't exist.";
                } elseif (trim((string) $this->jax->p['ttitle']) === '') {
                    $e = 'You must supply a topic title!';
                } else {
                    $this->database->safeupdate(
                        'topics',
                        [
                            'subtitle' => $this->jax->blockhtml($this->jax->p['tdesc']),
                            'summary' => mb_substr(
                                (string) preg_replace(
                                    '@\s+@',
                                    ' ',
                                    (string) $this->jax->wordfilter(
                                        $this->jax->blockhtml(
                                            $this->jax->textonly(
                                                $this->postdata,
                                            ),
                                        ),
                                    ),
                                ),
                                0,
                                50,
                            ),
                            'title' => $this->jax->blockhtml($this->jax->p['ttitle']),
                        ],
                        'WHERE `id`=?',
                        $tid,
                    );
                }
            }
        }

        if ($e !== '' && $e !== '0') {
            $this->page->JS('error', $e);
            $this->page->append('PAGE', $this->page->error($e));
        }

        if ($e || $editingpost) {
            $this->showpostform();

            return false;
        }

        $this->database->safeupdate(
            'posts',
            [
                'editby' => $USER['id'],
                'edit_date' => gmdate('Y-m-d H:i:s'),
                'post' => $this->postdata,
            ],
            'WHERE `id`=?',
            $this->database->basicvalue($pid),
        );
        $this->page->JS(
            'update',
            "#pid_{$pid} .post_content",
            $this->jax->theworks($this->postdata),
        );
        $this->page->JS('softurl');

        return null;
    }

    public function submitpost(): ?bool
    {
        global $USER,$PERMS;
        $this->session->act();
        $tid = $this->tid;
        $fid = $this->fid;
        $postdata = $this->postdata;
        $fdata = false;
        $newtopic = false;
        $postDate = gmdate('Y-m-d H:i:s');
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
                !isset($this->jax->p['ttitle'])
                || trim((string) $this->jax->p['ttitle']) === ''
            ) {
                $e = "You didn't specify a topic title!";
            } elseif (
                isset($this->jax->p['ttitle'])
                && mb_strlen((string) $this->jax->p['ttitle']) > 255
            ) {
                $e = 'Topic title must not exceed 255 characters';
            } elseif (
                isset($this->jax->p['subtitle'])
                && mb_strlen($this->jax->p['subtitle']) > 255
            ) {
                $e = 'Subtitle must not exceed 255 characters';
            } elseif (
                isset($this->jax->p['poll_type'])
                && $this->jax->p['poll_type']
            ) {
                $pollchoices = [];
                $pollChoice = preg_split("@[\r\n]+@", (string) $this->jax->p['pollchoices']);
                foreach ($pollChoice as $v) {
                    if (trim($v) === '') {
                        continue;
                    }

                    if (trim($v) === '0') {
                        continue;
                    }

                    $pollchoices[] = $this->jax->blockhtml($v);
                }

                if (trim((string) $this->jax->p['pollq']) === '') {
                    $e = "You didn't specify a poll question!";
                } elseif (count($pollchoices) > 10) {
                    $e = 'Poll choices must not exceed 10.';
                } elseif ($pollchoices === []) {
                    $e = "You didn't provide any poll choices!";
                }
            }

            // Perms.
            $result = $this->database->safeselect(
                ['perms'],
                'forums',
                'WHERE `id`=?',
                $fid,
            );
            $fdata = $this->database->arow($result);
            $this->database->disposeresult($result);

            if (!$fdata) {
                $e = "The forum you're trying to post in does not exist.";
            } else {
                $fdata['perms'] = $this->jax->parseperms(
                    $fdata['perms'],
                    $USER ? $USER['group_id'] : 3,
                );
                if (!$fdata['perms']['start']) {
                    $e = <<<'EOT'
                        You don't have permission to post a new topic in that forum.
                        EOT;
                }

                if (
                    ((isset($this->jax->p['poll_type']) && $this->jax->p['poll_type'])
                    || (isset($this->jax->p['pollq']) && $this->jax->p['pollq']))
                    && !$fdata['perms']['poll']
                ) {
                    $e = "You don't have permission to post a poll in that forum";
                }
            }

            if ($e === '' || $e === '0') {
                $this->database->safeinsert(
                    'topics',
                    [
                        'auth_id' => $uid,
                        'date' => $postDate,
                        'fid' => $fid,
                        'lp_date' => $postDate,
                        'lp_uid' => $uid,
                        'poll_choices' => isset($pollchoices) && $pollchoices
                            ? json_encode($pollchoices)
                            : '',
                        'poll_q' => isset($this->jax->p['pollq'])
                            ? $this->jax->blockhtml($this->jax->p['pollq'])
                            : '',
                        'poll_type' => $this->jax->p['poll_type'] ?? '',
                        'replies' => 0,
                        'subtitle' => $this->jax->blockhtml($this->jax->p['tdesc']),
                        'summary' => mb_substr(
                            (string) preg_replace(
                                '@\s+@',
                                ' ',
                                $this->jax->blockhtml(
                                    $this->jax->textonly(
                                        $this->postdata,
                                    ),
                                ),
                            ),
                            0,
                            50,
                        ),
                        'title' => $this->jax->blockhtml($this->jax->p['ttitle']),
                        'views' => 0,
                    ],
                );
                $tid = $this->database->insert_id(1);
            }

            $newtopic = true;
        }

        if ($e !== '' && $e !== '0') {
            $this->page->append('PAGE', $this->page->error($e));
            $this->page->JS('error', $e);
            $this->page->JS('enable', 'submitbutton');
            if ($this->how === 'newtopic') {
                $this->showtopicform();
            } else {
                $this->showpostform();
            }

            return null;
        }

        if ($tid && is_numeric($tid)) {
            $result = $this->database->safespecial(
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
            $fdata = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        if (!$fdata) {
            $e = "The topic you're trying to reply to does not exist.";
            $this->page->append('PAGE', $this->page->error($e));
            $this->page->JS('error', $e);

            return false;
        }

        $fdata['perms'] = $this->jax->parseperms(
            $fdata['perms'],
            $USER ? $USER['group_id'] : 3,
        );
        if (
            !$fdata['perms']['reply']
            || $fdata['locked']
            && !$PERMS['can_override_locked_topics']
        ) {
            $e = "You don't have permission to post here.";
            $this->page->append('PAGE', $this->page->error($e));
            $this->page->JS('error', $e);

            return false;
        }

        // Actually PUT THE POST IN!
        $this->database->safeinsert(
            'posts',
            [
                'auth_id' => $uid,
                'date' => $postDate,
                'ip' => $this->ipAddress->asBinary(),
                'newtopic' => $newtopic ? 1 : 0,
                'post' => $postdata,
                'tid' => $tid,
            ],
        );

        $pid = $this->database->insert_id(1);
        // Set op.
        if ($newtopic) {
            $this->database->safeupdate(
                'topics',
                [
                    'op' => $pid,
                ],
                'WHERE `id`=?',
                $tid,
            );
        }

        // Update activity history.
        $this->database->safeinsert(
            'activity',
            [
                'arg1' => $fdata['topictitle'],
                'date' => $postDate,
                'pid' => $pid,
                'tid' => $tid,
                'type' => $newtopic ? 'new_topic' : 'new_post',
                'uid' => $uid,
            ],
        );

        // Update last post info
        // for the topic.
        if (!$newtopic) {
            $this->database->safespecial(
                <<<'EOT'
                    UPDATE %t
                    SET `lp_uid` = ?, `lp_date` = ?, `replies` = `replies` + 1
                    WHERE `id`=?
                    EOT
                ,
                ['topics'],
                $uid,
                $postDate,
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
            $this->database->safespecial(
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
                $postDate,
                $path,
            );
        } else {
            $this->database->safespecial(
                <<<'EOT'
                    UPDATE %t
                    SET `lp_uid`= ?, `lp_tid` = ?, `lp_topic` = ?, `lp_date` = ?,
                        `posts` = `posts` + 1
                    WHERE `id` IN (?)
                    EOT
                ,
                ['forums'],
                $uid,
                $tid,
                $fdata['topictitle'],
                $postDate,
                $path,
            );
        }

        // Update statistics.
        if (!$fdata['nocount']) {
            $this->database->safespecial(
                <<<'EOT'
                    UPDATE %t
                    SET `posts` = `posts` + 1
                    WHERE `id`=?
                    EOT
                ,
                ['members'],
                $this->database->basicvalue($USER['id']),
            );
        }

        if ($newtopic) {
            $this->database->safespecial(
                <<<'EOT'
                    UPDATE %t
                    SET `posts` = `posts` + 1, `topics` = `topics` + 1
                    EOT
                ,
                ['stats'],
            );
        } else {
            $this->database->safespecial(
                'UPDATE %t SET `posts` = `posts` + 1',
                ['stats'],
            );
        }

        if ($this->how === 'qreply') {
            $this->page->JS('closewindow', '#qreply');
            $this->page->JS('refreshdata');
        } else {
            $this->page->location('?act=vt' . $tid . '&getlast=1');
        }

        return null;
    }
}
