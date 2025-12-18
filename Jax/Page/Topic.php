<?php

declare(strict_types=1);

namespace Jax\Page;

use Carbon\Carbon;
use Jax\Config;
use Jax\Database\Database;
use Jax\Date;
use Jax\DomainDefinitions;
use Jax\Interfaces\Route;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Models\Category;
use Jax\Models\Forum;
use Jax\Models\Group;
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Models\Topic as ModelsTopic;
use Jax\Page;
use Jax\Page\Topic\Poll;
use Jax\Page\Topic\Reactions;
use Jax\Request;
use Jax\Router;
use Jax\RSSFeed;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;
use Jax\UsersOnline;

use function _\keyBy;
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
        private readonly Config $config,
        private readonly Database $database,
        private readonly Date $date,
        private readonly DomainDefinitions $domainDefinitions,
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
    ) {
        $this->template->loadMeta('topic');
    }

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
                $this->request->isJSAccess() && !$this->request->isJSDirectLink() => $this->quickReplyForm($topic),
                default => $this->router->redirect('post', ['tid' => $topic->id]),
            },
            $ratePost !== 0 => $this->reactions->toggleReaction($ratePost, (int) $this->request->both('niblet')),
            $findPost !== 0 => $this->findPost($topic, $findPost),
            $this->request->both('getlast') !== null => $this->getLastPost($topic),
            $edit !== 0 => $this->quickEditPost($topic, $edit),
            $this->request->both('quote') !== null => $this->multiQuote($topic),
            $this->request->both('markread') !== null => $this->markRead($topic),
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
        return $memberIds !== [] ? keyBy(
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
            && $modelsTopic->lastPostDate > $this->date->datetimeAsTimestamp($this->user->get()->lastVisit)
        ) {
            $this->markRead($modelsTopic);
        }

        $topicTitle = $this->textFormatting->wordfilter($modelsTopic->title);
        $topicSubtitle = $this->textFormatting->wordfilter($modelsTopic->subtitle);

        $this->page->setPageTitle($topicTitle);
        $this->session->set('locationVerbose', "In topic '" . $topicTitle . "'");

        $forum = Forum::selectOne($modelsTopic->fid);
        $category = $forum !== null
            ? Category::selectOne($forum->category)
            : null;
        // Fix this to work with subforums.
        $this->page->setBreadCrumbs(
            [
                $this->router->url('category', ['id' => $category?->id]) => $category->title ?? '',
                $this->router->url('forum', [
                    'id' => $forum?->id,
                    'slug' => $this->textFormatting->slugify($forum?->title),
                ]) => $forum->title ?? '',
                $this->router->url('topic', [
                    'id' => $modelsTopic->id,
                    'slug' => $this->textFormatting->slugify($modelsTopic->title),
                ]) => $topicTitle,
            ],
        );

        // Generate pages.
        $postCount = Post::count('WHERE `tid`=?', $modelsTopic->id);

        $totalpages = (int) ceil($postCount / $this->numperpage);
        $pageLinks = [];
        foreach ($this->jax->pages($totalpages, $this->pageNumber + 1, 10) as $pageNumber) {
            $pageURL = $this->router->url('topic', ['id' => $modelsTopic->id, 'page' => $pageNumber]);
            $activeClass = $pageNumber === $this->pageNumber + 1
                ? ' class="active"'
                : '';
            $pageLinks[] = <<<HTML
                <a href="{$pageURL}"{$activeClass}>{$pageNumber}</a>
                HTML;
        }

        $pagelist = implode(' ', $pageLinks);

        // Are they on the last page? This stores a session variable.
        $this->session->addVar('topic_lastpage', ($this->pageNumber + 1) === $totalpages);

        // If it's a poll, put it in.
        $poll = $modelsTopic->pollType !== ''
            ? $this->poll->render($modelsTopic)
            : '';

        // Generate post listing.
        $page = $this->template->meta('topic-table', $this->postsIntoOutput($modelsTopic));
        $page = $this->template->meta(
            'topic-wrapper',
            $topicTitle
                . ($topicSubtitle !== '' ? ', ' . $topicSubtitle : ''),
            $page,
            '<a href="'
                . $this->router->url('topic', ['id' => $modelsTopic->id, 'fmt' => 'RSS'])
                . '" class="social rss" title="RSS Feed for this Topic" target="_blank">RSS</a>',
        );

        // Add buttons.
        $buttons = [
            '',
            '',
            '',
        ];

        $forumPerms = $this->fetchForumPermissions($modelsTopic, $forum);
        if ($forumPerms['start']) {
            $newTopicURL = $this->router->url('post', ['fid' => $modelsTopic->fid]);
            $buttons[0] = "<a href='{$newTopicURL}'>"
                . $this->template->meta(
                    $this->template->metaExists('button-newtopic')
                        ? 'button-newtopic'
                        : 'topic-button-newtopic',
                )
                . '</a>';
        }

        if (
            $forumPerms['reply']
            && (
                !$modelsTopic->locked
                || $this->user->getGroup()?->canOverrideLockedTopics
            )
        ) {
            $quickReplyURL = $this->router->url('topic', ['id' => $modelsTopic->id, 'qreply' => 1]);
            $buttons[1] = "<a href='{$quickReplyURL}'>" . $this->template->meta(
                $this->template->metaExists('button-qreply')
                    ? 'button-qreply'
                    : 'topic-button-qreply',
            ) . '</a>';
        }

        if (
            $forumPerms['reply']
            && (
                !$modelsTopic->locked
                || $this->user->getGroup()?->canOverrideLockedTopics
            )
        ) {
            $replyURL = $this->router->url('post', ['tid' => $modelsTopic->id]);
            $buttons[2] = "<a href='{$replyURL}'>" . $this->template->meta(
                $this->template->metaExists('button-reply')
                    ? 'button-reply'
                    : 'topic-button-reply',
            ) . '</a>';
        }


        // Make the users online list.
        $usersonline = '';
        foreach ($this->usersOnline->getUsersOnline() as $userOnline) {
            if (!$userOnline->uid) {
                continue;
            }

            if ($userOnline->location !== "vt{$modelsTopic->id}") {
                continue;
            }

            if ($userOnline->isBot) {
                $usersonline .= '<a class="user' . $userOnline->uid . '">' . $userOnline->name . '</a>';

                continue;
            }

            $usersonline .= $this->template->meta(
                'user-link',
                $userOnline->uid,
                $userOnline->groupID . (
                    $userOnline->status === 'idle'
                    ? " idle lastAction{$userOnline->lastAction}"
                    : ''
                ),
                $userOnline->name,
            );
        }

        $page .= $this->template->meta('topic-users-online', $usersonline);

        // Add in other page elements.
        $page = $poll . $this->template->meta(
            'topic-pages-top',
            $pagelist,
        ) . $this->template->meta(
            'topic-buttons-top',
            ...$buttons,
        ) . $page . $this->template->meta(
            'topic-pages-bottom',
            $pagelist,
        ) . $this->template->meta(
            'topic-buttons-bottom',
            ...$buttons,
        );

        // Update view count.
        ++$modelsTopic->views;
        $modelsTopic->update();

        if ($this->request->isJSAccess()) {
            $this->page->command('update', 'page', $page);
            if ($this->request->both('pid') !== null) {
                $this->page->command('scrollToPost', $this->request->both('pid'));

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
            $newposts = $this->postsIntoOutput($modelsTopic, (int) $this->session->getVar('topic_lastpid'));
            if ($newposts !== '') {
                $this->page->command('appendrows', '#intopic', $newposts);
            }
        }

        // Update users online list.
        $list = [];
        $oldcache = array_flip(explode(',', $this->session->get()->usersOnlineCache));
        $newcache = [];
        foreach ($this->usersOnline->getUsersOnline() as $userOnline) {
            if (!$userOnline->uid) {
                continue;
            }

            if ($userOnline->location !== "vt{$modelsTopic->id}") {
                continue;
            }

            $newcache[] = $userOnline->uid;

            if (!array_key_exists($userOnline->uid, $oldcache)) {
                $list[] = [
                    $userOnline->uid,
                    $userOnline->groupID,
                    $userOnline->status !== 'active'
                        ? $userOnline->status
                        : ($userOnline->birthday && ($this->config->getSetting('birthdays') & 1)
                            ? ' birthday' : ''),
                    $userOnline->name,
                    // don't display location, since we know we're in the topic
                    false,
                    $userOnline->lastAction,
                ];

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

        $badgesPerAuthor = $this->badges->fetchBadges(array_map(static fn(Post $post): int => $post->author, $posts));
        $badgesPerAuthorHTML = [];

        foreach ($badgesPerAuthor as $authorId => $badgeTuples) {
            if (!array_key_exists($authorId, $badgesPerAuthorHTML)) {
                $badgesPerAuthorHTML[$authorId] = '';
            }

            foreach ($badgeTuples as $badgeTuple) {
                $profileBadgesURL = $this->router->url('profile', ['id' => $authorId, 'page' => 'badges']);
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
                'content' => $this->template->meta(
                    'topic-reply-form',
                    $modelsTopic->id,
                    $this->textFormatting->blockhtml($prefilled),
                ),
                'id' => 'qreply',
                'resize' => 'textarea',
                'title' => $this->textFormatting->wordfilter($modelsTopic->title),
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
            'WHERE tid = ? AND id > ? '
            . 'ORDER BY `id` '
            . 'LIMIT ?,?',
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

            $postBody = $post->post;
            $postBody = $this->textFormatting->theWorks($postBody);

            // Post rating content goes here.
            $postrating = "<div id='reaction_p{$post->id}'>"
                . $this->reactions->render($post)
                . '</div>';

            $author = $post->author ? $membersById[$post->author] : null;
            $editor = $post->editby ? $membersById[$post->editby] : null;

            $editURL = $this->router->url('topic', ['id' => $modelsTopic->id, 'edit' => $post->id]);
            $replyURL = $this->router->url('topic', ['id' => $modelsTopic->id, 'quote' => $post->id]);
            $modPostURL = $this->router->url('modcontrols', ['do' => 'modp', 'pid' => $post->id]);

            $authorGroup = $author
                ? $groups[$author->groupID]
                : null;
            $postbuttons
                // Adds the Edit button
                = ($this->canEdit($modelsTopic, $post)
                    ? "<a href='{$editURL}' class='edit'>" . $this->template->meta('topic-edit-button')
                    . '</a>'
                    : '')
                // Adds the Quote button
                . ($forumPerms['reply']
                    ? " <a href='{$replyURL}' onclick='RUN.handleQuoting(this);return false;' "
                    . "class='quotepost'>" . $this->template->meta('topic-quote-button') . '</a> '
                    : '')
                // Adds the Moderate options
                . ($canModerateTopic
                    ? "<a href='{$modPostURL}' class='modpost' onclick='RUN.modcontrols.togbutton(this)'>"
                    . $this->template->meta('topic-mod-button') . '</a>'
                    : '');

            $urls = [
                'findpost' => $this->router->url('topic', [
                    'id' => $modelsTopic->id,
                    'findpost' => $post->id,
                    'pid' => $post->id,
                    'slug' => $this->textFormatting->slugify($modelsTopic->title),
                ]) . '#pid_' . $post->id,
                'iptools' => $this->router->url('modcontrols', [
                    'do' => 'iptools',
                    'ip' => $this->ipAddress->asHumanReadable($post->ip),
                ]),
            ];

            $postEmbeds = '';
            if ($post->openGraphMetadata) {
                $openGraphData = json_decode($post->openGraphMetadata, true, flags: JSON_THROW_ON_ERROR);
                foreach ($openGraphData as $url => $data) {
                    $postEmbeds .= $this->template->meta(
                        'topic-post-opengraph',
                        $data['url'] ?? $url,
                        $data['site_name'] ?? '',
                        $data['title'] ?? '',
                        $data['description'] ?? '',
                        $data['image'] ? '<img src="' . $this->textFormatting->blockhtml($data['image']) . '">' : '',
                    );
                }
            }

            $rows .= $this->template->meta(
                'topic-post-row',
                $post->id,
                $modelsTopic->id,
                $author ? $this->template->meta(
                    'user-link',
                    $author->id,
                    $author->groupID,
                    $author->displayName,
                ) : 'Guest',
                $author?->avatar ?: $this->template->meta('default-avatar'),
                $author?->usertitle,
                $author?->posts,
                $this->template->meta(
                    'topic-status-'
                        . (array_key_exists($post->author, $usersonline) ? 'online' : 'offline'),
                ),
                $authorGroup?->title,
                $post->author,
                $postbuttons,
                // ^10
                $this->date->autoDate($post->date),
                <<<HTML
                    <a href="{$urls['findpost']}"
                        onclick="prompt('Link to this post:',this.href);return false"
                        >{$this->template->meta('topic-perma-button')}</a>
                    HTML,
                $postBody,
                $authorGroup->canUseSignatures && $author?->sig
                    ? $this->textFormatting->theWorks($author->sig)
                    : '',
                $this->router->url('profile', ['id' => $post->author]),
                $editor ? $this->template->meta(
                    'topic-edit-by',
                    $this->template->meta(
                        'user-link',
                        $editor->id,
                        $editor->groupID,
                        $editor->displayName,
                    ),
                    $this->date->autoDate($post->editDate),
                ) : '',
                $this->user->getGroup()?->canModerate
                    ? "<a href='{$urls['iptools']}'>" . $this->template->meta(
                        'topic-mod-ipbutton',
                        $this->ipAddress->asHumanReadable($post->ip),
                    ) . '</a>'
                    : '',
                $authorGroup?->icon ? $this->template->meta(
                    'topic-icon-wrapper',
                    $authorGroup->icon,
                ) : '',
                ++$topicPostCounter,
                $postrating,
                // ^20
                $badgesPerAuthor[$author?->id] ?? '',
                $postEmbeds,
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

        $hiddenfields = $this->jax->hiddenFormFields(
            [
                'act' => 'post',
                'how' => 'edit',
                'pid' => (string) $pid,
            ],
        );

        if (!$this->request->isJSNewLocation()) {
            return;
        }

        if ($post === null) {
            $this->page->command('alert', 'Post not found!');

            return;
        }

        if (!$this->canEdit($modelsTopic, $post)) {
            $this->page->command('alert', "You don't have permission to edit this post.");

            return;
        }

        if ($post->newtopic !== 0) {
            $hiddenfields .= $this->jax->hiddenFormFields(
                [
                    'tid' => (string) $post->tid,
                ],
            );
            $form = $this->template->meta(
                'topic-qedit-topic',
                $hiddenfields,
                $this->textFormatting->wordfilter($modelsTopic->title),
                $this->textFormatting->wordfilter($modelsTopic->subtitle),
                $this->textFormatting->blockhtml($post->post),
            );
        } else {
            $form = $this->template->meta(
                'topic-qedit-post',
                $hiddenfields,
                $this->textFormatting->blockhtml($post->post),
                $pid,
            );
        }

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
            $this->page->command('alert', $error);
            $this->page->append('PAGE', $this->template->meta('error', $error));

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
            $multiquote = (string) ($this->session->getVar('multiquote') ?: '');
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
                'page' => ceil($post['numposts'] / $this->numperpage),
                'pid' => $post['lastpid'],
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
            $this->page->command('alert', "that post doesn't exist");

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
        );
    }

    private function markRead(ModelsTopic $modelsTopic): void
    {
        $topicsread = json_decode($this->session->get()->topicsread, true, flags: JSON_THROW_ON_ERROR);
        $topicsread[$modelsTopic->id] = Carbon::now('UTC')->getTimestamp();
        $this->session->set('topicsread', json_encode($topicsread, JSON_THROW_ON_ERROR));
    }

    private function viewRSS(ModelsTopic $modelsTopic): void
    {
        $boardURL = $this->domainDefinitions->getBoardURL();
        $rssFeed = new RSSFeed(
            [
                'description' => $this->textFormatting->wordfilter($modelsTopic->subtitle),
                'link' => $boardURL . $this->router->url('topic', ['id' => $modelsTopic->id]),
                'title' => $this->textFormatting->wordfilter($modelsTopic->title),
            ],
        );
        $posts = Post::selectMany(
            'WHERE `tid` = ?',
            $modelsTopic->id,
        );
        $authors = $this->fetchMembersById(array_map(static fn(Post $post): int => $post->author, $posts));

        foreach ($posts as $post) {
            $rssFeed->additem(
                [
                    'description' => $this->textFormatting->blockhtml($this->textFormatting->theWorks($post->post)),
                    'guid' => $post->id,
                    'link' => $boardURL . $this->router->url('topic', [
                        'id' => $modelsTopic->id,
                        'findpost' => $post->id,
                    ]),
                    'pubDate' => gmdate('r', $this->date->datetimeAsTimestamp($post->date)),
                    'title' => $authors[$post->author]->displayName . ':',
                ],
            );
        }

        $this->page->earlyFlush($rssFeed->publish());
    }
}
