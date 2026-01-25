<?php

declare(strict_types=1);

namespace Jax\Routes;

use Carbon\Carbon;
use Jax\Database\Database;
use Jax\Date;
use Jax\Interfaces\Route;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Lodash;
use Jax\Models\Category;
use Jax\Models\Forum;
use Jax\Models\Group;
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Models\Topic as ModelsTopic;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Routes\Topic\Poll;
use Jax\Routes\Topic\Reactions;
use Jax\RSSFeed;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;
use Jax\UserOnline;
use Jax\UsersOnline;

use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_unique;
use function ceil;
use function explode;
use function gmdate;
use function implode;
use function in_array;
use function json_decode;
use function json_encode;
use function max;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const SORT_REGULAR;

final class Topic implements Route
{
    private int $pageNumber = 0;

    private int $numperpage = 10;

    private int $firstPostID = 0;

    public function __construct(
        private readonly Badges $badges,
        private readonly Database $database,
        private readonly Date $date,
        private readonly Jax $jax,
        private readonly IPAddress $ipAddress,
        private readonly Page $page,
        private readonly Poll $poll,
        private readonly Reactions $reactions,
        private readonly Request $request,
        private readonly Router $router,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly Template $template,
        private readonly User $user,
        private readonly UsersOnline $usersOnline,
    ) {}

    public function route($params): void
    {
        $tid = (int) ($params['id'] ?? 0);

        $edit = (int) $this->request->asString->both('edit');
        $findPost = (int) $this->request->asString->both('findpost');
        $listRating = (int) $this->request->asString->both('listrating');
        $ratePost = (int) $this->request->asString->both('ratepost');
        $this->pageNumber = max((int) $this->request->both('page') - 1, 0);
        $quickReply = $this->request->both('qreply') !== null;

        $topic = ModelsTopic::selectOne($tid);
        $forumPerms = $topic !== null
            ? $this->fetchForumPermissions($topic)
            : [];

        if (!$topic || !$forumPerms['read']) {
            $this->router->redirect('index');

            return;
        }

        $this->session->act('vt' . $tid);

        if ($this->request->both('votepoll') !== null) {
            $this->poll->vote($topic);
        }

        match (true) {
            $quickReply && !$this->request->isJSUpdate() => match (true) {
                $this->request->isJSAccess() && !$this->request->isJSDirectLink() => $this->quickReplyForm(
                    $topic,
                ),
                default => $this->router->redirect(
                    'post',
                    ['tid' => $topic->id],
                ),
            },
            $ratePost !== 0 => $this->reactions->toggleReaction(
                $ratePost,
                (int) $this->request->both('niblet'),
            ),
            $findPost !== 0 => $this->findPost($topic, $findPost),
            $this->request->both('getlast') !== null => $this->getLastPost(
                $topic,
            ),
            $edit !== 0 => $this->quickEditPost($topic, $edit),
            $this->request->both('quote') !== null => $this->multiQuote(
                $topic,
            ),
            $this->request->both('markread') !== null => $this->markRead(
                $topic,
            ),
            $listRating !== 0 => $this->reactions->listReactions($listRating),
            $this->request->isJSUpdate() => $this->update($topic),
            $this->request->both('fmt') === 'RSS' => $this->viewRSS($topic),
            default => $this->viewTopic($topic),
        };
    }

    /**
     * @return array<string,bool>
     */
    private function fetchForumPermissions(
        ModelsTopic $modelsTopic,
        ?Forum $forum = null,
    ): array {
        static $forumPerms = [];

        if ($forumPerms !== []) {
            return $forumPerms;
        }

        $forum = $forum ?: Forum::selectOne($modelsTopic->fid);

        if ($forum === null) {
            return [];
        }

        return $forumPerms = $this->user->getForumPerms($forum->perms);
    }

    /**
     * @param array<int> $memberIds
     *
     * @return array<int,Member>
     */
    private function fetchMembersById(array $memberIds): array
    {
        return $memberIds !== [] ? Lodash::keyBy(
            Member::selectMany(
                Database::WHERE_ID_IN,
                array_unique($memberIds, SORT_REGULAR),
            ),
            static fn(Member $member): int => $member->id,
        ) : [];
    }

