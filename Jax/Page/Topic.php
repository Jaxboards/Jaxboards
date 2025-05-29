<?php

declare(strict_types=1);

namespace Jax\Page;

use Carbon\Carbon;
use Jax\Config;
use Jax\Database;
use Jax\Date;
use Jax\DomainDefinitions;
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
use Jax\RSSFeed;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function _\keyBy;
use function array_flip;
use function array_map;
use function array_merge;
use function array_unique;
use function ceil;
use function explode;
use function gmdate;
use function header;
use function implode;
use function in_array;
use function json_encode;
use function max;
use function preg_match;

use const PHP_EOL;

final class Topic
{
    private int $pageNumber = 0;

    private int $numperpage = 10;

    private int $firstPostID = 0;

    public function __construct(
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
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly Template $template,
        private readonly User $user,
    ) {
        $this->template->loadMeta('topic');
    }

    public function render(): void
    {
        preg_match('@\d+$@', (string) $this->request->asString->both('act'), $act);
        $tid = $act !== [] ? (int) $act[0] : 0;

        $edit = (int) $this->request->asString->both('edit');
        $findPost = (int) $this->request->asString->both('findpost');
        $listRating = (int) $this->request->asString->both('listrating');
        $ratePost = (int) $this->request->asString->both('ratepost');
        $this->pageNumber = max((int) $this->request->both('page') - 1, 0);
        $quickReply = $this->request->both('qreply') !== null;

        $topic = $this->fetchTopicData($tid);
        $forumPerms = $this->fetchForumPermissions($topic);

        if (!$topic || !$forumPerms['read']) {
            $this->page->location('?');

            return;
        }

        match (true) {
            $quickReply && !$this->request->isJSUpdate() => match (true) {
                $this->request->isJSAccess() && !$this->request->isJSDirectLink() => $this->quickReplyForm($topic),
                default => $this->page->location('?act=post&tid=' . $topic->id),
            },
            $ratePost !== 0 => $this->reactions->toggleReaction($ratePost, (int) $this->request->both('niblet')),
            $this->request->both('votepoll') !== null => $this->poll->vote($topic),
            $findPost !== 0 => $this->findPost($topic, $findPost),
            $this->request->both('getlast') !== null => $this->getLastPost($topic->id),
            $edit !== 0 => $this->quickEditPost($topic, $edit),
            $this->request->both('quote') !== null => $this->multiQuote($topic),
            $this->request->both('markread') !== null => $this->markRead($topic),
            $listRating !== 0 => $this->reactions->listReactions($listRating),
            $this->request->isJSUpdate() => $this->update($topic),
            $this->request->both('fmt') === 'RSS' => $this->viewRSS($topic),
            default => $this->viewTopic($topic),
        };
    }

    private function fetchTopicData(int $tid): ?ModelsTopic
    {
        $topic = ModelsTopic::selectOne($this->database, Database::WHERE_ID_EQUALS, $tid);

        if ($topic === null) {
            return null;
        }

        return $topic;
    }

    private function fetchForumPermissions(?ModelsTopic $modelsTopic)
    {
        static $forumPerms = [];

        if ($forumPerms !== []) {
            return $forumPerms;
        }

        $forum = Forum::selectOne($this->database, Database::WHERE_ID_EQUALS, $modelsTopic->fid);

        return $forumPerms = $this->user->getForumPerms($forum->perms);
    }

    /**
     * @return array<int,Member>
     */
    private function fetchMembersById(array $memberIds): array
    {
        return keyBy(
            Member::selectMany(
                $this->database,
                Database::WHERE_ID_IN,
                array_unique($memberIds),
            ),
            static fn(Member $member): int => $member->id,
        );
    }

