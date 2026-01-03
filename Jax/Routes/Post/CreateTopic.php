<?php

namespace Jax\Routes\Post;

use Jax\Database\Database;
use Jax\Models\Forum;
use Jax\Models\Post;
use Jax\Models\Topic;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

class CreateTopic
{
    public function __construct(
        private readonly Database $database,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Router $router,
        private readonly Template $template,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {}

    public function getInput()
    {
        $input = [
            'fid' => (int) $this->request->both('fid'),
            'pollChoices' => $this->request->asString->post('pollchoices'),
            'pollQuestion' => $this->request->asString->post('pollq'),
            'pollType' => $this->request->asString->post('pollType'),
            'topicDescription' => $this->request->asString->post('tdesc'),
            'topicTitle' => $this->request->asString->post('ttitle'),
        ];
        $input['pollChoices'] = $input['pollChoices'] !== null ? array_filter(
            preg_split("@[\r\n]+@", $input['pollChoices']) ?: [],
            static fn(string $line): bool => trim($line) !== '',
        ) : [];

        return $input;
    }

    /**
     * @param array<mixed> $input
     */
    public function validateInput(array $input): ?string
    {
        $forum = Forum::selectOne($input['fid']);
        $forumPerms = $forum !== null
            ? $this->user->getForumPerms($forum->perms)
            : [];

        // New topic input validation
        $error = match (true) {
            !$forum => "The forum you're trying to post in does not exist.",
            !$forumPerms['start'] => "You don't have permission to post a new topic in that forum.",
            !$input['topicTitle'] || trim($input['topicTitle']) === '' => "You didn't specify a topic title!",
            mb_strlen($input['topicTitle']) > 255 => 'Topic title must not exceed 255 characters',
            mb_strlen($input['topicDescription'] ?? '') > 255 => 'Topic description must not exceed 255 characters',
            default => null,
        };

        // Poll input validation
        $error ??= match (true) {
            !$input['pollType'] => null,
            $input['pollQuestion'] === null || trim($input['pollQuestion']) === '' => "You didn't specify a poll question!",
            count($input['pollChoices']) > 10 => 'Poll choices must not exceed 10.',
            $input['pollChoices'] === [] => "You didn't provide any poll choices!",
            $forum && !$forumPerms['poll'] => "You don't have permission to post a poll in that forum",
            default => null,
        };

        return $error;
    }

    public function createTopic(array $input): Topic
    {
        $uid = $this->user->get()->id;
        $postDate = $this->database->datetime();

        $topic = new Topic();
        $topic->author = $uid;
        $topic->date = $postDate;
        $topic->fid = $input['fid'];
        $topic->lastPostDate = $postDate;
        $topic->lastPostUser = $uid;
        $topic->pollChoices = $input['pollChoices'] !== []
            ? (json_encode($input['pollChoices'], JSON_THROW_ON_ERROR))
            : '';
        $topic->pollQuestion = $input['pollQuestion'] ?: '';
        $topic->pollType = $input['pollType'] ?? '';
        $topic->replies = 0;
        $topic->subtitle = $input['topicDescription'] ?? '';
        $topic->summary = mb_substr(
            (string) preg_replace(
                '@\s+@',
                ' ',
                $this->textFormatting->textOnly(
                    $this->postData ?? '',
                ),
            ),
            0,
            50,
        );
        $topic->title = $input['topicTitle'];
        $topic->views = 0;
        $topic->insert();

        return $topic;
    }

    public function showTopicForm(?Topic $topic = null, ?Post $post = null): null
    {
        $postData = $this->request->asString->post('postdata') ?? $post?->post;
        $tid = $topic->id ?? '';
        $fid = $topic->fid ?? (int) $this->request->asString->both('fid');
        $how = $this->request->asString->both('how') ?? 'newtopic';

        $isEditing = (bool) $topic;

        $forum = Forum::selectOne($fid);

        if ($forum === null) {
            $this->router->redirect('index');

            return null;
        }

        if ($topic === null) {
            $topic = new Topic();
            $topic->subtitle = $this->request->asString->post('tdesc') ?? '';
            $topic->title = $this->request->asString->post('ttitle') ?? '';
        }

        $forumPerms = $this->user->getForumPerms($forum->perms);

        $form = $this->template->render('post/new-topic-form', [
            'fid' => $fid,
            'forumPerms' => $forumPerms,
            'how' => $how,
            'isEditing' => $isEditing,
            'post' => $postData,
            'tid' => $tid,
            'topic' => $topic,
        ]);
        $page = '<div id="post-preview">' . $this->template->render('post/preview', ['post' => $postData]) . '</div>';
        $page .= $this->template->render('global/box', [
            'title' => $forum->title . ' > New Topic',
            'content' => $form,
        ]);

        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);

        if ($forumPerms['upload']) {
            $this->page->command('attachfiles');
        }

        return null;
    }
}