    private function viewTopic(ModelsTopic $modelsTopic): void
    {
        if (
            !$this->user->isGuest()
            && $modelsTopic->lastPostDate > $this->date->datetimeAsTimestamp(
                $this->user->get()->lastVisit,
            )
        ) {
            $this->markRead($modelsTopic);
        }

        $topicTitle = $this->textFormatting->wordFilter($modelsTopic->title);

        $this->page->setPageTitle($topicTitle);
        $this->page->setOpenGraphData([
            'title' => $topicTitle,
            'description' => $modelsTopic->subtitle,
        ]);
        $this->session->set(
            'locationVerbose',
            "In topic '" . $topicTitle . "'",
        );

        $forum = Forum::selectOne($modelsTopic->fid);
        $category = $forum !== null
            ? Category::selectOne($forum->category)
            : null;
        // Fix this to work with subforums.
        $this->page->setBreadCrumbs(
            [
                $this->router->url(
                    'category',
                    ['id' => $category?->id],
                ) => $category->title ?? '',
                $this->router->url('forum', [
                    'id' => $forum?->id,
                    'slug' => $this->textFormatting->slugify($forum?->title),
                ]) => $forum->title ?? '',
                $this->router->url('topic', [
                    'id' => $modelsTopic->id,
                    'slug' => $this->textFormatting->slugify(
                        $modelsTopic->title,
                    ),
                ]) => $this->textFormatting->wordFilter($modelsTopic->title),
            ],
        );

        // Generate pages.
        $postCount = Post::count('WHERE `tid`=?', $modelsTopic->id);

        $totalpages = (int) ceil($postCount / $this->numperpage);
        $pageLinks = [];
        foreach (
            $this->jax->pages(
                $totalpages,
                $this->pageNumber + 1,
                10,
            ) as $pageNumber
        ) {
            $pageURL = $this->router->url(
                'topic',
                ['id' => $modelsTopic->id, 'page' => $pageNumber],
            );
            $activeClass = $pageNumber === $this->pageNumber + 1
                ? ' class="active"'
                : '';
            $pageLinks[] = <<<HTML
                <a href="{$pageURL}"{$activeClass}>{$pageNumber}</a>
                HTML;
        }

        $pagelist = implode(' ', $pageLinks);

        // Are they on the last page? This stores a session variable.
        $this->session->addVar(
            'topic_lastpage',
            ($this->pageNumber + 1) === $totalpages,
        );

        // If it's a poll, put it in.
        $poll = $modelsTopic->pollType !== ''
            ? $this->poll->render($modelsTopic)
            : '';

        $forumPerms = $this->fetchForumPermissions($modelsTopic, $forum);

        // Make the users online list.
        $usersInTopic = array_filter(
            $this->usersOnline->getUsersOnline(),
            static fn(UserOnline $userOnline): bool => $userOnline->location === "vt{$modelsTopic->id}",
        );

        // Generate post listing.
        $page = $this->template->render(
            'topic/index',
            [
                'topic' => $modelsTopic,
                'content' => $this->postsIntoOutput($modelsTopic),
                'canCreateTopic' => $forumPerms['start'],
                'canReply' => $forumPerms['reply'] && (
                    !$modelsTopic->locked || $this->user->getGroup()?->canOverrideLockedTopics
                ),
                'pages' => $pagelist,
                'poll' => $poll,
                'usersInTopic' => $usersInTopic,
            ],
        );

        // Update view count.
        ++$modelsTopic->views;
        $modelsTopic->update();

        if ($this->request->isJSAccess()) {
            $this->page->command('update', 'page', $page);
            if ($this->request->both('pid') !== null) {
                $this->page->command(
                    'scrollToPost',
                    $this->request->both('pid'),
                );

                return;
            }

            if ($this->request->both('page') !== null) {
                $this->page->command('scrollToPost', $this->firstPostID);

                return;
            }

            return;
        }

        $this->page->append('PAGE', $page);
    }