    private function viewTopic(ModelsTopic $modelsTopic): void
    {
        if (
            !$this->user->isGuest()
            && $modelsTopic->lp_date > $this->user->get('last_visit')
        ) {
            $this->markRead($modelsTopic);
        }

        $topicTitle = $this->textFormatting->wordfilter($modelsTopic->title);
        $topicSubtitle = $this->textFormatting->wordfilter($modelsTopic->subtitle);

        $this->page->setPageTitle($topicTitle);
        $this->session->set('location_verbose', "In topic '" . $topicTitle . "'");

        $forum = Forum::selectOne($this->database, Database::WHERE_ID_EQUALS, $modelsTopic->fid);
        $category = Category::selectOne($this->database, Database::WHERE_ID_EQUALS, $forum->cat_id);
        // Fix this to work with subforums.
        $this->page->setBreadCrumbs(
            [
                "?act=vc{$category->id}" => (string) $category->title,
                "?act=vf{$forum->id}" => (string) $forum->title,
                "?act=vt{$modelsTopic->id}" => $topicTitle,
            ],
        );

        // Generate pages.
        $postCount = Post::count($this->database, 'WHERE `tid`=?', $modelsTopic->id) ?? 0;

        $totalpages = (int) ceil($postCount / $this->numperpage);
        $pagelist = '';
        foreach ($this->jax->pages($totalpages, $this->pageNumber + 1, 10) as $pageNumber) {
            $pagelist .= $this->template->meta(
                'topic-pages-part',
                $modelsTopic->id,
                $pageNumber,
                $pageNumber === $this->pageNumber + 1 ? ' class="active"' : '',
                $pageNumber,
            );
        }

        // Are they on the last page? This stores a session variable.
        $this->session->addVar('topic_lastpage', $this->pageNumber + 1 === $totalpages);

        // If it's a poll, put it in.
        $poll = $modelsTopic->poll_type !== '' && $modelsTopic->poll_type !== '0'
            ? $this->poll->render($modelsTopic)
            : '';

        // Generate post listing.
        $page = $this->template->meta('topic-table', $this->postsIntoOutput($modelsTopic));
        $page = $this->template->meta(
            'topic-wrapper',
            $topicTitle
                . ($topicSubtitle !== '' ? ', ' . $topicSubtitle : ''),
            $page,
            '<a href="./?act=vt' . $modelsTopic->id . '&amp;fmt=RSS" class="social rss" title="RSS Feed for this Topic" target="_blank">RSS</a>',
        );

        // Add buttons.
        $buttons = [
            '',
            '',
            '',
        ];

        $forumPerms = $this->fetchForumPermissions($modelsTopic);
        if ($forumPerms['start']) {
            $buttons[0] = "<a href='?act=post&fid=" . $modelsTopic->fid . "'>"
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
                || $this->user->getPerm('can_override_locked_topics')
            )
        ) {
            $buttons[1] = "<a href='?act=vt{$modelsTopic->id}&qreply=1'>" . $this->template->meta(
                $this->template->metaExists('button-qreply')
                    ? 'button-qreply'
                    : 'topic-button-qreply',
            ) . '</a>';
        }

        if (
            $forumPerms['reply']
            && (
                !$modelsTopic->locked
                || $this->user->getPerm('can_override_locked_topics')
            )
        ) {
            $buttons[2] = "<a href='?act=post&tid={$modelsTopic->id}'>" . $this->template->meta(
                $this->template->metaExists('button-reply')
                    ? 'button-reply'
                    : 'topic-button-reply',
            ) . '</a>';
        }


