<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\Database\Database;
use Jax\Hooks;
use Jax\Interfaces\Route;
use Jax\IPAddress;
use Jax\Models\Activity;
use Jax\Models\Forum;
use Jax\Models\Member;
use Jax\Models\Post as ModelsPost;
use Jax\Models\Stats;
use Jax\Models\Topic;
use Jax\OpenGraph;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Routes\Post\CreateTopic;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_key_exists;
use function explode;
use function in_array;
use function json_decode;
use function json_encode;
use function mb_strlen;
use function mb_substr;
use function preg_replace;
use function trim;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

final class Post implements Route
{
    private ?string $postData = null;

    private string $postpreview = '';

    private int $tid;

    private int $fid;

    private int $pid;

    private ?string $how = null;

    public function __construct(
        private readonly API $api,
        private readonly CreateTopic $createTopic,
        private readonly Database $database,
        private readonly Hooks $hooks,
        private readonly IPAddress $ipAddress,
        private readonly OpenGraph $openGraph,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Router $router,
        private readonly Session $session,
        private readonly Template $template,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {}

    public function route($params): void
    {
        $this->tid = (int) $this->request->asString->both('tid');
        $this->fid = (int) $this->request->asString->both('fid');
        $this->pid = (int) $this->request->asString->both('pid');
        $this->how = $this->request->asString->both('how');
        $submit = $this->request->asString->post('submit');
        $postData = $this->request->asString->post('postdata');

        // Nothing updates on this page
        if ($this->request->isJSUpdate()) {
            return;
        }

        if ($postData !== null) {
            [$postData, $codes] = $this->textFormatting->startCodeTags(
                $postData,
            );
            $postData = $this->textFormatting->linkify($postData);
            $postData = $this->textFormatting->finishCodeTagsBB(
                $postData,
                $codes,
            );
            $this->postData = $postData;
        }

        if ($this->request->file('Filedata') !== null) {
            $attachmentId = $this->api->upload();
            if ($attachmentId !== '') {
                $this->postData .= "\n\n[attachment]{$attachmentId}[/attachment]";
            }
        }

        $error = match (true) {
            $submit === 'Preview' || $submit === 'Full Reply'
                => $this->previewPost(),
            (bool) $this->pid && $this->how === 'edit' => $this->editPost(),
            $this->how === 'newtopic' => $this->createTopic(),
            $this->postData !== null => $this->createPost($this->tid),
            (bool) $this->fid => $this->createTopic->showTopicForm(),
            (bool) $this->tid => $this->showPostForm(),
            default => $this->router->redirect('index'),
        };

        if ($error !== null) {
            $this->page->command('error', $error);
            $this->page->append('PAGE', $this->page->error($error));

            return;
        }
    }

    private function previewPost(): null
    {
        $post = $this->postData ?? '';
        if (trim($post) !== '') {
            $post = $this->template->render('post/preview', ['post' => $post]);
            $this->postpreview = $post;
        }

        if (!$this->request->isJSAccess() || $this->how === 'qreply') {
            $this->showPostForm();
        }

        $this->page->command('update', 'post-preview', $post);

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

        $topic->title = $this->textFormatting->wordFilter($topic->title);
        $topicPerms = $this->user->getForumPerms($forum->perms);

        $page .= '<div id="post-preview">' . $this->postpreview . '</div>';
        $postData = $this->postData;

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
                $postData .=
                    "[quote={$authorName}]{$post->post}[/quote]" . PHP_EOL;
            }

            $this->session->deleteVar('multiquote');
        }

        $form = $this->template->render('post/form', [
            'topicPerms' => $topicPerms,
            'post' => $postData,
            'pid' => $this->pid,
            'tid' => $tid,
        ]);