    private function update(ModelsTopic $modelsTopic): void
    {

        // Check for new posts and append them.
        if ($this->session->get()->location !== "vt{$modelsTopic->id}") {
            $this->session->deleteVar('topic_lastpid');
        }

        if (
            $this->session->getVar('topic_lastpid')
            && $this->session->getVar('topic_lastpage')
        ) {
            $newposts = $this->postsIntoOutput(
                $modelsTopic,
                (int) $this->session->getVar('topic_lastpid'),
            );
            if ($newposts !== '') {
                $this->page->command('appendrows', '#intopic', $newposts);
            }
        }

        // Update users online list.
        $list = [];
        $oldcache = array_flip(
            explode(',', $this->session->get()->usersOnlineCache),
        );
        $newcache = [];
        foreach ($this->usersOnline->getUsersOnline() as $userOnline) {
            if (!$userOnline->uid) {
                continue;
            }

            if ($userOnline->location !== "vt{$modelsTopic->id}") {
                continue;
            }

            $newcache[] = $userOnline->uid;

            if (!array_key_exists((string) $userOnline->uid, $oldcache)) {
                $list[] = $userOnline;

                continue;
            }

            unset($oldcache[$userOnline->uid]);
        }

        if ($list !== []) {
            $this->page->command('onlinelist', $list);
        }

        $oldcache = implode(',', array_flip($oldcache));
        $newcache = implode(',', $newcache);
        if ($oldcache !== '') {
            $this->page->command('setoffline', $oldcache);
        }

        $this->session->set('usersOnlineCache', $newcache);
    }

    /**
     * @param array<Post> $posts
     *
     * @return array<string>
     */
    private function renderBadges(array $posts): array
    {
        if (!$this->badges->isEnabled()) {
            return [];
        }

        $badgesPerAuthor = $this->badges->fetchBadges(
            array_map(static fn(Post $post): int => $post->author, $posts),
        );
        $badgesPerAuthorHTML = [];

        foreach ($badgesPerAuthor as $authorId => $badgeTuples) {
            if (!array_key_exists($authorId, $badgesPerAuthorHTML)) {
                $badgesPerAuthorHTML[$authorId] = '';
            }

            foreach ($badgeTuples as $badgeTuple) {
                $profileBadgesURL = $this->router->url(
                    'profile',
                    ['id' => $authorId, 'page' => 'badges'],
                );
                $badgesPerAuthorHTML[$authorId] .= <<<HTML
                    <a href="{$profileBadgesURL}">
                        <img src="{$badgeTuple->badge->imagePath}" title="{$badgeTuple->badge->badgeTitle}">
                    </a>
                    HTML;
            }
        }

        return $badgesPerAuthorHTML;
    }

    private function quickReplyForm(ModelsTopic $modelsTopic): void
    {
        $prefilled = '';
        $this->page->command('softurl');
        if (
            $this->session->getVar('multiquote')
        ) {
            $posts = Post::selectMany(
                'WHERE `id` IN ?',
                explode(',', (string) $this->session->getVar('multiquote')),
            );

            $membersById = Member::joinedOn(
                $posts,
                static fn(Post $post): int => $post->author,
            );

            foreach ($posts as $post) {
                $prefilled .= '[quote=' . $membersById[$post->author]->displayName . ']'
                    . $post->post
                    . '[/quote]'
                    . PHP_EOL;
            }

            $this->session->deleteVar('multiquote');
        }

        $this->page->command(
            'window',
            [
                'content' => $this->template->render(
                    'topic/reply-form',
                    [
                        'topic' => $modelsTopic,
                        'text' => $prefilled,
                    ],
                ),
                'id' => 'qreply',
                'resize' => '.quickreply',
                'title' => $this->textFormatting->wordFilter(
                    $modelsTopic->title,
                ),
            ],
        );
        $this->page->command('updateqreply', '');
    }

