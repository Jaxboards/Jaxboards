<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\IPAddress;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\TextFormatting;
use Jax\User;

use function array_pop;
use function count;
use function explode;
use function filesize;
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

/**
 * @psalm-api
 */
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
        private readonly DomainDefinitions $domainDefinitions,
        private readonly Page $page,
        private readonly IPAddress $ipAddress,
        private readonly Request $request,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {
        $this->page->addmeta('post-preview', $this->page->meta('box', '', 'Post Preview', '%s'));
    }

    public function render(): void
    {
        $this->tid = $this->request->both('tid') ?? 0;
        $this->fid = $this->request->both('fid') ?? 0;
        $this->pid = $this->request->both('pid') ?? 0;
        $this->how = $this->request->both('how') ?? '';

        if ($this->request->post('postdata') !== null) {
            $this->nopost = false;
            $this->postdata = $this->request->post('postdata');

            // Linkify stuff before sending it.
            $this->postdata = str_replace("\t", '    ', $this->postdata);
            $codes = $this->textFormatting->startcodetags($this->postdata);
            $this->postdata = $this->textFormatting->linkify($this->postdata);
            $this->postdata = $this->textFormatting->finishcodetags($this->postdata, $codes, true);

            // This is aliases [youtube] to [video] but it probably should not be here
            $this->postdata = str_replace('youtube]', 'video]', $this->postdata);
        }

        if (
            isset($_FILES['Filedata'], $_FILES['Filedata']['tmp_name'])
            && $_FILES['Filedata']['tmp_name']
        ) {
            $this->postdata .= '[attachment]'
                . $this->upload($_FILES['Filedata'])
                . '[/attachment]';
        }

        if (
            $this->request->post('submit') === 'Preview'
            || $this->request->post('submit') === 'Full Reply'
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
        if ($uid === false) {
            $uid = $this->user->get('id');
        }

        if ($uid === false && $this->user->isGuest()) {
            return 'must be logged in';
        }

        $size = filesize($fileobj['tmp_name']);
        $hash = hash_file('sha512', $fileobj['tmp_name']);
        $uploadpath = $this->domainDefinitions->getBoardPath() . '/Uploads/';

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
            $id = $this->database->insertId();
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
            $post = $this->textFormatting->theworks($post);
            $post = $this->page->meta('post-preview', $post);
            $this->postpreview = $post;
        }

        if (!$this->request->isJSAccess() || $this->how === 'qreply') {
            $this->showpostform();
        }

        $this->page->JS('update', 'post-preview', $post);
    }

    public function showtopicform(): void
    {
        $error = null;
        if ($this->request->isJSUpdate()) {
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
                $error = "The topic you're trying to edit does not exist.";
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
        }

        $result = $this->database->safeselect(
            ['title', 'perms'],
            'forums',
            'WHERE `id`=?',
            $fid,
        );
        $forum = $this->database->arow($result);
        $this->database->disposeresult($result);

        $forum['perms'] = $this->user->parseForumPerms(
            $forum['perms'],
        );

        if ($forum === []) {
            $error = "This forum doesn't exist. Weird.";
        }

        if ($error !== null) {
            $page .= $this->page->meta('error', $error);
        } else {
            if (!isset($tdata)) {
                $tdata = [
                    'subtitle' => '',
                    'title' => '',
                ];
            }

            $pollForm = $forum['perms']['poll'] ? <<<'HTML'
                <label class="addpoll" for="addpoll">
                    Add a Poll
                </label>
                <select name="poll_type" title="Add a poll"
                    onchange="document.querySelector('#polloptions').style.display=this.value?'block':'none'">
                <option value="">No</option>
                <option value="single">Yes, single-choice</option>
                <option value="multi">Yes, multi-choice</option></select>
                <br>
                <div id="polloptions" style="display:none">
                    <label for="pollq">Poll Question:</label>
                    <input type="text" id="pollq" name="pollq" title="Poll Question"/><br>
                    <label for="pollc">Poll Choices:</label> (one per line)
                    <textarea id="pollc" name="pollchoices" title="Poll Choices"></textarea>
                </div>
                HTML : '';

            $uploadButton = $forum['perms']['upload']
                ? ''
                : <<<'HTML'
                    <div id="attachfiles" class="addfile">
                        Add Files <input type="file" name="Filedata" title="Browse for file" />
                    </div>
                    HTML;

            $form = <<<HTML
                <form method="post" data-ajax-form="true"
                                onsubmit="if(this.submitButton.value.match(/post/i)) this.submitButton.disabled=true;">
                <div class="topicform">
                    <input type="hidden" name="act" value="post" />
                    <input type="hidden" name="how" value="newtopic" />
                    <input type="hidden" name="fid" value="{$fid}" />
                    <label for="ttitle">Topic title:</label>
                    <input type="text" name="ttitle" id="ttitle" title="Topic Title" value="{$tdata['title']}" />
                    <br>
                    <label for="tdesc">Description:</label>
                    <input
                        id="tdesc"
                        name="tdesc"
                        title="Topic Description (extra information about your topic)"
                        type="text"
                        value="{$tdata['subtitle']}"
                        />
                    <br>
                    <textarea
                        name="postdata"
                        id="postdata"
                        title="Type your post here"
                        class="bbcode-editor"
                        >
                        {$this->textFormatting->blockhtml($postdata)}
                    </textarea>
                    <br><div class="postoptions">
                        {$pollForm}
                        {$uploadButton}
                        <div class="buttons">
                            <input type="submit" name="submit"
                                value="Post New Topic"
                                title="Submit your post"
                                onclick="this.form.submitButton=this;"
                                id="submitbutton">
                            <input
                                type="submit"
                                name="submit"
                                value="Preview"
                                title="See a preview of your post"
                                onclick="this.form.submitButton=this">
                            </div>
                    </div>
                </form>
                HTML;
            $page .= $this->page->meta('box', '', $forum['title'] . ' > New Topic', $form);
        }

        $this->page->append('page', $page);
        $this->page->JS('update', 'page', $page);

        if ($error !== null) {
            return;
        }

        if ($forum['perms']['upload']) {
            $this->page->JS('attachfiles');
        }

        $this->page->JS('SCRIPT', "document.querySelector('#pollchoices').style.display='none'");
    }

    public function showpostform(): void
    {
        $page = '';
        $tid = $this->tid;
        if ($this->request->isJSUpdate() && $this->how !== 'qreply') {
            return;
        }

        if (!$this->user->isGuest() && $this->how === 'qreply') {
            $this->page->JS('closewindow', '#qreply');
        }

        if ($tid) {
            $result = $this->database->safespecial(
                <<<'SQL'
                    SELECT
                        t.`title` AS `title`,
                        f.`perms` AS `perms`
                    FROM %t t
                    LEFT JOIN %t f
                        ON t.`fid`=f.`id`
                    WHERE t.`id`=?
                    SQL
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

            $tdata['title'] = $this->textFormatting->wordfilter($tdata['title']);
            $tdata['perms'] = $this->user->parseForumPerms($tdata['perms']);
        }

        $page .= '<div id="post-preview">' . $this->postpreview . '</div>';
        $postdata = $this->textFormatting->blockhtml($this->postdata);
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
            $this->session->getVar('multiquote')
        ) {
            $postdata = '';

            $result = $this->database->safespecial(
                <<<'SQL'
                    SELECT
                        m.`display_name` AS `name`,
                        p.`post` AS `post`
                    FROM %t p
                    LEFT JOIN %t m
                        ON p.`auth_id`=m.`id`
                    WHERE p.`id` IN (?)
                    SQL
                ,
                ['posts', 'members'],
                $this->session->getVar('multiquote'),
            );

            while ($postRow = $this->database->arow($result)) {
                $postdata .= '[quote=' . $postRow['name'] . ']' . $postRow['post'] . '[/quote]' . PHP_EOL;
            }

            $this->session->deleteVar('multiquote');
        }

        $uploadForm = $tdata['perms']['upload'] ? <<<'HTML'
            <div id="attachfiles">
                Add Files
                <input type="file" name="Filedata" title="Browse for file" />
            </div>
            HTML : '';

        $form = <<<HTML
            <div class="postform">
                <form method="post" data-ajax-form="true"
                    onsubmit="if(this.submitButton.value.match(/post/i)) this.submitButton.disabled=true;"
                    enctype="multipart/form-data"
                    >
                    {$vars}
                    <textarea
                        name="postdata" id="post" title="Type your post here" class="bbcode-editor"
                        >{$postdata}</textarea>
                    <br>
                    {$uploadForm}
                    <div class="buttons">
                        <input type="submit" name="submit"  id="submitbutton"
                            value="Post" title="Submit your post"
                            onclick="this.form.submitButton=this"
                            />
                        <input type="submit" name="submit" value="Preview"
                            title="See a preview of your post"
                            onclick="this.form.submitButton=this"/>
                    </div>
                </form>
            </div>
            HTML;

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
        if (
            $post['auth_id']
            && ($post['newtopic'] ? $this->user->getPerm('can_edit_topics')
            : $this->user->getPerm('can_edit_posts'))
            && $post['auth_id'] === $this->user->get('id')
        ) {
            return true;
        }

        return (bool) $this->canmoderate($post['tid']);
    }

    public function canmoderate($tid)
    {
        if ($this->canmod) {
            return $this->canmod;
        }

        $canmod = false;
        if ($this->user->getPerm('can_moderate')) {
            $canmod = true;
        }

        if ($this->user->get('mod')) {
            $result = $this->database->safespecial(
                'SELECT mods FROM %t WHERE id=(SELECT fid FROM %t WHERE id=?)',
                ['forums', 'topics'],
                $this->database->basicvalue($tid),
            );
            $mods = $this->database->arow($result);
            $this->database->disposeresult($result);
            if (in_array($this->user->get('id'), explode(',', (string) $mods['mods']))) {
                $canmod = true;
            }
        }

        return $this->canmod = $canmod;
    }

    public function editpost(): ?bool
    {
        $pid = $this->pid;
        $tid = $this->tid;
        $error = '';
        $errorditingpost = false;
        if (!$pid || !is_numeric($pid)) {
            $error = 'Invalid post to edit.';
        }

        if ($this->postdata) {
            if (!$this->nopost && trim((string) $this->postdata) === '') {
                $error = "You didn't supply a post!";
            } elseif (mb_strlen((string) $this->postdata) > 65535) {
                $error = 'Post must not exceed 65,535 bytes.';
            }
        }

        if ($error === '' || $error === '0') {
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
                $error = 'The post you are trying to edit does not exist.';
            } elseif (!$this->canedit($post)) {
                $error = "You don't have permission to edit that post!";
            } elseif ($this->postdata === null) {
                $errorditingpost = true;
                $this->postdata = $post['post'];
            }
        }

        if ($tid && !$error) {
            if (!is_numeric($tid) || !$tid) {
                $error = 'Invalid post to edit.';
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
                    $error = "The topic you are trying to edit doesn't exist.";
                } elseif (trim((string) $this->request->post('ttitle')) === '') {
                    $error = 'You must supply a topic title!';
                } else {
                    $this->database->safeupdate(
                        'topics',
                        [
                            'subtitle' => $this->textFormatting->blockhtml($this->request->post('tdesc')),
                            'summary' => mb_substr(
                                (string) preg_replace(
                                    '@\s+@',
                                    ' ',
                                    $this->textFormatting->wordfilter(
                                        $this->textFormatting->blockhtml(
                                            $this->textFormatting->textonly(
                                                $this->postdata,
                                            ),
                                        ),
                                    ),
                                ),
                                0,
                                50,
                            ),
                            'title' => $this->textFormatting->blockhtml($this->request->post('ttitle')),
                        ],
                        'WHERE `id`=?',
                        $tid,
                    );
                }
            }
        }

        if ($error !== '' && $error !== '0') {
            $this->page->JS('error', $error);
            $this->page->append('PAGE', $this->page->error($error));
        }

        if ($error || $errorditingpost) {
            $this->showpostform();

            return false;
        }

        $this->database->safeupdate(
            'posts',
            [
                'editby' => $this->user->get('id'),
                'edit_date' => $this->database->datetime(),
                'post' => $this->postdata,
            ],
            'WHERE `id`=?',
            $this->database->basicvalue($pid),
        );
        $this->page->JS(
            'update',
            "#pid_{$pid} .post_content",
            $this->textFormatting->theworks($this->postdata),
        );
        $this->page->JS('softurl');

        return null;
    }

    public function submitpost(): ?bool
    {
        $this->session->act();
        $tid = $this->tid;
        $fid = $this->fid;
        $postdata = $this->postdata;
        $fdata = false;
        $newtopic = false;
        $postDate = $this->database->datetime();
        $uid = $this->user->get('id');
        $error = '';

        if (!$this->nopost && trim((string) $postdata) === '') {
            $error = "You didn't supply a post!";
        } elseif (mb_strlen((string) $postdata) > 50000) {
            $error = 'Post must not exceed 50,000 characters.';
        }

        if (!$error && $this->how === 'newtopic') {
            if (!$fid || !is_numeric($fid)) {
                $error = 'No forum specified exists.';
            } elseif (
                trim($this->request->post('ttitle') ?? '') === ''
            ) {
                $error = "You didn't specify a topic title!";
            } elseif (
                mb_strlen($this->request->post('ttitle') ?? '') > 255
            ) {
                $error = 'Topic title must not exceed 255 characters';
            } elseif (
                mb_strlen($this->request->post('subtitle') ?? '') > 255
            ) {
                $error = 'Subtitle must not exceed 255 characters';
            } elseif (
                $this->request->post('poll_type') !== null
            ) {
                $pollchoices = [];
                $pollChoice = preg_split("@[\r\n]+@", (string) $this->request->post('pollchoices'));
                foreach ($pollChoice as $v) {
                    if (trim($v) === '') {
                        continue;
                    }

                    if (trim($v) === '0') {
                        continue;
                    }

                    $pollchoices[] = $this->textFormatting->blockhtml($v);
                }

                if (trim((string) $this->request->post('pollq')) === '') {
                    $error = "You didn't specify a poll question!";
                } elseif (count($pollchoices) > 10) {
                    $error = 'Poll choices must not exceed 10.';
                } elseif ($pollchoices === []) {
                    $error = "You didn't provide any poll choices!";
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
                $error = "The forum you're trying to post in does not exist.";
            } else {
                $fdata['perms'] = $this->user->parseForumPerms($fdata['perms']);
                if (!$fdata['perms']['start']) {
                    $error = "You don't have permission to post a new topic in that forum.";
                }

                if (
                    (
                        $this->request->post('poll_type') !== null
                        || $this->request->post('pollq') !== null
                    ) && !$fdata['perms']['poll']
                ) {
                    $error = "You don't have permission to post a poll in that forum";
                }
            }

            if ($error === '' || $error === '0') {
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
                        'poll_q' => $this->request->post('pollq') !== null
                            ? $this->textFormatting->blockhtml($this->request->post('pollq'))
                            : '',
                        'poll_type' => $this->request->post('poll_type') ?? '',
                        'replies' => 0,
                        'subtitle' => $this->textFormatting->blockhtml($this->request->post('tdesc')),
                        'summary' => mb_substr(
                            (string) preg_replace(
                                '@\s+@',
                                ' ',
                                $this->textFormatting->blockhtml(
                                    $this->textFormatting->textonly(
                                        $this->postdata,
                                    ),
                                ),
                            ),
                            0,
                            50,
                        ),
                        'title' => $this->textFormatting->blockhtml($this->request->post('ttitle')),
                        'views' => 0,
                    ],
                );
                $tid = $this->database->insertId();
            }

            $newtopic = true;
        }

        if ($error !== '' && $error !== '0') {
            $this->page->append('PAGE', $this->page->error($error));
            $this->page->JS('error', $error);
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
                <<<'SQL'
                    SELECT
                        t.`title` AS `topictitle`,
                        f.`id` AS `id`,
                        f.`path` AS `path`,
                        f.`perms` AS `perms`,
                        f.`nocount` AS `nocount`,
                        t.`locked` AS `locked`
                    FROM %t t
                    LEFT JOIN %t f
                        ON t.`fid`=f.`id`
                        WHERE t.`id`=?
                    SQL
                ,
                ['topics', 'forums'],
                $tid,
            );
            $fdata = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        if (!$fdata) {
            $error = "The topic you're trying to reply to does not exist.";
            $this->page->append('PAGE', $this->page->error($error));
            $this->page->JS('error', $error);

            return false;
        }

        $fdata['perms'] = $this->user->parseForumPerms($fdata['perms']);
        if (
            !$fdata['perms']['reply']
            || $fdata['locked']
            && !$this->user->getPerm('can_override_locked_topics')
        ) {
            $error = "You don't have permission to post here.";
            $this->page->append('PAGE', $this->page->error($error));
            $this->page->JS('error', $error);

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

        $pid = $this->database->insertId();
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
                <<<'SQL'
                    UPDATE %t
                    SET `lp_uid` = ?, `lp_date` = ?, `replies` = `replies` + 1
                    WHERE `id`=?
                    SQL
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
                <<<'SQL'
                    UPDATE %t
                    SET
                        `lp_uid`=?,
                        `lp_tid`=?,
                        `lp_topic`=?,
                        `lp_date`=?,
                        `topics`=`topics`+1
                    WHERE `id` IN ?
                    SQL
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
                <<<'SQL'
                    UPDATE %t
                    SET
                        `lp_uid`=?,
                        `lp_tid`=?,
                        `lp_topic`=?,
                        `lp_date`=?,
                        `posts`=`posts`+1
                    WHERE `id` IN (?)
                    SQL
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
            $this->user->set('posts', $this->user->get('posts') + 1);
        }

        if ($newtopic) {
            $this->database->safespecial(
                <<<'SQL'
                    UPDATE %t
                    SET
                        `posts`=`posts` + 1,
                        `topics`=`topics` + 1
                    SQL
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
