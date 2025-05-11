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
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_filter;
use function array_map;
use function count;
use function explode;
use function filesize;
use function hash_file;
use function in_array;
use function is_file;
use function is_string;
use function json_encode;
use function mb_strlen;
use function mb_substr;
use function move_uploaded_file;
use function pathinfo;
use function preg_replace;
use function preg_split;
use function trim;

use const PATHINFO_EXTENSION;
use const PHP_EOL;

final class Post
{
    private bool $canmod = false;

    private ?string $postData = null;

    private string $postpreview = '';

    private int $tid;

    private int $fid;

    private int $pid;

    private ?string $how = null;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly Page $page,
        private readonly IPAddress $ipAddress,
        private readonly Request $request,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly Template $template,
        private readonly User $user,
    ) {
        $this->template->addMeta('post-preview', $this->template->meta('box', '', 'Post Preview', '%s'));
    }

    public function render(): void
    {
        $how = $this->request->both('how');
        $this->tid = (int) $this->request->both('tid');
        $this->fid = (int) $this->request->both('fid');
        $this->pid = (int) $this->request->both('pid');
        $this->how = is_string($how) ? $how : null;
        $submit = $this->request->post('submit');
        $fileData = $this->request->file('Filedata');
        $postData = $this->request->post('postdata');

        // Nothing updates on this page
        if ($this->request->isJSUpdate()) {
            return;
        }

        if (is_string($postData)) {
            [$postData, $codes] = $this->textFormatting->startCodeTags($postData);
            $postData = $this->textFormatting->linkify($postData);
            $postData = $this->textFormatting->finishCodeTagsBB($postData, $codes);
            $this->postData = $postData;
        }

        if ($fileData !== null && $this->user->getPerm('can_attach')) {
            $attachmentId = $this->upload($fileData);
            $this->postData .= "\n\n[attachment]{$attachmentId}[/attachment]";
        }

        match (true) {
            $submit === 'Preview' || $submit === 'Full Reply' => $this->previewPost(),
            (bool) $this->pid && $this->how === 'edit' => $this->editPost(),
            $this->postData !== null => $this->submitPost(),
            $this->fid || $this->tid && $this->how === 'edit' => $this->showTopicForm(),
            (bool) $this->tid => $this->showPostForm(),
            default => $this->page->location('?'),
        };
    }

    /**
     * 1) Compute a hash of the file to use as the filename on the server
     * 2) If it's an image, keep the extension so we can show it. Otherwise remove it.
     * 3) If the file has already been uploaded (based on hash) then don't replace it.
     *
     * @param array{tmp_name:string,name:string} $fileobj
     *
     * @return int|string file ID from the files table, or string on failure
     */
    private function upload(array $fileobj): int|string
    {
        $uid = $this->user->get('id');

        $size = (int) filesize($fileobj['tmp_name']);
        $hash = hash_file('sha512', $fileobj['tmp_name']) ?: 'hash_error';
        $uploadPath = $this->domainDefinitions->getBoardPath() . '/Uploads/';

        $ext = pathinfo($fileobj['name'], PATHINFO_EXTENSION);

        $imageExtension = in_array($ext, $this->config->getSetting('images') ?? [], true)
            ? ".{$ext}"
            : null;

        $file = $uploadPath . $hash . $imageExtension;

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

            $attachmentId = $this->database->insertId();
            if ($attachmentId) {
                return $attachmentId;
            }
        }

        $result = $this->database->safeselect(
            ['id'],
            'files',
            'WHERE `hash`=?',
            $hash,
        );
        $fileRecord = $this->database->arow($result);
        if (!$fileRecord) {
            return '';
        }

        $id = (string) $fileRecord['id'];
        $this->database->disposeresult($result);

        return $id;
    }

    private function previewPost(): void
    {
        $post = $this->postData ?? '';
        if (trim($post) !== '') {
            $post = $this->textFormatting->theWorks($post);
            $post = $this->template->meta('post-preview', $post);
            $this->postpreview = $post;
        }

        if (!$this->request->isJSAccess() || $this->how === 'qreply') {
            $this->showPostForm();
        }

        $this->page->command('update', 'post-preview', $post);
    }

    private function showTopicForm(): void
    {
        $error = null;
        if ($this->request->isJSUpdate()) {
            return;
        }

        $postData = $this->postData;
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
                Database::WHERE_ID_EQUALS,
                $this->database->basicvalue($this->tid),
            );
            $topic = $this->database->arow($result);
            $this->database->disposeresult($result);

            if (!$topic) {
                $error = "The topic you're trying to edit does not exist.";
            } else {
                $result = $this->database->safeselect(
                    ['post'],
                    'posts',
                    Database::WHERE_ID_EQUALS,
                    $this->database->basicvalue($topic['op']),
                );
                $postData = $this->database->arow($result);
                $this->database->disposeresult($result);

                if ($postData) {
                    $postData = $postData['post'];
                }
            }
        }

        $result = $this->database->safeselect(
            ['title', 'perms'],
            'forums',
            Database::WHERE_ID_EQUALS,
            $fid,
        );
        $forum = $this->database->arow($result);
        $this->database->disposeresult($result);

        if ($forum === null) {
            $error = "This forum doesn't exist. Weird.";
        }

        if ($error !== null) {
            $page .= $this->template->meta('error', $error);
        } else {
            if (!isset($topic)) {
                $topic = [
                    'subtitle' => '',
                    'title' => '',
                ];
            }

            $forum['perms'] = $this->user->getForumPerms($forum['perms']);

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
                    <input type="text" name="ttitle" id="ttitle" title="Topic Title" value="{$topic['title']}" />
                    <br>
                    <label for="tdesc">Description:</label>
                    <input
                        id="tdesc"
                        name="tdesc"
                        title="Topic Description (extra information about your topic)"
                        type="text"
                        value="{$topic['subtitle']}"
                        />
                    <br>
                    <textarea
                        name="postdata"
                        id="postdata"
                        title="Type your post here"
                        class="bbcode-editor"
                        >{$this->textFormatting->blockhtml($postData ?? '')}</textarea>
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
            $page .= $this->template->meta('box', '', $forum['title'] . ' > New Topic', $form);
        }

        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);

        if ($error !== null) {
            return;
        }

        if ($forum['perms']['upload']) {
            $this->page->command('attachfiles');
        }

        $this->page->command('SCRIPT', "document.querySelector('#pollchoices').style.display='none'");
    }

    private function showPostForm(): void
    {
        $page = '';
        $tid = $this->tid;
        if ($this->request->isJSUpdate() && $this->how !== 'qreply') {
            return;
        }

        if (!$this->user->isGuest() && $this->how === 'qreply') {
            $this->page->command('closewindow', '#qreply');
        }

        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT
                    t.`title` AS `title`,
                    f.`perms` AS `perms`
                FROM %t t
                LEFT JOIN %t f
                    ON t.`fid`=f.`id`
                WHERE t.`id`=?
                SQL,
            ['topics', 'forums'],
            $this->database->basicvalue($tid),
        );
        $topic = $this->database->arow($result);
        $this->database->disposeresult($result);
        if (!$topic) {
            $page .= $this->template->meta(
                'error',
                "The topic you're attempting to reply in no longer exists.",
            );
        } else {
            $topic['title'] = $this->textFormatting->wordfilter($topic['title']);
            $topic['perms'] = $this->user->getForumPerms($topic['perms']);
        }

        $page .= '<div id="post-preview">' . $this->postpreview . '</div>';
        $postData = $this->textFormatting->blockhtml($this->postData ?? '');
        $varsarray = [
            'act' => 'post',
            'how' => 'fullpost',
        ];
        if ($this->pid !== 0) {
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
            $postData = '';

            $result = $this->database->safespecial(
                <<<'SQL'
                    SELECT
                        m.`display_name` AS `name`,
                        p.`post` AS `post`
                    FROM %t p
                    LEFT JOIN %t m
                        ON p.`auth_id`=m.`id`
                    WHERE p.`id` IN (?)
                    SQL,
                ['posts', 'members'],
                $this->session->getVar('multiquote'),
            );

            while ($postRow = $this->database->arow($result)) {
                $postData .= '[quote=' . $postRow['name'] . ']' . $postRow['post'] . '[/quote]' . PHP_EOL;
            }

            $this->session->deleteVar('multiquote');
        }

        $uploadForm = $topic['perms']['upload'] ? <<<'HTML'
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
                        >{$postData}</textarea>
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

        $page .= $this->template->meta('box', '', $topic['title'] . ' &gt; Reply', $form);
        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
        if (!$topic['perms']['upload']) {
            return;
        }

        $this->page->command('attachfiles');
    }

    /**
     * @param array<string,mixed> $post
     */
    private function canEdit(array $post): bool
    {
        if (
            $post['auth_id']
            && ($post['newtopic'] ? $this->user->getPerm('can_edit_topics')
                : $this->user->getPerm('can_edit_posts'))
            && $post['auth_id'] === $this->user->get('id')
        ) {
            return true;
        }

        return $this->canModerate($post['tid']);
    }

    private function canModerate(int $tid): bool
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
            if (
                $mods !== null
                && in_array($this->user->get('id'), explode(',', (string) $mods['mods']))
            ) {
                $canmod = true;
            }
        }

        return $this->canmod = $canmod;
    }

    private function editPost(): void
    {
        $pid = $this->pid;
        $tid = $this->tid;
        $error = null;
        $editingPost = false;
        $postData = $this->postData;
        $error = match (true) {
            !$pid => 'Invalid post to edit.',
            $postData !== null && trim($postData) === '' => "You didn't supply a post!",
            mb_strlen((string) $this->postData) > 65_535 => 'Post must not exceed 65,535 bytes.',
            default => null,
        };

        if ($error === null) {
            $result = $this->database->safeselect(
                [
                    'auth_id',
                    'newtopic',
                    'post',
                    'tid',
                ],
                'posts',
                Database::WHERE_ID_EQUALS,
                $pid,
            );
            $post = $this->database->arow($result);
            $this->database->disposeresult($result);

            $error = match (true) {
                !$post => 'The post you are trying to edit does not exist.',
                !$this->canEdit($post) => "You don't have permission to edit that post!",
                default => null,
            };

            if ($this->postData === null && $post) {
                $editingPost = true;
                $this->postData = $post['post'];
            }
        }

        if ($tid && $error === null) {
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
                Database::WHERE_ID_EQUALS,
                $tid,
            );
            $topic = $this->database->arow($result);
            $this->database->disposeresult($result);

            $inputTopicTitle = $this->request->post('ttitle');
            $topicTitle = is_string($inputTopicTitle) ? $inputTopicTitle : null;

            if (!$topic) {
                $error = "The topic you are trying to edit doesn't exist.";
            } elseif ($topicTitle === null || trim($topicTitle) === '') {
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
                                        $this->textFormatting->textOnly(
                                            $this->postData,
                                        ),
                                    ),
                                ),
                            ),
                            0,
                            50,
                        ),
                        'title' => $this->textFormatting->blockhtml($topicTitle),
                    ],
                    Database::WHERE_ID_EQUALS,
                    $tid,
                );
            }
        }

        if ($error !== null) {
            $this->page->command('error', $error);
            $this->page->append('PAGE', $this->page->error($error));
        }

        if ($error || $editingPost) {
            $this->showPostForm();

            return;
        }

        $this->database->safeupdate(
            'posts',
            [
                'editby' => $this->user->get('id'),
                'edit_date' => $this->database->datetime(),
                'post' => $this->postData,
            ],
            Database::WHERE_ID_EQUALS,
            $this->database->basicvalue($pid),
        );
        $this->page->command(
            'update',
            "#pid_{$pid} .post_content",
            $this->textFormatting->theWorks($this->postData),
        );
        $this->page->command('softurl');
    }

    private function submitPost(): void
    {
        $this->session->act();
        $tid = $this->tid;
        $fid = $this->fid;
        $postData = $this->postData;
        $fdata = false;
        $newtopic = false;
        $postDate = $this->database->datetime();
        $uid = $this->user->get('id');
        $error = null;

        if ($this->postData !== null && trim((string) $postData) === '') {
            $error = "You didn't supply a post!";
        } elseif (mb_strlen((string) $postData) > 50000) {
            $error = 'Post must not exceed 50,000 characters.';
        }

        $inputTopicTitle = $this->request->post('ttitle');
        $inputSubtitle = $this->request->post('subtitle');
        $inputPollChoices = $this->request->post('pollchoices');
        $inputPollQuestion = $this->request->post('pollq');
        $topicTitle = is_string($inputTopicTitle) ? $inputTopicTitle : null;
        $subTitle = is_string($inputSubtitle) ? $inputSubtitle : null;
        $pollChoices = is_string($inputPollChoices) ? $inputPollChoices : null;
        $pollQuestion = is_string($inputPollQuestion)
            ? $inputPollQuestion
            : null;

        if ($error === null && $this->how === 'newtopic') {
            if ($fid === 0) {
                $error = 'No forum specified exists.';
            } elseif (
                !$topicTitle || trim($topicTitle) === ''
            ) {
                $error = "You didn't specify a topic title!";
            } elseif (
                mb_strlen($topicTitle) > 255
            ) {
                $error = 'Topic title must not exceed 255 characters';
            } elseif (
                mb_strlen($subTitle ?? '') > 255
            ) {
                $error = 'Subtitle must not exceed 255 characters';
            } elseif ($this->request->post('poll_type')) {
                $pollchoices = array_map(
                    fn($line): string => $this->textFormatting->blockhtml($line),
                    array_filter(
                        preg_split("@[\r\n]+@", (string) $pollChoices),
                        static fn($line): bool => trim((string) $line) !== '',
                    ),
                );

                if ($pollQuestion === null || trim($pollQuestion) === '') {
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
                Database::WHERE_ID_EQUALS,
                $fid,
            );
            $fdata = $this->database->arow($result);
            $this->database->disposeresult($result);

            if (!$fdata) {
                $error = "The forum you're trying to post in does not exist.";
            } else {
                $fdata['perms'] = $this->user->getForumPerms($fdata['perms']);
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

            if ($error === null) {
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
                                    $this->textFormatting->textOnly(
                                        $this->postData,
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

        if ($error !== null) {
            $this->page->append('PAGE', $this->page->error($error));
            $this->page->command('error', $error);
            $this->page->command('enable', 'submitbutton');
            if ($this->how === 'newtopic') {
                $this->showTopicForm();
            } else {
                $this->showPostForm();
            }

            return;
        }

        if ($tid) {
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
                    SQL,
                ['topics', 'forums'],
                $tid,
            );
            $fdata = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        if (!$fdata) {
            $error = "The topic you're trying to reply to does not exist.";
            $this->page->append('PAGE', $this->page->error($error));
            $this->page->command('error', $error);

            return;
        }

        $fdata['perms'] = $this->user->getForumPerms($fdata['perms']);
        if (
            ($this->how !== 'newtopic' && !$fdata['perms']['reply'])
            || ($fdata['locked']
                && !$this->user->getPerm('can_override_locked_topics'))
        ) {
            $error = "You don't have permission to post here.";
            $this->page->append('PAGE', $this->page->error($error));
            $this->page->command('error', $error);

            return;
        }

        // Actually PUT THE POST IN!
        $this->database->safeinsert(
            'posts',
            [
                'auth_id' => $uid,
                'date' => $postDate,
                'ip' => $this->ipAddress->asBinary(),
                'newtopic' => $newtopic ? 1 : 0,
                'post' => $postData,
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
                Database::WHERE_ID_EQUALS,
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
                    SQL,
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
                    SQL,
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
                    WHERE `id` IN ?
                    SQL,
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
                    SQL,
                ['stats'],
            );
        } else {
            $this->database->safespecial(
                'UPDATE %t SET `posts` = `posts` + 1',
                ['stats'],
            );
        }

        if ($this->how === 'qreply') {
            $this->page->command('closewindow', '#qreply');
            $this->page->command('refreshdata');
        } else {
            $this->page->location('?act=vt' . $tid . '&getlast=1');
        }
    }
}