    private function postsIntoOutput(
        ModelsTopic $modelsTopic,
        int $lastpid = 0,
    ): string {
        $usersonline = $this->usersOnline->getUsersOnline();

        $topicPostCounter = $this->pageNumber * $this->numperpage;
        $posts = Post::selectMany(
            <<<'SQL'
                WHERE tid = ? AND id > ?
                ORDER BY `newtopic` DESC, `id`
                LIMIT ?,?
                SQL,
            $modelsTopic->id,
            $lastpid,
            $lastpid !== 0 ? 0 : $topicPostCounter,
            $this->numperpage,
        );

        if ($posts === []) {
            return '';
        }

        $membersById = $this->fetchMembersById(
            array_merge(
                array_map(static fn($post): int => $post->author, $posts),
                array_map(static fn($post): int => $post->editby ?? 0, $posts),
            ),
        );

        $groups = Group::joinedOn(
            $membersById,
            static fn(Member $member): int => $member->groupID,
        );

        $forumPerms = $this->fetchForumPermissions($modelsTopic);
        $canModerateTopic = $this->user->isModeratorOfTopic($modelsTopic);

        $badgesPerAuthor = $this->renderBadges($posts);

        $rows = '';
        foreach ($posts as $post) {
            if ($this->firstPostID === 0) {
                $this->firstPostID = $post->id;
            }

            $author = $post->author ? $membersById[$post->author] : null;
            $editor = $post->editby ? $membersById[$post->editby] : null;

            $authorGroup = $author
                ? $groups[$author->groupID]
                : null;

            $rows .= $this->template->render(
                'topic/post-row',
                [
                    'author' => $author,
                    'badges' => $author && array_key_exists($author->id, $badgesPerAuthor) ? $badgesPerAuthor[$author->id] : '',
                    'canEdit' => $this->canEdit($modelsTopic, $post),
                    'canModerate' => $canModerateTopic,
                    'canReport' => !$this->user->isGuest(),
                    'canReply' => $forumPerms['reply'],
                    'editor' => $editor,
                    'group' => $authorGroup,
                    'ip' => $this->ipAddress->asHumanReadable($post->ip),
                    'isOnline' => array_key_exists(
                        $post->author,
                        $usersonline,
                    ),
                    'openGraphData' => $post->openGraphMetadata ? json_decode(
                        $post->openGraphMetadata,
                        true,
                        flags: JSON_THROW_ON_ERROR,
                    ) : null,
                    'post' => $post,
                    'postRating' => $this->reactions->render($post),
                    'topic' => $modelsTopic,
                ],
            );
            $lastpid = $post->id;
        }

        $this->session->addVar('topic_lastpid', $lastpid);

        return $rows;
    }

    private function canEdit(ModelsTopic $modelsTopic, Post $post): bool
    {
        if ($this->user->isModeratorOfTopic($modelsTopic)) {
            return true;
        }

        return $post->author
            && ($post->newtopic !== 0
                ? $this->user->getGroup()?->canEditTopics
                : $this->user->getGroup()?->canEditPosts)
            && $post->author === $this->user->get()->id;
    }

    private function quickEditPost(ModelsTopic $modelsTopic, int $pid): void
    {
        if (!$this->request->isJSAccess()) {
            $this->router->redirect('post', [
                'how' => 'edit',
                'tid' => $modelsTopic->id,
                'pid' => $pid,
            ]);

            return;
        }

        $this->page->command('softurl');
        $post = Post::selectOne($pid);

        if (!$this->request->isJSNewLocation()) {
            return;
        }

        if ($post === null) {
            $this->page->command('error', 'Post not found!');

            return;
        }

        if (!$this->canEdit($modelsTopic, $post)) {
            $this->page->command(
                'error',
                "You don't have permission to edit this post.",
            );

            return;
        }

        $form = $post->newtopic !== 0 ? $this->template->render(
            'topic/qedit-topic',
            [
                'topic' => $modelsTopic,
                'post' => $post,
            ],
        ) : $this->template->render(
            'topic/qedit-post',
            [
                'post' => $post,
            ],
        );

        $this->page->command('update', "#pid_{$pid} .post_content", $form);
    }

    private function multiQuote(ModelsTopic $modelsTopic): void
    {
        $pid = (int) $this->request->asString->both('quote');
        if ($pid === 0) {
            return;
        }

        $post = Post::selectOne($pid);

        if ($post === null) {
            $error = "That post doesn't exist!";
            $this->page->command('error', $error);
            $this->page->append(
                'PAGE',
                $this->template->render('error', ['message' => $error]),
            );

            return;
        }

        $author = Member::selectOne($post->author);

        if ($this->request->both('qreply')) {
            $this->page->command(
                'updateqreply',
                '[quote=' . $author?->name . ']' . $post->post . '[/quote]'
                    . PHP_EOL . PHP_EOL,
            );
        } else {
            $multiquote = (string) ($this->session->getVar(
                'multiquote',
            ) ?: '');
            $multiquotes = explode(',', $multiquote);
            if (!in_array((string) $pid, $multiquotes, true)) {
                $multiquotes[] = (string) $pid;
                $this->session->addVar(
                    'multiquote',
                    implode(',', $multiquotes),
                );
            }

            // This line toggles whether or not the qreply window should open
            // on quote.
            if ($this->request->isJSAccess()) {
                $this->quickReplyForm($modelsTopic);
            } else {
                $this->router->redirect('post', ['tid' => $modelsTopic->id]);
            }
        }

        $this->page->command('softurl');
    }

