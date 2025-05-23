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
use Jax\Page;
use Jax\Page\Topic\Poll;
use Jax\Page\Topic\Reactions;
use Jax\Request;
use Jax\RSSFeed;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_flip;
use function ceil;
use function explode;
use function gmdate;
use function header;
use function implode;
use function in_array;
use function is_numeric;
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
        preg_match('@\d+$@', (string) $this->request->both('act'), $act);
        $tid = (int) $act[0] ?: 0;

        if ($tid === 0) {
            $this->page->location('?');

            return;
        }

        $edit = (int) $this->request->asString->both('edit');
        $findPost = (int) $this->request->asString->both('findpost');
        $listRating = $this->request->both('listrating');
        $ratePost = $this->request->both('ratepost');
        $this->pageNumber = max((int) $this->request->both('page') - 1, 0);

        $topic = $this->fetchTopicData($tid);

        if (!$topic || !$topic['fperms']['read']) {
            $this->page->location('?');

            return;
        }

        if (
            $this->request->both('qreply') !== null
            && !$this->request->isJSUpdate()
        ) {
            if (
                $this->request->isJSAccess()
                && !$this->request->isJSDirectLink()
            ) {
                $this->quickReplyForm($topic);

                return;
            }

            $this->page->location('?act=post&tid=' . $tid);

            return;
        }


        if ($ratePost !== null) {
            $this->reactions->toggleReaction((int) $ratePost, (int) $this->request->both('niblet'));

            return;
        }

        if ($this->request->both('votepoll') !== null) {
            $this->poll->vote($topic);

            return;
        }

        if ($findPost !== 0) {
            $this->findPost($topic, $findPost);

            return;
        }

        if ($this->request->both('getlast') !== null) {
            $this->getLastPost($tid);

            return;
        }

        if ($edit !== 0) {
            $this->quickEditPost($topic, $edit);

            return;
        }

        if ($this->request->both('quote') !== null) {
            $this->multiQuote($topic);

            return;
        }

        if ($this->request->both('markread') !== null) {
            $this->markRead($topic);

            return;
        }

        if ($listRating !== null) {
            $this->reactions->listReactions((int) $listRating);

            return;
        }

        if ($this->request->isJSUpdate()) {
            $this->update($topic);

            return;
        }

        if ($this->request->both('fmt') === 'RSS') {
            $this->viewRSS($topic);

            return;
        }

        $this->viewTopic($topic);
    }

    /**
     * @return ?array<string,mixed>
     */
    private function fetchTopicData(int $tid): ?array
    {
        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT
                    a.`id`
                    , a.`title` AS `topic_title`
                    , a.`locked` AS `locked`
                    , UNIX_TIMESTAMP(a.`lp_date`) AS `lp_date`
                    , b.`title` AS `forum_title`
                    , b.`perms` AS `fperms`
                    , c.`id` AS `cat_id`
                    , c.`title` AS `cat_title`
                    , a.`fid` AS `fid`
                    , a.`poll_q` AS `poll_q`
                    , a.`poll_type` AS `poll_type`
                    , a.`poll_choices` AS `poll_choices`
                    , a.`poll_results` AS `poll_results`
                    , a.`subtitle` AS `subtitle`
                FROM %t a
                LEFT JOIN %t b ON a.`fid` = b.`id`
                LEFT JOIN %t AS c ON b.`cat_id` = c.`id`
                WHERE a.`id` = ?
                LIMIT 1

                SQL,
            ['topics', 'forums', 'categories'],
            $tid,
        );
        $topicData = $this->database->arow($result);
        $this->database->disposeresult($result);

        if ($topicData === null) {
            return null;
        }

        $topicData['topic_title'] = $this->textFormatting->wordfilter($topicData['topic_title']);
        $topicData['subtitle'] = $this->textFormatting->wordfilter($topicData['subtitle']);
        $topicData['fperms'] = $this->user->getForumPerms($topicData['fperms']);

        return $topicData;
    }

    private function viewTopic(array $topic): void
    {
        $tid = $topic['id'];
        if (
            !$this->user->isGuest()
            && $topic['lp_date'] > $this->user->get('last_visit')
        ) {
            $this->markRead($topic);
        }

        $this->page->setPageTitle($topic['topic_title']);
        $this->session->set('location_verbose', "In topic '" . $topic['topic_title'] . "'");

        // Fix this to work with subforums.
        $this->page->setBreadCrumbs(
            [
                "?act=vc{$topic['cat_id']}" => $topic['cat_title'],
                "?act=vf{$topic['fid']}" => $topic['forum_title'],
                "?act=vt{$tid}" => $topic['topic_title'],
            ],
        );

        // Generate pages.
        $result = $this->database->safeselect(
            'COUNT(`id`) as postcount',
            'posts',
            'WHERE `tid`=?',
            $tid,
        );
        $postCount = $this->database->arow($result)['postcount'];
        $this->database->disposeresult($result);

        $totalpages = (int) ceil($postCount / $this->numperpage);
        $pagelist = '';
        foreach ($this->jax->pages($totalpages, $this->pageNumber + 1, 10) as $pageNumber) {
            $pagelist .= $this->template->meta(
                'topic-pages-part',
                $tid,
                $pageNumber,
                $pageNumber === $this->pageNumber + 1 ? ' class="active"' : '',
                $pageNumber,
            );
        }

        // Are they on the last page? This stores a session variable.
        $this->session->addVar('topic_lastpage', $this->pageNumber + 1 === $totalpages);

        // If it's a poll, put it in.
        $poll = $topic['poll_type']
            ? $this->poll->render($topic)
            : '';

        // Generate post listing.
        $page = $this->template->meta('topic-table', $this->postsintooutput($topic));
        $page = $this->template->meta(
            'topic-wrapper',
            $topic['topic_title']
                . ($topic['subtitle'] ? ', ' . $topic['subtitle'] : ''),
            $page,
            '<a href="./?act=vt' . $tid . '&amp;fmt=RSS" class="social rss" title="RSS Feed for this Topic" target="_blank">RSS</a>',
        );

        // Add buttons.
        $buttons = [
            '',
            '',
            '',
        ];

        if ($topic['fperms']['start']) {
            $buttons[0] = "<a href='?act=post&fid=" . $topic['fid'] . "'>"
                . $this->template->meta(
                    $this->template->metaExists('button-newtopic')
                        ? 'button-newtopic'
                        : 'topic-button-newtopic',
                )
                . '</a>';
        }

        if (
            $topic['fperms']['reply']
            && (
                !$topic['locked']
                || $this->user->getPerm('can_override_locked_topics')
            )
        ) {
            $buttons[1] = "<a href='?act=vt{$tid}&qreply=1'>" . $this->template->meta(
                $this->template->metaExists('button-qreply')
                    ? 'button-qreply'
                    : 'topic-button-qreply',
            ) . '</a>';
        }

        if (
            $topic['fperms']['reply']
            && (
                !$topic['locked']
                || $this->user->getPerm('can_override_locked_topics')
            )
        ) {
            $buttons[2] = "<a href='?act=post&tid={$tid}'>" . $this->template->meta(
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

            if ($user['location'] !== "vt{$tid}") {
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
        $this->database->safespecial(
            <<<'SQL'
                UPDATE %t
                SET `views` = `views` + 1
                WHERE `id` = ?

                SQL,
            ['topics'],
            $tid,
        );

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

    /**
     * @param array<string,null|float|int|string> $topic
     */
    private function update(array $topic): void
    {
        $tid = $topic['id'];

        // Check for new posts and append them.
        if ($this->session->get('location') !== "vt{$tid}") {
            $this->session->deleteVar('topic_lastpid');
        }

        if (
            $this->session->getVar('topic_lastpid')
            && $this->session->getVar('topic_lastpage')
        ) {
            $newposts = $this->postsintooutput($topic, $this->session->getVar('topic_lastpid'));
            if ($newposts !== '' && $newposts !== '0') {
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

            if ($user['location'] !== "vt{$tid}") {
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
        if ($oldcache !== '' && $oldcache !== '0') {
            $this->page->command('setoffline', $oldcache);
        }

        $this->session->set('users_online_cache', $newcache);
    }

    /**
     * @param array<string,null|float|int|string> $topic
     */
    private function quickReplyForm(array $topic): void
    {
        $tid = $topic['id'];
        $prefilled = '';
        $this->page->command('softurl');
        if (
            $this->session->getVar('multiquote')
        ) {
            $result = $this->database->safespecial(
                <<<'SQL'
                    SELECT
                        p.`post` AS `post`,
                        m.`display_name` AS `name`
                    FROM %t p
                    LEFT JOIN %t m
                        ON p.`auth_id` = m.`id`
                        WHERE p.`id` IN ?
                    SQL,
                ['posts', 'members'],
                explode(',', $this->session->getVar('multiquote') ?? ''),
            );

            while ($post = $this->database->arow($result)) {
                $prefilled .= '[quote=' . $post['name'] . ']' . $post['post'] . '[/quote]' . PHP_EOL;
            }

            $this->session->deleteVar('multiquote');
        }

        $result = $this->database->safeselect(
            'title',
            'topics',
            Database::WHERE_ID_EQUALS,
            $tid,
        );
        $tdata = $this->database->arow($result);
        $this->database->disposeresult($result);

        $this->page->command(
            'window',
            [
                'content' => $this->template->meta(
                    'topic-reply-form',
                    $tid,
                    $this->textFormatting->blockhtml($prefilled),
                ),
                'id' => 'qreply',
                'resize' => 'textarea',
                'title' => $this->textFormatting->wordfilter($tdata['title']),
            ],
        );
        $this->page->command('updateqreply', '');
    }

    /**
     * @param array<string,null|float|int|string> $topic
     */
    private function postsintooutput(array $topic, $lastpid = 0): string
    {
        $usersonline = $this->database->getUsersOnline();
        $this->config->getSetting('ratings') ?? 0;

        $topicPostCounter = 0;

        $query = $lastpid ? $this->database->safespecial(
            <<<'SQL'
                SELECT m.`id` AS `id`
                    , m.`name` AS `name`
                    , m.`group_id` AS `group_id`
                    , m.`sound_im` AS `sound_im`
                    , m.`sound_shout` AS `sound_shout`
                    , UNIX_TIMESTAMP(m.`last_visit`) AS `last_visit`
                    , m.`display_name` AS `display_name`
                    , m.`friends` AS `friends`
                    , m.`enemies` AS `enemies`
                    , m.`skin_id` AS `skin_id`
                    , m.`nowordfilter` AS `nowordfilter`
                    , m.`wysiwyg` AS `wysiwyg`
                    , m.`avatar` AS `avatar`
                    , m.`usertitle` AS `usertitle`
                    , CONCAT(MONTH(m.`birthdate`), ' ', MONTH(m.`birthdate`)) as `birthday`
                    , m.`mod` AS `mod`
                    , m.`posts` AS `posts`
                    , p.`tid` AS `tid`
                    , p.`id` AS `pid`
                    , p.`ip` AS `ip`
                    , p.`newtopic` AS `newtopic`
                    , p.`post` AS `post`
                    , p.`showsig` AS `showsig`
                    , p.`showemotes` AS `showemotes`
                    , p.`tid` AS `tid`
                    , UNIX_TIMESTAMP(p.`date`) AS `date`
                    , p.`auth_id` AS `auth_id`
                    , p.`rating` AS `rating`
                    , g.`title` AS `title`
                    , g.`icon` AS `icon`
                    , UNIX_TIMESTAMP(p.`edit_date`) AS `edit_date`
                    , p.`editby` AS `editby`
                    , e.`display_name` AS `ename`
                    , e.`group_id` AS `egroup_id`
                FROM %t AS p
                LEFT JOIN %t m
                    ON p.`auth_id` = m.`id`
                LEFT JOIN %t g
                    ON m.`group_id` = g.`id`
                LEFT JOIN %t e
                ON p.`editby` = e.`id`
                WHERE p.`tid` = ?
                  AND p.`id` > ?
                ORDER BY `pid`

                SQL,
            ['posts', 'members', 'member_groups', 'members'],
            $topic['id'],
            $lastpid,
        ) : $this->database->safespecial(
            <<<'SQL'
                SELECT m.`id` AS `id`
                    , m.`name` AS `name`
                    , m.`email` AS `email`
                    , m.`sig` AS `sig`
                    , m.`posts` AS `posts`
                    , m.`group_id` AS `group_id`
                    , m.`avatar` AS `avatar`
                    , m.`usertitle` AS `usertitle`
                    , UNIX_TIMESTAMP(m.`join_date`) AS `join_date`
                    , UNIX_TIMESTAMP(m.`last_visit`) AS `last_visit`
                    , m.`contact_skype` AS `contact_skype`
                    , m.`contact_yim` AS `contact_yim`
                    , m.`contact_msn` AS `contact_msn`
                    , m.`contact_gtalk` AS `contact_gtalk`
                    , m.`contact_aim` AS `contact_aim`
                    , m.`website` AS `website`
                    , m.`birthdate` AS `birthdate`
                    , DAY(m.`birthdate`) AS `dob_day`
                    , MONTH(m.`birthdate`) AS `dob_month`
                    , YEAR(m.`birthdate`) AS `dob_year`
                    , m.`about` AS `about`
                    , m.`display_name` AS `display_name`
                    , m.`full_name` AS `full_name`
                    , m.`contact_steam` AS `contact_steam`
                    , m.`location` AS `location`
                    , m.`gender` AS `gender`
                    , m.`friends` AS `friends`
                    , m.`enemies` AS `enemies`
                    , m.`sound_shout` AS `sound_shout`
                    , m.`sound_im` AS `sound_im`
                    , m.`sound_pm` AS `sound_pm`
                    , m.`sound_postinmytopic` AS `sound_postinmytopic`
                    , m.`sound_postinsubscribedtopic` AS `sound_postinsubscribedtopic`
                    , m.`notify_pm` AS `notify_pm`
                    , m.`notify_postinmytopic` AS `notify_postinmytopic`
                    , m.`notify_postinsubscribedtopic` AS `notify_postinsubscribedtopic`
                    , m.`ucpnotepad` AS `ucpnotepad`
                    , m.`skin_id` AS `skin_id`
                    , m.`contact_twitter` AS `contact_twitter`
                    , m.`contact_discord` AS `contact_discord`
                    , m.`contact_youtube` AS `contact_youtube`
                    , m.`contact_bluesky` AS `contact_bluesky`
                    , m.`email_settings` AS `email_settings`
                    , m.`nowordfilter` AS `nowordfilter`
                    , COALESCE(p.`ip`, m.`ip`) AS `ip`
                    , m.`mod` AS `mod`
                    , m.`wysiwyg` AS `wysiwyg`
                    , p.`tid` AS `tid`
                    , p.`id` AS `pid`
                    , p.`newtopic` AS `newtopic`
                    , p.`post` AS `post`
                    , p.`showsig` AS `showsig`
                    , p.`showemotes` AS `showemotes`
                    , p.`tid` AS `tid`
                    , UNIX_TIMESTAMP(p.`date`) AS `date`
                    , p.`auth_id` AS `auth_id`
                    , p.`rating` AS `rating`
                    , g.`title` AS `title`
                    , g.`icon` AS `icon`
                    , UNIX_TIMESTAMP(p.`edit_date`) AS `edit_date`
                    , p.`editby` AS `editby`
                    , e.`display_name` AS `ename`
                    , e.`group_id` AS `egroup_id`
                FROM %t p
                LEFT JOIN %t m
                    ON p.`auth_id`=m.`id`
                LEFT JOIN %t g
                    ON m.`group_id` = g.`id`
                LEFT JOIN %t e
                    ON p.`editby` = e.`id`
                WHERE p.`tid` = ?
                ORDER BY `newtopic` DESC
                    , `pid` ASC
                LIMIT ?, ?

                SQL,
            ['posts', 'members', 'member_groups', 'members'],
            $topic['id'],
            $topicPostCounter = $this->pageNumber * $this->numperpage,
            $this->numperpage,
        );

        $rows = '';
        foreach ($this->database->arows($query) as $post) {
            if ($this->firstPostID === 0) {
                $this->firstPostID = $post['pid'];
            }

            $postt = $post['post'];

            $postt = $this->textFormatting->theWorks($postt);

            // Post rating content goes here.
            $postrating = $this->reactions->render($post);

            $postbuttons
                // Adds the Edit button
                = ($this->canedit($topic, $post)
                    ? "<a href='?act=vt" . $topic['id'] . '&amp;edit=' . $post['pid']
                    . "' class='edit'>" . $this->template->meta('topic-edit-button')
                    . '</a>'
                    : '')
                // Adds the Quote button
                . ($topic['fperms']['reply']
                    ? " <a href='?act=vt" . $topic['id'] . '&amp;quote=' . $post['pid']
                    . "' onclick='RUN.handleQuoting(this);return false;' "
                    . "class='quotepost'>" . $this->template->meta('topic-quote-button') . '</a> '
                    : '')
                // Adds the Moderate options
                . ($this->canModerate($topic)
                    ? "<a href='?act=modcontrols&amp;do=modp&amp;pid=" . $post['pid']
                    . "' class='modpost' onclick='RUN.modcontrols.togbutton(this)'>"
                    . $this->template->meta('topic-mod-button') . '</a>'
                    : '');

            $rows .= $this->template->meta(
                'topic-post-row',
                $post['pid'],
                $topic['id'],
                $post['auth_id'] ? $this->template->meta(
                    'user-link',
                    $post['auth_id'],
                    $post['group_id'],
                    $post['display_name'],
                ) : 'Guest',
                $post['avatar'] ?: $this->template->meta('default-avatar'),
                $post['usertitle'],
                $post['posts'],
                $this->template->meta(
                    'topic-status-'
                        . (isset($usersonline[$post['auth_id']])
                            && $usersonline[$post['auth_id']] ? 'online' : 'offline'),
                ),
                $post['title'],
                $post['auth_id'],
                $postbuttons,
                // ^10
                $this->date->autoDate($post['date']),
                <<<HTML
                    <a href="?act=vt{$topic['id']}&amp;findpost={$post['pid']}&pid={$post['pid']}"
                        onclick="prompt('Link to this post:',this.href);return false"
                        >{$this->template->meta('topic-perma-button')}</a>
                    HTML,
                $postt,
                isset($post['sig']) && $post['sig']
                    ? $this->textFormatting->theWorks($post['sig'])
                    : '',
                $post['auth_id'],
                $post['edit_date'] ? $this->template->meta(
                    'topic-edit-by',
                    $this->template->meta(
                        'user-link',
                        $post['editby'],
                        $post['egroup_id'],
                        $post['ename'],
                    ),
                    $this->date->autoDate($post['edit_date']),
                ) : '',
                $this->user->getPerm('can_moderate')
                    ? '<a href="?act=modcontrols&amp;do=iptools&amp;ip='
                    . $this->ipAddress->asHumanReadable($post['ip']) . '">' . $this->template->meta(
                        'topic-mod-ipbutton',
                        $this->ipAddress->asHumanReadable($post['ip']),
                    ) . '</a>'
                    : '',
                $post['icon'] ? $this->template->meta(
                    'topic-icon-wrapper',
                    $post['icon'],
                ) : '',
                ++$topicPostCounter,
                $postrating,
                // 30 V
                $post['contact_skype'] ?? '',
                $post['contact_discord'] ?? '',
                $post['contact_yim'] ?? '',
                $post['contact_msn'] ?? '',
                $post['contact_gtalk'] ?? '',
                $post['contact_aim'] ?? '',
                $post['contact_youtube'] ?? '',
                $post['contact_steam'] ?? '',
                $post['contact_twitter'] ?? '',
                $post['contact_bluesky'] ?? '',
                '',
                '',
                '',
            );
            $lastpid = $post['pid'];
        }

        $this->session->addVar('topic_lastpid', $lastpid);

        return $rows;
    }

    /**
     * @param array<string,null|float|int|string> $topic
     * @param array<string,null|float|int|string> $post
     */
    private function canedit(array $topic, array $post): bool
    {
        if ($this->canModerate($topic)) {
            return true;
        }

        return $post['auth_id']
            && ($post['newtopic']
                ? $this->user->getPerm('can_edit_topics')
                : $this->user->getPerm('can_edit_posts'))
            && $post['auth_id'] === $this->user->get('id');
    }

    /**
     * @param array<string,null|float|int|string> $topic
     */
    private function canModerate(array $topic): bool
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
            $result = $this->database->safespecial(
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
                $this->database->basicvalue($topic['id']),
            );
            $mods = $this->database->arow($result);
            $this->database->disposeresult($result);
            if (in_array($this->user->get('id'), explode(',', (string) $mods['mods']), true)) {
                $canMod = true;
            }
        }

        return $canMod;
    }

    /**
     * @param array<string,null|float|int|string> $topic
     */
    private function quickEditPost(array $topic, int $pid): void
    {
        if (!$this->request->isJSAccess()) {
            $this->page->location('?act=post&pid=' . $pid);
        }

        $this->page->command('softurl');
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

        $hiddenfields = $this->jax->hiddenFormFields(
            [
                'act' => 'post',
                'how' => 'edit',
                'pid' => $pid,
            ],
        );

        if (!$this->request->isJSNewLocation()) {
            return;
        }

        if (!$post) {
            $this->page->command('alert', 'Post not found!');

            return;
        }

        if (!$this->canedit($topic, $post)) {
            $this->page->command('alert', "You don't have permission to edit this post.");

            return;
        }

        if ($post['newtopic']) {
            $hiddenfields .= $this->jax->hiddenFormFields(
                [
                    'tid' => $post['tid'],
                ],
            );
            $result = $this->database->safeselect(
                [
                    'subtitle',
                    'title',
                ],
                'topics',
                Database::WHERE_ID_EQUALS,
                $post['tid'],
            );
            $topic = $this->database->arow($result);
            $this->database->disposeresult($result);

            $form = $this->template->meta(
                'topic-qedit-topic',
                $hiddenfields,
                $topic['title'],
                $topic['subtitle'],
                $this->textFormatting->blockhtml($post['post']),
            );
        } else {
            $form = $this->template->meta(
                'topic-qedit-post',
                $hiddenfields,
                $this->textFormatting->blockhtml($post['post']),
                $pid,
            );
        }

        $this->page->command('update', "#pid_{$pid} .post_content", $form);
    }

    /**
     * @param array<string,null|float|int|string> $topic
     */
    private function multiQuote(array $topic): void
    {
        $pid = $this->request->both('quote');
        $post = false;
        if ($pid && is_numeric($pid)) {
            $result = $this->database->safespecial(
                <<<'SQL'
                    SELECT p.`post` AS `post`
                        , m.`display_name` AS `name`
                    FROM %t p
                    LEFT JOIN %t m
                      ON p.`auth_id` = m.`id`
                    WHERE p.`id` = ?

                    SQL,
                ['posts', 'members'],
                $pid,
            );
            $post = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        if (!$post) {
            $error = "That post doesn't exist!";
            $this->page->command('alert', $error);
            $this->page->append('PAGE', $this->template->meta('error', $error));

            return;
        }

        if ($this->request->both('qreply')) {
            $this->page->command(
                'updateqreply',
                '[quote=' . $post['name'] . ']' . $post['post'] . '[/quote]'
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
                $this->quickReplyForm($topic);
            } else {
                header('Location:?act=post&tid=' . $topic['id']);
            }
        }

        $this->page->command('softurl');
    }

    private function getLastPost(int $tid): void
    {
        $result = $this->database->safeselect(
            'MAX(`id`) AS `lastpid`,COUNT(`id`) AS `numposts`',
            'posts',
            'WHERE `tid`=?',
            $tid,
        );
        $f = $this->database->arow($result);
        $this->database->disposeresult($result);

        $this->page->command('softurl');
        $this->page->location(
            "?act=vt{$tid}&page=" . ceil($f['numposts'] / $this->numperpage)
                . '&pid=' . $f['lastpid'] . '#pid_' . $f['lastpid'],
        );
    }

    /**
     * @param array<string,null|float|int|string> $topic
     */
    private function findPost(array $topic, int $postId): void
    {
        $postPosition = null;
        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT
                    `id`
                FROM %t
                WHERE tid=(
                    SELECT tid
                    FROM %t
                    WHERE `id` = ?
                )
                ORDER BY `id` ASC

                SQL,
            ['posts', 'posts'],
            $postId,
        );
        foreach ($this->database->arows($result) as $index => $post) {
            if ($post['id'] === $postId) {
                $postId = $post['id'];
                $postPosition = (int) $index;

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
            "?act=vt{$topic['id']}&page={$pageNumber}&pid={$postId}#pid_{$postId}",
        );
    }

    /**
     * @param array<string,null|float|int|string> $topic
     */
    private function markRead(array $topic): void
    {
        $topicsread = $this->jax->parseReadMarkers($this->session->get('topicsread'));
        $topicsread[$topic['id']] = Carbon::now()->getTimestamp();
        $this->session->set('topicsread', json_encode($topicsread));
    }

    /**
     * @param array<string,null|float|int|string> $topic
     */
    private function viewRSS(array $topic): void
    {
        $tid = $topic['id'];
        $boardURL = $this->domainDefinitions->getBoardURL();
        $rssFeed = new RSSFeed(
            [
                'description' => $topic['subtitle'],
                'link' => "{$boardURL}?act=vt{$tid}",
                'title' => $topic['topic_title'],
            ],
        );
        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT p.`id` AS `id`
                    , p.`post` AS `post`
                    , UNIX_TIMESTAMP(p.`date`) AS `date`
                    , m.`display_name` AS `display_name`
                FROM %t p
                LEFT JOIN %t m
                    ON p.`auth_id` = m.`id`
                    WHERE p.`tid` = ?

                SQL,
            ['posts', 'members'],
            $this->database->basicvalue($tid),
        );
        foreach ($this->database->arows($result) as $post) {
            $rssFeed->additem(
                [
                    'description' => $this->textFormatting->blockhtml($this->textFormatting->theWorks($post['post'])),
                    'guid' => $post['id'],
                    'link' => "{$boardURL}?act=vt{$tid}&amp;findpost={$post['id']}",
                    'pubDate' => gmdate('r', $post['date']),
                    'title' => $post['display_name'] . ':',
                ],
            );
        }

        $rssFeed->publish();
    }
}