        // Make the users online list.
        $usersonline = '';
        foreach ($this->database->getUsersOnline($this->user->isAdmin()) as $user) {
            if (!$user['uid']) {
                continue;
            }

            if ($user['location'] !== "vt{$modelsTopic->id}") {
                continue;
            }

            if (isset($user['is_bot']) && $user['is_bot']) {
                $usersonline .= '<a class="user' . $user['uid'] . '">' . $user['name'] . '</a>';

                continue;
            }

            $usersonline .= $this->template->meta(
                'user-link',
                $user['uid'],
                $user['group_id'] . (
                    $user['status'] === 'idle'
                    ? " idle lastAction{$user['last_action']}"
                    : ''
                ),
                $user['name'],
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
        $modelsTopic->update($this->database);

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
        if ($this->session->get('location') !== "vt{$modelsTopic->id}") {
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
        $oldcache = array_flip(explode(',', (string) $this->session->get('users_online_cache')));
        $newcache = [];
        foreach ($this->database->getUsersOnline($this->user->isAdmin()) as $user) {
            if (!$user['uid']) {
                continue;
            }

            if ($user['location'] !== "vt{$modelsTopic->id}") {
                continue;
            }

            $newcache[] = $user['uid'];

            if (!isset($oldcache[$user['uid']])) {
                $list[] = [
                    $user['uid'],
                    $user['group_id'],
                    $user['status'] !== 'active'
                        ? $user['status']
                        : ($user['birthday'] && ($this->config->getSetting('birthdays') & 1)
                            ? ' birthday' : ''),
                    $user['name'],
                    // don't display location, since we know we're in the topic
                    false,
                    $user['last_action'],
                ];

                continue;
            }

            unset($oldcache[$user['uid']]);
        }

        if ($list !== []) {
            $this->page->command('onlinelist', $list);
        }

        $oldcache = implode(',', array_flip($oldcache));
        $newcache = implode(',', $newcache);
        if ($oldcache !== '') {
            $this->page->command('setoffline', $oldcache);
        }

        $this->session->set('users_online_cache', $newcache);
    }

    private function quickReplyForm(ModelsTopic $modelsTopic): void
    {
        $prefilled = '';
        $this->page->command('softurl');
        if (
            $this->session->getVar('multiquote')
        ) {
            $posts = Post::selectMany(
                $this->database,
                'WHERE `id` IN ?',
                explode(',', (string) $this->session->getVar('multiquote')),
            );

            $members = keyBy(
                Member::selectMany(
                    $this->database,
                    Database::WHERE_ID_IN,
                    array_unique(array_map(static fn($post): ?int => $post->auth_id, $posts)),
                ),
                static fn($member) => $member->id,
            );


            foreach ($posts as $post) {
                $prefilled .= '[quote=' . $members[$post->auth_id]->display_name . ']'
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
        $usersonline = $this->database->getUsersOnline();
        $this->config->getSetting('ratings') ?? 0;

        $topicPostCounter = $this->pageNumber * $this->numperpage;
        $posts = Post::selectMany(
            $this->database,
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
                array_map(static fn($post): ?int => $post->auth_id, $posts),
                array_map(static fn($post): ?int => $post->editby, $posts),
            ),
        );

        $forumPerms = $this->fetchForumPermissions($modelsTopic);

        $rows = '';
        foreach ($posts as $post) {
            if ($this->firstPostID === 0) {
                $this->firstPostID = $post->id;
            }

            $postBody = $post->post;
            $postBody = $this->textFormatting->theWorks($postBody);

            // Post rating content goes here.
            $postrating = $this->reactions->render($post);

            $author = $post->auth_id ? $membersById[$post->auth_id] : null;
            $editor = $post->editby ? $membersById[$post->editby] : null;
            $authorGroup = $author
                ? Group::selectOne($this->database, Database::WHERE_ID_EQUALS, $author->group_id)
                : null;
            $postbuttons
                // Adds the Edit button
                = ($this->canEdit($modelsTopic, $post)
                    ? "<a href='?act=vt" . $modelsTopic->id . '&amp;edit=' . $post->id
                    . "' class='edit'>" . $this->template->meta('topic-edit-button')
                    . '</a>'
                    : '')
                // Adds the Quote button
                . ($forumPerms['reply']
                    ? " <a href='?act=vt" . $modelsTopic->id . '&amp;quote=' . $post->id
                    . "' onclick='RUN.handleQuoting(this);return false;' "
                    . "class='quotepost'>" . $this->template->meta('topic-quote-button') . '</a> '
                    : '')
                // Adds the Moderate options
                . ($this->canModerate($modelsTopic)
                    ? "<a href='?act=modcontrols&amp;do=modp&amp;pid=" . $post->id
                    . "' class='modpost' onclick='RUN.modcontrols.togbutton(this)'>"
                    . $this->template->meta('topic-mod-button') . '</a>'
                    : '');

            $rows .= $this->template->meta(
                'topic-post-row',
                $post->id,
                $modelsTopic->id,
                $author ? $this->template->meta(
                    'user-link',
                    $author->id,
                    $author->group_id,
                    $author->display_name,
                ) : 'Guest',
                $author->avatar ?: $this->template->meta('default-avatar'),
                $author->usertitle,
                $author->posts,
                $this->template->meta(
                    'topic-status-'
                        . (isset($usersonline[$post->auth_id])
                            && $usersonline[$post->auth_id] ? 'online' : 'offline'),
                ),
                $authorGroup->title,
                $post->auth_id,
                $postbuttons,
                // ^10
                $this->date->autoDate($this->database->datetimeAsTimestamp($post->date)),
                <<<HTML
                    <a href="?act=vt{$modelsTopic->id}&amp;findpost={$post->id}&pid={$post->id}"
                        onclick="prompt('Link to this post:',this.href);return false"
                        >{$this->template->meta('topic-perma-button')}</a>
                    HTML,
                $postBody,
                isset($author->sig) && $author->sig
                    ? $this->textFormatting->theWorks($author->sig)
                    : '',
                $post->auth_id,
                $editor ? $this->template->meta(
                    'topic-edit-by',
                    $this->template->meta(
                        'user-link',
                        $editor->id,
                        $editor->group_id,
                        $editor->display_name,
                    ),
                    $this->date->autoDate($this->database->datetimeAsTimestamp($post->edit_date)),
                ) : '',
                $this->user->getPerm('can_moderate')
                    ? '<a href="?act=modcontrols&amp;do=iptools&amp;ip='
                    . $this->ipAddress->asHumanReadable($post->ip) . '">' . $this->template->meta(
                        'topic-mod-ipbutton',
                        $this->ipAddress->asHumanReadable($post->ip),
                    ) . '</a>'
                    : '',
                $authorGroup->icon ? $this->template->meta(
                    'topic-icon-wrapper',
                    $authorGroup->icon,
                ) : '',
                ++$topicPostCounter,
                $postrating,
                // 30 V
                $author->contact_skype ?? '',
                $author->contact_discord ?? '',
                $author->contact_yim ?? '',
                $author->contact_msn ?? '',
                $author->contact_gtalk ?? '',
                $author->contact_aim ?? '',
                $author->contact_youtube ?? '',
                $author->contact_steam ?? '',
                $author->contact_twitter ?? '',
                $author->contact_bluesky ?? '',
                '',
                '',
                '',
            );
            $lastpid = $post->id;
        }

        $this->session->addVar('topic_lastpid', $lastpid);

        return $rows;
    }

    private function canEdit(ModelsTopic $modelsTopic, Post $post): bool
    {
        if ($this->canModerate($modelsTopic)) {
            return true;
        }

        return $post->auth_id
            && ($post->newtopic !== 0
                ? $this->user->getPerm('can_edit_topics')
                : $this->user->getPerm('can_edit_posts'))
            && $post->auth_id === $this->user->get('id');
    }

    private function canModerate(ModelsTopic $modelsTopic): bool
    {
        static $canMod;
        if ($canMod !== null) {
            return $canMod;
        }

        $canMod = false;
        if ($this->user->getPerm('can_moderate')) {
            $canMod = true;
        }

        if ($this->user->get('mod')) {
            $result = $this->database->special(
                <<<'SQL'
                    SELECT `mods`
                    FROM %t
                    WHERE `id` = (
                        SELECT `fid`
                        FROM %t
                        WHERE `id` = ?
                    )
                    SQL,
                ['forums', 'topics'],
                $modelsTopic->id,
            );
            $mods = $this->database->arow($result);
            $this->database->disposeresult($result);
            if (
                $mods
                && in_array($this->user->get('id'), explode(',', (string) $mods['mods']), true)
            ) {
                $canMod = true;
            }
        }

        return $canMod;
    }

    private function quickEditPost(ModelsTopic $modelsTopic, int $pid): void
    {
        if (!$this->request->isJSAccess()) {
            $this->page->location("?act=post&how=edit&tid={$modelsTopic->id}&pid={$pid}");

            return;
        }

        $this->page->command('softurl');
        $post = Post::selectOne($this->database, Database::WHERE_ID_EQUALS, $pid);

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

        $post = Post::selectOne(
            $this->database,
            Database::WHERE_ID_EQUALS,
            $pid,
        );

        if ($post === null) {
            $error = "That post doesn't exist!";
            $this->page->command('alert', $error);
            $this->page->append('PAGE', $this->template->meta('error', $error));

            return;
        }

        $author = Member::selectOne(
            $this->database,
            Database::WHERE_ID_EQUALS,
            $post->auth_id,
        );

        if ($this->request->both('qreply')) {
            $this->page->command(
                'updateqreply',
                '[quote=' . $author->name . ']' . $post->post . '[/quote]'
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
                header('Location:?act=post&tid=' . $modelsTopic->id);
            }
        }

        $this->page->command('softurl');
    }

    private function getLastPost(int $tid): void
    {
        $result = $this->database->select(
            [
                'MAX(`id`) AS `lastpid`',
                'COUNT(`id`) AS `numposts`',
            ],
            'posts',
            'WHERE `tid`=?',
            $tid,
        );
        $post = $this->database->arow($result);
        $this->database->disposeresult($result);

        if (!$post) {
            $this->page->location('?');

            return;
        }

        $this->page->command('softurl');
        $this->page->location(
            "?act=vt{$tid}&page=" . ceil($post['numposts'] / $this->numperpage)
                . '&pid=' . $post['lastpid'] . '#pid_' . $post['lastpid'],
        );
    }

    private function findPost(ModelsTopic $modelsTopic, int $postId): void
    {
        $postPosition = null;
        $post = Post::selectOne($this->database, Database::WHERE_ID_EQUALS, $postId);
        if ($post === null) {
            return;
        }

        $posts = Post::selectMany($this->database, 'WHERE tid=?', $post->tid);
        foreach ($posts as $index => $post) {
            if ($post->id === $postId) {
                $postId = $post->id;
                $postPosition = $index;

                break;
            }
        }

        $this->page->command('softurl');
        if ($postPosition === null) {
            $this->page->command('alert', "that post doesn't exist");

            return;
        }

        $pageNumber = (int) ceil($postPosition / $this->numperpage);
        $this->page->location(
            "?act=vt{$modelsTopic->id}&page={$pageNumber}&pid={$postId}#pid_{$postId}",
        );
    }

    private function markRead(ModelsTopic $modelsTopic): void
    {
        $topicsread = $this->jax->parseReadMarkers($this->session->get('topicsread'));
        $topicsread[$modelsTopic->id] = Carbon::now()->getTimestamp();
        $this->session->set('topicsread', json_encode($topicsread));
    }

    private function viewRSS(ModelsTopic $modelsTopic): void
    {
        $boardURL = $this->domainDefinitions->getBoardURL();
        $rssFeed = new RSSFeed(
            [
                'description' => $this->textFormatting->wordfilter($modelsTopic->subtitle),
                'link' => "{$boardURL}?act=vt{$modelsTopic->id}",
                'title' => $this->textFormatting->wordfilter($modelsTopic->title),
            ],
        );
        $posts = Post::selectMany(
            $this->database,
            Database::WHERE_ID_IN,
            $modelsTopic->id,
        );
        $authors = $this->fetchMembersById(array_map(static fn($post): ?int => $post->auth_id, $posts));

        foreach ($posts as $post) {
            $rssFeed->additem(
                [
                    'description' => $this->textFormatting->blockhtml($this->textFormatting->theWorks($post->post)),
                    'guid' => $post->id,
                    'link' => "{$boardURL}?act=vt{$modelsTopic->id}&amp;findpost={$post->id}",
                    'pubDate' => gmdate('r', $this->database->datetimeAsTimestamp($post->date)),
                    'title' => $authors[$post->auth_id]->display_name . ':',
                ],
            );
        }

        $rssFeed->publish();
    }
}