    private function getLastPost(ModelsTopic $modelsTopic): void
    {
        $result = $this->database->select(
            [
                'MAX(`id`) AS `lastpid`',
                'COUNT(`id`) AS `numposts`',
            ],
            'posts',
            'WHERE `tid`=?',
            $modelsTopic->id,
        );
        $post = $this->database->arow($result);
        $this->database->disposeresult($result);

        if (!$post) {
            $this->router->redirect('index');

            return;
        }

        $this->page->command('softurl');
        $this->router->redirect(
            'topic',
            [
                'id' => $modelsTopic->id,
                'page' => (int) ceil($post['numposts'] / $this->numperpage),
                'pid' => (int) $post['lastpid'],
                'slug' => $this->textFormatting->slugify($modelsTopic->title),
            ],
            "#pid_{$post['lastpid']}",
        );
    }

    private function findPost(ModelsTopic $modelsTopic, int $postId): void
    {
        $postPosition = null;
        $post = Post::selectOne($postId);
        if ($post === null) {
            return;
        }

        $posts = Post::selectMany('WHERE tid=?', $post->tid);
        foreach ($posts as $index => $post) {
            if ($post->id === $postId) {
                $postId = $post->id;
                $postPosition = $index + 1;

                break;
            }
        }

        $this->page->command('softurl');
        if ($postPosition === null) {
            $this->page->command('error', "that post doesn't exist");

            return;
        }

        $pageNumber = (int) ceil($postPosition / $this->numperpage);
        $this->router->redirect(
            'topic',
            [
                'id' => $modelsTopic->id,
                'page' => $pageNumber,
                'pid' => $postId,
                'slug' => $this->textFormatting->slugify($modelsTopic->title),
            ],
            '#pid_' . $post->id,
        );
    }

    private function markRead(ModelsTopic $modelsTopic): void
    {
        $topicsread = json_decode(
            $this->session->get()->topicsread,
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $topicsread[$modelsTopic->id] = Carbon::now('UTC')->getTimestamp();
        $this->session->set(
            'topicsread',
            json_encode($topicsread, JSON_THROW_ON_ERROR),
        );
    }

    private function viewRSS(ModelsTopic $modelsTopic): void
    {
        $rootURL = $this->router->getRootURL();
        $rssFeed = new RSSFeed(
            [
                'description' => $this->textFormatting->wordFilter(
                    $modelsTopic->subtitle,
                ),
                'link' => $rootURL . $this->router->url(
                    'topic',
                    ['id' => $modelsTopic->id],
                ),
                'title' => $this->textFormatting->wordFilter(
                    $modelsTopic->title,
                ),
            ],
        );
        $posts = Post::selectMany(
            'WHERE `tid` = ?',
            $modelsTopic->id,
        );
        $authors = $this->fetchMembersById(
            array_map(static fn(Post $post): int => $post->author, $posts),
        );

        foreach ($posts as $post) {
            $link = $rootURL . $this->router->url('topic', [
                'id' => $modelsTopic->id,
                'findpost' => $post->id,
            ]);
            $rssFeed->additem(
                [
                    'description' => $this->textFormatting->blockhtml(
                        $this->textFormatting->theWorks($post->post),
                    ),
                    'guid' => $link,
                    'link' => $link,
                    'pubDate' => gmdate(
                        'r',
                        $this->date->datetimeAsTimestamp($post->date),
                    ),
                    'title' => $authors[$post->author]->displayName . ':',
                ],
            );
        }

        $this->page->earlyFlush($rssFeed->publish());
    }
}
