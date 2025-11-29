<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\DomainDefinitions;
use Jax\Hooks;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Models\Activity;
use Jax\Models\File;
use Jax\Models\Forum;
use Jax\Models\Member;
use Jax\Models\Post as ModelsPost;
use Jax\Models\Stats;
use Jax\Models\Topic;
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
    private ?string $postData = null;

    private string $postpreview = '';

    private int $tid;

    private int $fid;

    private int $pid;

    private ?string $how = null;

    public function __construct(
        private readonly Database $database,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly Hooks $hooks,
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
        $this->tid = (int) $this->request->asString->both('tid');
        $this->fid = (int) $this->request->asString->both('fid');
        $this->pid = (int) $this->request->asString->both('pid');
        $this->how = $this->request->asString->both('how');
        $submit = $this->request->asString->post('submit');
        $fileData = $this->request->file('Filedata');
        $postData = $this->request->asString->post('postdata');

        // Nothing updates on this page
        if ($this->request->isJSUpdate()) {
            return;
        }

        if ($postData !== null) {
            [$postData, $codes] = $this->textFormatting->startCodeTags($postData);
            $postData = $this->textFormatting->linkify($postData);
            $postData = $this->textFormatting->finishCodeTagsBB($postData, $codes);
            $this->postData = $postData;
        }

        if ($fileData !== null && $this->user->getGroup()?->canAttach) {
            $attachmentId = $this->upload($fileData);
            $this->postData .= "\n\n[attachment]{$attachmentId}[/attachment]";
        }

        $error = match (true) {
            $submit === 'Preview' || $submit === 'Full Reply' => $this->previewPost(),
            (bool) $this->pid && $this->how === 'edit' => $this->editPost(),
            $this->how === 'newtopic' => $this->createTopic(),
            $this->postData !== null => $this->submitPost($this->tid),
            (bool) $this->fid => $this->showTopicForm(),
            (bool) $this->tid => $this->showPostForm(),
            default => $this->page->location('?'),
        };

        if ($error !== null) {
            $this->page->command('error', $error);
            $this->page->append('PAGE', $this->page->error($error));

            return;
        }
    }

    /**
     * 1) Compute a hash of the file to use as the filename on the server
     * 2) If it's an image, keep the extension so we can show it. Otherwise remove it.
     * 3) If the file has already been uploaded (based on hash) then don't replace it.
     *
     * @param array<string,mixed> $fileobj
     *
     * @return string file ID
     */
    private function upload(array $fileobj): string
    {
        $uid = $this->user->get()->id;

        $size = (int) filesize($fileobj['tmp_name']);
        $hash = hash_file('sha1', $fileobj['tmp_name']) ?: 'hash_error';
        $uploadPath = $this->domainDefinitions->getBoardPath() . '/Uploads/';

        $ext = pathinfo((string) $fileobj['name'], PATHINFO_EXTENSION);

        $imageExtension = in_array($ext, Jax::IMAGE_EXTENSIONS, true)
            ? ".{$ext}"
            : null;

        $filePath = $uploadPath . $hash . $imageExtension;

        if (!is_file($filePath)) {
            move_uploaded_file($fileobj['tmp_name'], $filePath);

            $file = new File();
            $file->hash = $hash;
            $file->ip = $this->ipAddress->asBinary() ?? '';
            $file->name = $fileobj['name'];
            $file->size = $size;
            $file->uid = $uid;
            $file->insert();

            return (string) $file->id;
        }

        $fileRecord = File::selectOne('WHERE `hash`=?', $hash);
        if ($fileRecord === null) {
            return '';
        }

        return (string) $fileRecord->id;
    }

    private function previewPost(): null
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

        return null;
    }

    private function showTopicForm(?Topic $topic = null): null
    {
        $postData = $this->postData;
        $page = '<div id="post-preview">' . $this->postpreview . '</div>';
        $tid = $topic->id ?? '';
        $fid = $topic->fid ?? $this->fid;
        $how = $this->how ?? 'newtopic';

        $isEditing = (bool) $topic;

        $forum = Forum::selectOne($fid);

        if ($forum === null) {
            $this->page->location('?');

            return null;
        }

        if ($topic === null) {
            $topic = new Topic();
            $topic->subtitle = $this->request->asString->post('tdesc') ?? '';
            $topic->title = $this->request->asString->post('ttitle') ?? '';
        }

        $forumPerms = $this->user->getForumPerms($forum->perms);

        $pollForm = $forumPerms['poll'] ? <<<'HTML'
            <label class="addpoll" for="addpoll">
                Add a Poll
            </label>
            <select name="pollType" title="Add a poll"
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

        $uploadButton = $forumPerms['upload']
            ? ''
            : <<<'HTML'
                <div id="attachfiles" class="addfile">
                    Add Files <input type="file" name="Filedata" title="Browse for file" />
                </div>
                HTML;

        $submitLabel = $isEditing ? 'Edit Topic' : 'Post New Topic';
        $form = <<<HTML
            <form method="post" data-ajax-form="true"
                            onsubmit="if(this.submitButton.value.match(/post/i)) this.submitButton.disabled=true;">
            <div class="topicform">
                <input type="hidden" name="act" value="post" />
                <input type="hidden" name="how" value="{$how}" />
                <input type="hidden" name="fid" value="{$fid}" />
                <input type="hidden" name="tid" value="{$tid}" />
                <label for="ttitle">Topic title:</label>
                <input type="text" name="ttitle" id="ttitle" title="Topic Title" value="{$topic->title}" />
                <br>
                <label for="tdesc">Description:</label>
                <input
                    id="tdesc"
                    name="tdesc"
                    title="Topic Description (extra information about your topic)"
                    type="text"
                    value="{$topic->subtitle}"
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
                            value="{$submitLabel}"
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
        $page .= $this->template->meta('box', '', $forum->title . ' > New Topic', $form);

        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);

        if ($forumPerms['upload']) {
            $this->page->command('attachfiles');
        }

        $this->page->command('SCRIPT', "document.querySelector('#pollchoices').style.display='none'");

        return null;
    }

    private function showPostForm(): ?string
    {
        $page = '';
        $tid = $this->tid;
        if ($this->request->isJSUpdate()) {
            return null;
        }

        if (!$this->user->isGuest() && $this->how === 'qreply') {
            $this->page->command('closewindow', '#qreply');
        }

        $topic = Topic::selectOne($tid);
        if ($topic === null) {
            return "The topic you're attempting to reply in no longer exists.";
        }

        $forum = Forum::selectOne($topic->fid);
        if ($forum === null) {
            return "The forum you're attempting to reply to no longer exists.";
        }

        $topic->title = $this->textFormatting->wordfilter($topic->title);
        $topicPerms = $this->user->getForumPerms($forum->perms);

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

        if ($this->session->getVar('multiquote')) {
            $postData = '';

            $posts = ModelsPost::selectMany(
                Database::WHERE_ID_IN,
                explode(',', (string) $this->session->getVar('multiquote')),
            );

            $membersById = Member::joinedOn(
                $posts,
                static fn(ModelsPost $modelsPost): int => $modelsPost->author,
            );

            foreach ($posts as $post) {
                $authorName = $membersById[$post->author]->name ?? '';
                $postData .= "[quote={$authorName}]{$post->post}[/quote]" . PHP_EOL;
            }

            $this->session->deleteVar('multiquote');
        }

        $uploadForm = $topicPerms['upload'] ? <<<'HTML'
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

        $page .= $this->template->meta('box', '', $topic->title . ' &gt; Reply', $form);
        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
        if (!$topicPerms['upload']) {
            $this->page->command('attachfiles');
        }

        return null;
    }

    private function canEdit(Topic $topic, ModelsPost $modelsPost): bool
    {
        if (
            $modelsPost->author
            && ($modelsPost->newtopic !== 0 ? $this->user->getGroup()?->canEditTopics
                : $this->user->getGroup()?->canEditPosts)
            && $modelsPost->author === $this->user->get()->id
        ) {
            return true;
        }

        return $this->canModerate($topic);
    }

    private function canModerate(Topic $topic): bool
    {
        if ($this->user->getGroup()?->canModerate) {
            return true;
        }

        if ($this->user->get()->mod !== 0) {
            $forum = Forum::selectOne($topic);

            if (
                $forum
                && in_array((string) $this->user->get()->id, explode(',', $forum->mods), true)
            ) {
                return true;
            }
        }

        return false;
    }

    private function validatePost(?string $postData): ?string
    {
        return match (true) {
            $postData !== null && trim($postData) === '' => "You didn't supply a post!",
            $this->postData && mb_strlen($this->postData) > 65_535 => 'Post must not exceed 65,535 characters.',
            default => null,
        };
    }

    private function updatePost(
        ModelsPost $modelsPost,
        ?string $postData,
    ): ?string {
        $error = $this->validatePost($postData);
        if ($error) {
            return $error;
        }

        $modelsPost->editby = $this->user->get()->id;
        $modelsPost->editDate = $this->database->datetime();
        $modelsPost->post = $this->postData ?? '';
        $modelsPost->update();

        $this->page->command(
            'update',
            "#pid_{$modelsPost->id} .post_content",
            $this->textFormatting->theWorks($this->postData ?? ''),
        );
        $this->page->command('softurl');

        return null;
    }

    private function updateTopic(Topic $topic): ?string
    {
        $topicTitle = $this->request->asString->post('ttitle');
        $topicDesc = $this->request->asString->post('tdesc');

        $error = match (true) {
            $topicTitle === null || trim($topicTitle) === '' => 'You must supply a topic title!',
            default => null,
        };

        if ($error) {
            return $error;
        }

        $topic->subtitle = $this->textFormatting->blockhtml($topicDesc ?? '');
        $topic->summary = mb_substr(
            (string) preg_replace(
                '@\s+@',
                ' ',
                $this->textFormatting->wordfilter(
                    $this->textFormatting->blockhtml(
                        $this->textFormatting->textOnly(
                            $this->postData ?? '',
                        ),
                    ),
                ),
            ),
            0,
            50,
        );
        $topic->update();

        return null;
    }

    private function editPost(): ?string
    {
        $pid = $this->pid;
        $postData = $this->postData;

        $post = ModelsPost::selectOne($pid);
        $topic = $post !== null
            ? Topic::selectOne($post->tid)
            : null;

        $isTopicPost = $topic && $post && $topic->op === $post->id;

        if ($post === null || $topic === null) {
            return 'The post you are trying to edit does not exist.';
        }

        if (!$this->canEdit($topic, $post)) {
            return "You don't have permission to edit that post!";
        }

        if ($this->request->post('submit') !== null) {
            // Update topic when editing topic
            $error = $this->updatePost($post, $postData);
            if (!$error && $isTopicPost) {
                $error = $this->updateTopic($topic);
            }

            if ($error !== null) {
                return $error;
            }

            $this->page->location("?act=vt{$post->tid}&findpost={$pid}");

            return null;
        }

        if ($this->postData === null) {
            $this->postData = $post->post;
        }

        if ($isTopicPost) {
            $this->showTopicForm($topic);

            return null;
        }

        $this->showPostForm();

        return null;
    }

    private function createTopic(): null
    {
        $fid = $this->fid;
        $uid = $this->user->get()->id;
        $postDate = $this->database->datetime();

        $inputPollChoices = $this->request->asString->post('pollchoices');
        $topicTitle = $this->request->asString->post('ttitle');
        $topicDescription = $this->request->asString->post('tdesc');
        $subTitle = $this->request->asString->post('subtitle');
        $pollQuestion = $this->request->asString->post('pollq');
        $pollChoices = $inputPollChoices !== null ? array_map(
            fn($line): string => $this->textFormatting->blockhtml($line),
            array_filter(
                preg_split("@[\r\n]+@", $inputPollChoices) ?: [],
                static fn($line): bool => trim($line) !== '',
            ),
        ) : [];
        $pollType = $this->request->asString->post('pollType');

        $forum = Forum::selectOne($fid);

        $forumPerms = $forum !== null
            ? $this->user->getForumPerms($forum->perms)
            : [];

        // New topic input validation
        $error = match (true) {
            !$forum => "The forum you're trying to post in does not exist.",
            !$forumPerms['start'] => "You don't have permission to post a new topic in that forum.",
            !$topicTitle || trim($topicTitle) === '' => "You didn't specify a topic title!",
            mb_strlen($topicTitle) > 255 => 'Topic title must not exceed 255 characters',
            mb_strlen($subTitle ?? '') > 255 => 'Subtitle must not exceed 255 characters',
            default => null,
        };

        // Post validation
        $error ??= $this->validatePost($this->postData);

        // Poll input validation
        $error ??= match (true) {
            !$pollType => null,
            $pollQuestion === null || trim($pollQuestion) === '' => "You didn't specify a poll question!",
            count($pollChoices) > 10 => 'Poll choices must not exceed 10.',
            $pollChoices === [] => "You didn't provide any poll choices!",
            $forum && !$forumPerms['poll'] => "You don't have permission to post a poll in that forum",
            default => null,
        };

        if ($error !== null) {
            // Handle error here so we can still show topic form
            $this->page->append('PAGE', $this->page->error($error));
            $this->page->command('error', $error);
            $this->page->command('enable', 'submitbutton');
            $this->showTopicForm();

            return null;
        }

        $topic = new Topic();
        $topic->author = $uid;
        $topic->date = $postDate;
        $topic->fid = $fid;
        $topic->lastPostDate = $postDate;
        $topic->lastPostUser = $uid;
        $topic->pollChoices = $pollChoices !== []
                    ? (json_encode($pollChoices) ?: '')
                    : '';
        $topic->pollQuestion = $pollQuestion !== null
                    ? $this->textFormatting->blockhtml($pollQuestion)
                    : '';
        $topic->pollType = $pollType ?? '';
        $topic->replies = 0;
        $topic->subtitle = $this->textFormatting->blockhtml($topicDescription ?? '');
        $topic->summary = mb_substr(
            (string) preg_replace(
                '@\s+@',
                ' ',
                $this->textFormatting->blockhtml(
                    $this->textFormatting->textOnly(
                        $this->postData ?? '',
                    ),
                ),
            ),
            0,
            50,
        );
        $topic->title = $this->textFormatting->blockhtml($topicTitle ?? '');
        $topic->views = 0;
        $topic->insert();

        $this->submitPost($topic->id, true);

        return null;
    }

    private function submitPost(int $tid, bool $newtopic = false): ?string
    {
        $this->session->act();
        $postData = $this->postData;
        $postDate = $this->database->datetime();
        $uid = $this->user->get()->id;

        // Post validation
        $error = $this->validatePost($postData);

        if ($error !== null) {
            // Handle error here to show post form
            $this->page->append('PAGE', $this->page->error($error));
            $this->page->command('error', $error);
            $this->page->command('enable', 'submitbutton');
            $this->showPostForm();

            return null;
        }

        $topic = Topic::selectOne($tid);
        $forum = $topic !== null
            ? Forum::selectOne($topic->fid)
            : null;

        if ($topic === null || $forum === null) {
            return "The topic you're trying to reply to does not exist.";
        }

        $forumPerms = $this->user->getForumPerms($forum->perms);

        if (
            ($this->how !== 'newtopic' && !$forumPerms['reply'])
            || ($topic->locked && !$this->user->getGroup()?->canOverrideLockedTopics)
        ) {
            return "You don't have permission to post here.";
        }

        // Actually PUT THE POST IN!
        $post = new ModelsPost();
        $post->author = $uid;
        $post->date = $postDate;
        $post->ip = $this->ipAddress->asBinary() ?? '';
        $post->newtopic = $newtopic ? 1 : 0;
        $post->post = $postData ?? '';
        $post->tid = $tid;
        $post->insert();

        $this->hooks->dispatch('post', $post);

        // Set op.
        if ($newtopic) {
            $topic->op = $post->id;
            $topic->update();
        }

        $activity = new Activity();
        $activity->arg1 = $topic->title;
        $activity->date = $postDate;
        $activity->pid = $post->id;
        $activity->tid = $tid;
        $activity->type = $newtopic ? 'new_topic' : 'new_post';
        $activity->uid = $uid;
        $activity->insert();

        // Update last post info
        // for the topic.
        if (!$newtopic) {
            $topic->lastPostUser = $uid;
            $topic->lastPostDate = $postDate;
            ++$topic->replies;
            $topic->update();
        }

        // Do some magic to update the tree all the way up (for subforums).
        $path = trim($forum->path) !== ''
            ? explode(' ', $forum->path)
            : [];
        if (!in_array($topic->fid, $path)) {
            $path[] = $topic->fid;
        }

        if ($newtopic) {
            $this->database->special(
                <<<'SQL'
                    UPDATE %t
                    SET
                        `lastPostUser`=?,
                        `lastPostTopic`=?,
                        `lastPostTopicTitle`=?,
                        `lastPostDate`=?,
                        `topics`=`topics`+1
                    WHERE `id` IN ?
                    SQL,
                ['forums'],
                $uid,
                $tid,
                $topic->title,
                $postDate,
                $path,
            );
        } else {
            $this->database->special(
                <<<'SQL'
                    UPDATE %t
                    SET
                        `lastPostUser`=?,
                        `lastPostTopic`=?,
                        `lastPostTopicTitle`=?,
                        `lastPostDate`=?,
                        `posts`=`posts`+1
                    WHERE `id` IN ?
                    SQL,
                ['forums'],
                $uid,
                $tid,
                $topic->title,
                $postDate,
                $path,
            );
        }

        // Update statistics.
        if ($forum->nocount === 0) {
            $this->user->set('posts', $this->user->get()->posts + 1);
        }

        $stats = Stats::selectOne();
        if ($stats !== null) {
            ++$stats->posts;
            if ($newtopic) {
                ++$stats->topics;
            }

            $stats->update();
        }

        if ($this->how === 'qreply') {
            $this->page->command('closewindow', '#qreply');
            $this->page->command('refreshdata');
        } else {
            $this->page->location('?act=vt' . $tid . '&getlast=1');
        }

        return null;
    }
}