        $page .= $this->template->render('global/box', [
            'title' => $topic->title . ' &gt; Reply',
            'content' => $form,
        ]);
        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);

        return null;
    }

    private function canEdit(Topic $topic, ModelsPost $modelsPost): bool
    {
        if (
            $modelsPost->author &&
            ($modelsPost->newtopic !== 0
                ? $this->user->getGroup()?->canEditTopics
                : $this->user->getGroup()?->canEditPosts) &&
            $modelsPost->author === $this->user->get()->id
        ) {
            return true;
        }

        return $this->user->isModeratorOfTopic($topic);
    }

    private function validatePost(?string $postData): ?string
    {
        return match (true) {
            $postData !== null && trim($postData) === ''
                => "You didn't supply a post!",
            $this->postData && mb_strlen($this->postData) > 65_535
                => 'Post must not exceed 65,535 characters.',
            default => null,
        };
    }

    private function updatePost(ModelsPost $modelsPost): void
    {
        $modelsPost->editby = $this->user->get()->id;
        $modelsPost->editDate = $this->database->datetime();
        $modelsPost->update();

        $this->page->command(
            'update',
            "#pid_{$modelsPost->id} .post_content",
            $this->textFormatting->theWorks($modelsPost->post ?? ''),
        );
        $this->page->command('softurl');
    }

    private function updateTopic(Topic $topic): ?string
    {
        $topicTitle = $this->request->asString->post('ttitle');
        $topicDesc = $this->request->asString->post('tdesc');

        $error = match (true) {
            $topicTitle === null || trim($topicTitle) === ''
                => 'You must supply a topic title!',
            default => null,
        };

        if ($error) {
            return $error;
        }

        $topic->title = $topicTitle;
        $topic->subtitle = $topicDesc ?? '';
        $topic->summary = mb_substr(
            (string) preg_replace(
                '@\s+@',
                ' ',
                $this->textFormatting->wordFilter(
                    $this->textFormatting->textOnly($this->postData ?? ''),
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
        $topic = $post !== null ? Topic::selectOne($post->tid) : null;

        $isTopicPost = $topic && $post && $topic->op === $post->id;

        if ($post === null || $topic === null) {
            return 'The post you are trying to edit does not exist.';
        }

        if (!$this->canEdit($topic, $post)) {
            return "You don't have permission to edit that post!";
        }

        $removeEmbed = $this->request->both('removeEmbed');
        if ($removeEmbed !== null) {
            $openGraphMetadata = json_decode(
                $post->openGraphMetadata,
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            if (array_key_exists($removeEmbed, $openGraphMetadata)) {
                unset($openGraphMetadata[$removeEmbed]);
                $post->openGraphMetadata = json_encode(
                    $openGraphMetadata,
                    JSON_THROW_ON_ERROR,
                );
                $this->updatePost($post);
            }

            return null;
        }

        if ($this->request->post('submit') !== null) {
            // Update topic when editing topic
            $error = $this->validatePost($postData);
            if ($error !== null) {
                return $error;
            }

            $post->post = $postData;
            $this->updatePost($post);

            if ($isTopicPost) {
                $error = $this->updateTopic($topic);
            }

            if ($error !== null) {
                return $error;
            }

            $this->router->redirect('topic', [
                'id' => $post->tid,
                'findpost' => $pid,
            ]);

            return null;
        }

        if ($this->postData === null) {
            $this->postData = $post->post;
        }

        if ($isTopicPost) {
            $this->createTopic->showTopicForm($topic, $post);

            return null;
        }

        $this->showPostForm();

        return null;
    }

    private function createTopic(): null
    {
        $topicInput = $this->createTopic->getInput();
        $error =
            $this->createTopic->validateInput($topicInput) ??
            $this->validatePost($this->postData);

        if ($error) {
            // Handle error here so we can still show topic form
            $this->page->append('PAGE', $this->page->error($error));
            $this->page->command('error', $error);
            $this->page->command('enable', 'submitbutton');
            $this->createTopic->showTopicForm();

            return null;
        }

        $topic = $this->createTopic->createTopic($topicInput);
        $this->createPost($topic->id, true);

        return null;
    }

    private function createPost(int $tid, bool $newtopic = false): ?string
    {
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
        $forum = $topic !== null ? Forum::selectOne($topic->fid) : null;

        if ($topic === null || $forum === null) {
            return "The topic you're trying to reply to does not exist.";
        }

        $forumPerms = $this->user->getForumPerms($forum->perms);

        if (
            ($this->how !== 'newtopic' && !$forumPerms['reply']) ||
            ($topic->locked &&
                !$this->user->getGroup()?->canOverrideLockedTopics)
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
        $post->openGraphMetadata = json_encode(
            $this->openGraph->fetchFromBBCode($postData),
            JSON_THROW_ON_ERROR,
        );
        $post->insert();

        $this->hooks->dispatch('post', $post, $topic);

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
        $path = trim($forum->path) !== '' ? explode(' ', $forum->path) : [];
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
                SQL
                ,
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
                SQL
                ,
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
            $this->router->redirect('topic', ['id' => $tid, 'getlast' => 1]);
        }

        return null;
    }
}
