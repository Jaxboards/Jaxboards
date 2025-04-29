<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\RSSFeed;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_diff;
use function array_flip;
use function array_key_exists;
use function array_map;
use function array_merge;
use function ceil;
use function count;
use function explode;
use function gmdate;
use function header;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function json_decode;
use function json_encode;
use function preg_match;
use function round;
use function time;

use const PHP_EOL;

final class Topic
{
    private int $tid = 0;

    private int $pageNumber = 0;

    private int $numperpage = 10;

    private bool $canMod = false;

    private int $firstPostID = 0;

    private ?array $topicdata = null;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly Jax $jax,
        private readonly IPAddress $ipAddress,
        private readonly Page $page,
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
        $this->tid = (int) $act[0] ?: 0;

        if ($this->tid === 0) {
            $this->page->location('?');

            return;
        }

        $this->getTopicData($this->tid);
        if (!$this->topicdata || !$this->topicdata['fperms']['read']) {
            $this->page->location('?');

            return;
        }

        $this->pageNumber = (int) $this->request->both('page');
        if ($this->pageNumber <= 0 || !is_numeric($this->pageNumber)) {
            $this->pageNumber = 1;
        }

        --$this->pageNumber;

        if (
            $this->request->both('qreply') !== null
            && !$this->request->isJSUpdate()
        ) {
            if (
                $this->request->isJSAccess()
                && !$this->request->isJSDirectLink()
            ) {
                $this->qreplyform($this->tid);

                return;
            }

            $this->page->location('?act=post&tid=' . $this->tid);

            return;
        }

        if ($this->request->both('ratepost') !== null) {
            $this->ratepost($this->request->both('ratepost'), $this->request->both('niblet'));

            return;
        }

        if ($this->request->both('votepoll') !== null) {
            $this->votepoll();

            return;
        }

        if ($this->request->both('findpost') !== null) {
            $this->findpost($this->request->both('findpost'));

            return;
        }

        if ($this->request->both('getlast') !== null) {
            $this->getlastpost($this->tid);

            return;
        }

        if ($this->request->both('edit') !== null) {
            $this->qeditpost($this->request->both('edit'));

            return;
        }

        if ($this->request->both('quote') !== null) {
            $this->multiquote($this->tid);

            return;
        }

        if ($this->request->both('markread') !== null) {
            $this->markread($this->tid);

            return;
        }

        if (
            $this->request->both('listrating') !== null
        ) {
            $this->listrating($this->request->both('listrating'));

            return;
        }

        if ($this->request->isJSUpdate()) {
            $this->update($this->tid);

            return;
        }

        if ($this->request->both('fmt') === 'RSS') {
            $this->viewrss($this->tid);

            return;
        }

        $this->viewtopic($this->tid);
    }

    private function getTopicData(int $tid): void
    {
        $result = $this->database->safespecial(
            <<<'MySQL'
                SELECT a.`title` AS `topic_title`
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

                MySQL,
            ['topics', 'forums', 'categories'],
            $tid,
        );
        $this->topicdata = $this->database->arow($result);
        $this->database->disposeresult($result);

        $this->topicdata['topic_title'] = $this->textFormatting->wordfilter($this->topicdata['topic_title']);
        $this->topicdata['subtitle'] = $this->textFormatting->wordfilter($this->topicdata['subtitle']);
        $this->topicdata['fperms'] = $this->user->parseForumPerms($this->topicdata['fperms']);
    }

    private function viewtopic($tid): void
    {
        if (
            !$this->user->isGuest()
            && $this->topicdata['lp_date'] > $this->user->get('last_visit')
        ) {
            $this->markread($tid);
        }

        $this->page->setPageTitle($this->topicdata['topic_title']);
        $this->session->set('location_verbose', "In topic '" . $this->topicdata['topic_title'] . "'");

        // Fix this to work with subforums.
        $this->page->setBreadCrumbs(
            [
                $this->topicdata['cat_title'] => '?act=vc' . $this->topicdata['cat_id'],
                $this->topicdata['forum_title'] => '?act=vf' . $this->topicdata['fid'],
                $this->topicdata['topic_title'] => "?act=vt{$tid}",
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
        $poll = $this->topicdata['poll_type'] ? $this->template->meta(
            'box',
            " id='poll'",
            $this->topicdata['poll_q'],
            $this->generatepoll(
                $this->topicdata['poll_type'],
                json_decode((string) $this->topicdata['poll_choices']),
                $this->topicdata['poll_results'],
            ),
        ) : '';

        // Generate post listing.
        $page = $this->template->meta('topic-table', $this->postsintooutput());
        $page = $this->template->meta(
            'topic-wrapper',
            $this->topicdata['topic_title']
            . ($this->topicdata['subtitle'] ? ', ' . $this->topicdata['subtitle'] : ''),
            $page,
            '<a href="./?act=vt' . $tid . '&amp;fmt=RSS" class="social rss" title="RSS Feed for this Topic">RSS</a>',
        );

        // Add buttons.
        $buttons = [
            '',
            '',
            '',
        ];

        if ($this->topicdata['fperms']['start']) {
            $buttons[0] = "<a href='?act=post&fid=" . $this->topicdata['fid'] . "'>"
            . $this->template->meta(
                $this->template->metaExists('button-newtopic')
                    ? 'button-newtopic'
                    : 'topic-button-newtopic',
            )
            . '</a>';
        }

        if (
            $this->topicdata['fperms']['reply']
            && (
                !$this->topicdata['locked']
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
            $this->topicdata['fperms']['reply']
            && (
                !$this->topicdata['locked']
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
            $buttons,
        ) . $page . $this->template->meta(
            'topic-pages-bottom',
            $pagelist,
        ) . $this->template->meta(
            'topic-buttons-bottom',
            $buttons,
        );

        // Update view count.
        $this->database->safespecial(
            <<<'MySQL'
                UPDATE %t
                SET `views` = `views` + 1
                WHERE `id` = ?

                MySQL,
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
                $this->page->command('scrollToPost', $this->firstPostID, 1);

                return;
            }

            return;
        }

        $this->page->append('PAGE', $page);
    }

    private function update($tid): void
    {

        // Check for new posts and append them.
        if ($this->session->get('location') !== "vt{$tid}") {
            $this->session->deleteVar('topic_lastpid');
        }

        if (
            $this->session->getVar('topic_lastpid')
            && $this->session->getVar('topic_lastpage')
        ) {
            $newposts = $this->postsintooutput($this->session->getVar('topic_lastpid'));
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

    private function qreplyform($tid): void
    {
        $prefilled = '';
        $this->page->command('softurl');
        if (
            $this->session->getVar('multiquote')
        ) {
            $result = $this->database->safespecial(
                <<<'MySQL'
                    SELECT
                        p.`post` AS `post`,
                        m.`display_name` AS `name`
                    FROM %t p
                    LEFT JOIN %t m
                        ON p.`auth_id` = m.`id`
                        WHERE p.`id` IN ?
                    MySQL,
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
            'WHERE `id`=?',
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

    private function postsintooutput($lastpid = 0): string
    {
        $usersonline = $this->database->getUsersOnline();
        $ratingConfig = $this->config->getSetting('ratings') ?? 0;

        $topicPostCounter = 0;

        $query = $lastpid ? $this->database->safespecial(
            <<<'MySQL'
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

                MySQL,
            ['posts', 'members', 'member_groups', 'members'],
            $this->tid,
            $lastpid,
        ) : $this->database->safespecial(
            <<<'MySQL'
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

                MySQL,
            ['posts', 'members', 'member_groups', 'members'],
            $this->tid,
            $topicPostCounter = $this->pageNumber * $this->numperpage,
            $this->numperpage,
        );

        $rows = '';
        while ($post = $this->database->arow($query)) {
            if ($this->firstPostID === 0) {
                $this->firstPostID = $post['pid'];
            }

            $postt = $post['post'];

            $postt = $this->textFormatting->theworks($postt);

            // Post rating content goes here.
            $postrating = '';
            $showrating = '';
            if (($ratingConfig & 1) !== 0) {
                $prating = [];
                $postratingbuttons = '';
                if ($post['rating']) {
                    $prating = json_decode((string) $post['rating'], true);
                }

                $rniblets = $this->database->getRatingNiblets();
                if ($rniblets) {
                    foreach ($rniblets as $nibletIndex => $niblet) {
                        $postratingbuttons .= '<a href="?act=vt' . $this->tid . '&amp;ratepost='
                            . $post['pid'] . '&amp;niblet=' . $nibletIndex . '">'
                            . $this->template->meta(
                                'rating-niblet',
                                $niblet['img'],
                                $niblet['title'],
                            ) . '</a>';
                        if (!isset($prating[$nibletIndex])) {
                            continue;
                        }

                        if (!$prating[$nibletIndex]) {
                            continue;
                        }

                        $num = 'x' . count($prating[$nibletIndex]);
                        $postratingbuttons .= $num;
                        $showrating .= $this->template->meta(
                            'rating-niblet',
                            $niblet['img'],
                            $niblet['title'],
                        ) . $num;
                    }

                    $postrating = $this->template->meta(
                        'rating-wrapper',
                        $postratingbuttons,
                        ($ratingConfig & 2) === 0
                            ? '<a href="?act=vt' . $this->tid
                        . '&amp;listrating=' . $post['pid'] . '">(List)</a>'
                            : '',
                        $showrating,
                    );
                }
            }

            $postbuttons
            // Adds the Edit button
            = ($this->canedit($post)
                ? "<a href='?act=vt" . $this->tid . '&amp;edit=' . $post['pid']
            . "' class='edit'>" . $this->template->meta('topic-edit-button')
            . '</a>'
                : '')
            // Adds the Quote button
            . ($this->topicdata['fperms']['reply']
                ? " <a href='?act=vt" . $this->tid . '&amp;quote=' . $post['pid']
            . "' onclick='RUN.handleQuoting(this);return false;' "
            . "class='quotepost'>" . $this->template->meta('topic-quote-button') . '</a> '
                : '')
            // Adds the Moderate options
            . ($this->canModerate()
                ? "<a href='?act=modcontrols&amp;do=modp&amp;pid=" . $post['pid']
            . "' class='modpost' onclick='RUN.modcontrols.togbutton(this)'>"
            . $this->template->meta('topic-mod-button') . '</a>'
                : '');

            $rows .= $this->template->meta(
                'topic-post-row',
                $post['pid'],
                $this->tid,
                $post['auth_id'] ? $this->template->meta(
                    'user-link',
                    $post['auth_id'],
                    $post['group_id'],
                    $post['display_name'],
                ) : 'Guest',
                $this->jax->pick($post['avatar'], $this->template->meta('default-avatar')),
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
                $this->jax->date($post['date']),
                '<a href="?act=vt' . $this->tid . '&amp;findpost=' . $post['pid']
                . '" onclick="prompt(\'Link to this post:\',this.href);return false">'
                . $this->template->meta('topic-perma-button') . '</a>',
                $postt,
                isset($post['sig']) && $post['sig']
                    ? $this->textFormatting->theworks($post['sig'])
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
                    $this->jax->date($post['edit_date']),
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

    private function canedit($post): bool
    {
        if ($this->canModerate()) {
            return true;
        }

        return $post['auth_id']
        && ($post['newtopic']
            ? $this->user->getPerm('can_edit_topics')
            : $this->user->getPerm('can_edit_posts'))
        && $post['auth_id'] === $this->user->get('id');
    }

    private function canModerate(): bool
    {
        if ($this->canMod) {
            return $this->canMod;
        }

        $canMod = false;
        if ($this->user->getPerm('can_moderate')) {
            $canMod = true;
        }

        if ($this->user->get('mod')) {
            $result = $this->database->safespecial(
                <<<'MySQL'
                    SELECT `mods`
                    FROM %t
                    WHERE `id` = (
                        SELECT `fid`
                        FROM %t
                        WHERE `id` = ?
                    )
                    MySQL,
                ['forums', 'topics'],
                $this->database->basicvalue($this->tid),
            );
            $mods = $this->database->arow($result);
            $this->database->disposeresult($result);
            if (in_array($this->user->get('id'), explode(',', (string) $mods['mods']), true)) {
                $canMod = true;
            }
        }

        return $this->canMod = $canMod;
    }

    private function generatepoll($type, $choices, $results): string
    {
        if (!$choices) {
            $choices = [];
        }

        $page = '';
        $usersvoted = [];
        $voted = false;

        if (!$this->user->isGuest()) {
            // Accomplish three things at once:
            // * Determine if the user has voted.
            // * Count up the number of votes.
            // * Parse the result set.
            $presults = [];

            $totalvotes = 0;
            $numvotes = [];
            foreach (explode(';', (string) $results) as $optionIndex => $voters) {
                $presults[$optionIndex] = array_map(static fn($voterId): int => (int) $voterId, explode(',', $voters));
                $totalvotes += ($numvotes[$optionIndex] = count($presults[$optionIndex]));
                if (in_array($this->user->get('id'), $presults[$optionIndex], true)) {
                    $voted = true;
                }

                foreach ($presults[$optionIndex] as $user) {
                    $usersvoted[$user] = 1;
                }
            }
        }

        $usersvoted = count($usersvoted);

        if ($voted) {
            $page .= '<table>';
            foreach ($choices as $k => $v) {
                $page .= "<tr><td>{$v}</td><td class='numvotes'>"
                    . $numvotes[$k] . ' votes ('
                    . round($numvotes[$k] / $totalvotes * 100, 2)
                    . "%)</td><td style='width:200px'><div class='bar' style='width:"
                    . round($numvotes[$k] / $totalvotes * 100)
                    . "%;'></div></td></tr>";
            }

            $page .= "<tr><td colspan='3' class='totalvotes'>Total Votes: "
                . $usersvoted . '</td></tr>';

            return $page . '</table>';
        }

        $page = $this->jax->hiddenFormFields(
            [
                'act' => 'vt' . $this->tid,
                'votepoll' => 1,
            ],
        );

        foreach ($choices as $k => $v) {
            $page .= "<div class='choice'>"
            . (
                $type === 'multi'
                ? "<input type='checkbox' name='choice[]' value='{$k}' id='poll_{$k}' />"
                : "<input type='radio' name='choice' value='{$k}' id='poll_{$k}' /> "
            )
            . "<label for='poll_{$k}'>{$v}</label>"
            . '</div>';
        }

        return "<form method='post' action='?' data-ajax-form='true'>"
            . $page
            . "<div class='buttons'>"
            . "<input type='submit' value='Vote'>"
            . '</div>'
            . '</form>';
    }

    private function votepoll(): void
    {
        $error = null;
        if ($this->user->isGuest()) {
            $error = 'You must be logged in to vote!';
        } else {
            $result = $this->database->safeselect(
                [
                    'poll_q',
                    'poll_results',
                    'poll_choices',
                    'poll_type',
                ],
                'topics',
                'WHERE `id`=?',
                $this->tid,
            );
            $row = $this->database->arow($result);
            $this->database->disposeresult($result);

            $choice = $this->request->both('choice');
            $choices = json_decode((string) $row['poll_choices'], true);
            $numchoices = count($choices);
            $results = $row['poll_results'];
            if ($results) {
                $results = explode(';', (string) $results);
                foreach ($results as $k => $v) {
                    $results[$k] = $v !== '' && $v !== '0'
                        ? explode(',', $v)
                        : [];
                }
            } else {
                $results = [];
            }

            // Results is now an array of arrays, the keys of the parent array
            // correspond to the choices while the arrays within the array
            // correspond to a collection of user IDs that have voted for that
            // choice.
            $voted = false;
            foreach ($results as $v) {
                foreach ($v as $v2) {
                    if ($v2 === $this->user->get('id')) {
                        $voted = true;

                        break;
                    }
                }
            }

            if ($voted) {
                $error = 'You have already voted on this poll!';
            }

            if ($row['poll_type'] === 'multi') {
                if (is_array($choice)) {
                    foreach ($choice as $c) {
                        if (is_numeric($c) && $c < $numchoices && $c >= 0) {
                            continue;
                        }

                        $error = 'Invalid choices';
                    }
                } else {
                    $error = 'Invalid Choice';
                }
            } elseif (
                !is_numeric($choice)
                || $choice >= $numchoices
                || $choice < 0
            ) {
                $error = 'Invalid choice';
            }
        }

        if ($error !== null) {
            $this->page->command('error', $error);

            return;
        }

        if ($row['poll_type'] === 'multi') {
            foreach ($choice as $c) {
                $results[$c][] = $this->user->get('id');
            }
        } else {
            $results[$choice][] = $this->user->get('id');
        }

        $presults = [];
        for ($x = 0; $x < $numchoices; ++$x) {
            $presults[$x] = isset($results[$x]) && $results[$x]
                ? implode(',', $results[$x]) : '';
        }

        $presults = implode(';', $presults);
        $this->page->command(
            'update',
            '#poll .content',
            $this->generatePoll(
                $row['poll_type'],
                $choices,
                $presults,
            ),
            '1',
        );

        $this->database->safeupdate(
            'topics',
            [
                'poll_results' => $presults,
            ],
            'WHERE `id`=?',
            $this->tid,
        );
    }

    private function ratepost(
        array|string $postid,
        null|array|string $nibletid,
    ): void {
        $this->page->command('softurl');
        if (!is_numeric($postid) || !is_numeric($nibletid)) {
            return;
        }

        $result = $this->database->safeselect(
            ['rating'],
            'posts',
            'WHERE `id`=?',
            $this->database->basicvalue($postid),
        );
        $post = $this->database->arow($result);
        $this->database->disposeresult($result);

        $niblets = $this->database->getRatingNiblets();
        $ratings = [];

        $error = match (true) {
            $this->user->isGuest() => 'You must be logged in to rate posts.',
            !$post => "That post doesn't exist.",
            !$niblets[$nibletid] => 'Invalid rating',
            default => null,
        };

        if ($error !== null) {
            $this->page->command('error', $error);

            return;
        }

        $ratings = json_decode((string) $post['rating'], true);
        if (!$ratings) {
            $ratings = [];
        }

        if (!array_key_exists((int) $nibletid, $ratings)) {
            $ratings[(int) $nibletid] = [];
        }

        $unrate = in_array((int) $this->user->get('id'), $ratings[(int) $nibletid], true);
        // Unrate
        if ($unrate) {
            $ratings[(int) $nibletid] = array_diff($ratings[(int) $nibletid], [(int) $this->user->get('id')]);
        } else {
            // Rate
            $ratings[(int) $nibletid][] = (int) $this->user->get('id');
        }

        $this->database->safeupdate(
            'posts',
            [
                'rating' => json_encode($ratings),
            ],
            'WHERE `id`=?',
            $this->database->basicvalue($postid),
        );
        $this->page->command('alert', $unrate ? 'Unrated!' : 'Rated!');
    }

    private function qeditpost(array|string $pid): void
    {
        if (!is_numeric($pid)) {
            return;
        }

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
            'WHERE `id`=?',
            $pid,
        );
        $post = $this->database->arow($result);
        $this->database->disposeresult($result);

        $hiddenfields = $this->jax->hiddenFormFields(
            [
                'act' => 'post',
                'how' => 'qedit',
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

        if (!$this->canedit($post)) {
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
                'WHERE `id`=?',
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

    private function multiquote($tid): void
    {
        $pid = $this->request->both('quote');
        $post = false;
        if ($pid && is_numeric($pid)) {
            $result = $this->database->safespecial(
                <<<'MySQL'
                    SELECT p.`post` AS `post`
                        , m.`display_name` AS `name`
                    FROM %t p
                    LEFT JOIN %t m
                      ON p.`auth_id` = m.`id`
                    WHERE p.`id` = ?

                    MySQL,
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
            $multiquote = (string) ($this->session->getVar('multiquote') ?? '');
            $multiquotes = $multiquote !== '' ? explode(',', $multiquote) : [];
            if (!in_array((string) $pid, $multiquotes, true)) {
                $multiquotes[] = $pid;
                $this->session->addVar(
                    'multiquote',
                    implode(',', $multiquotes),
                );
            }

            // This line toggles whether or not the qreply window should open
            // on quote.
            if ($this->request->isJSAccess()) {
                $this->qreplyform($tid);
            } else {
                header('Location:?act=post&tid=' . $tid);
            }
        }

        $this->page->command('softurl');
    }

    private function getlastpost($tid): void
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

    private function findpost(array|string $pid): void
    {
        $couldntfindit = false;
        if (!is_numeric($pid)) {
            $couldntfindit = true;
        } else {
            $result = $this->database->safespecial(
                <<<'MySQL'
                    SELECT
                        `id`
                    FROM %t
                    WHERE tid=(
                        SELECT tid
                        FROM %t
                        WHERE `id` = ?
                    )
                    ORDER BY `id` ASC

                    MySQL,
                ['posts', 'posts'],
                $pid,
            );
            $num = 1;
            while ($f = $this->database->arow($result)) {
                if ($f['id'] === $pid) {
                    $pid = $f['id'];
                    $couldntfindit = false;

                    break;
                }

                ++$num;
            }
        }

        $this->page->command('softurl');
        if ($couldntfindit) {
            $this->page->command('alert', "that post doesn't exist");

            return;
        }

        $this->page->location(
            '?act=vt' . $this->tid . '&page='
            . ceil($num / $this->numperpage) . '&pid=' . $pid . '#pid_' . $pid,
        );
    }

    private function markread($tid): void
    {
        $topicsread = $this->jax->parsereadmarkers($this->session->get('topicsread'));
        $topicsread[$tid] = time();
        $this->session->set('topicsread', json_encode($topicsread));
    }

    private function listrating(array|string $pid): void
    {
        $ratingConfig = $this->config->getSetting('ratings') ?? 0;
        if (($ratingConfig & 2) !== 0) {
            return;
        }

        $this->page->command('softurl');
        $result = $this->database->safeselect(
            ['rating'],
            'posts',
            'WHERE `id`=?',
            $this->database->basicvalue($pid),
        );
        $row = $this->database->arow($result);
        $this->database->disposeresult($result);
        $ratings = $row ? json_decode((string) $row['rating'], true) : [];

        if (empty($ratings)) {
            return;
        }

        $members = [];
        foreach ($ratings as $v) {
            $members = array_merge($members, $v);
        }

        if ($members === []) {
            $this->page->command('alert', 'This post has no ratings yet!');

            return;
        }

        $result = $this->database->safeselect(
            [
                'id',
                'display_name',
                'group_id',
            ],
            'members',
            'WHERE `id` IN ?',
            $members,
        );
        $mdata = [$result];
        while ($member = $this->database->arow($result)) {
            $mdata[$member['id']] = [$member['display_name'], $member['group_id']];
        }

        unset($members);
        $niblets = $this->database->getRatingNiblets();
        $page = '';
        foreach ($ratings as $index => $rating) {
            $page .= '<div class="column">';
            $page .= '<img src="' . $niblets[$index]['img'] . '" /> '
                . $niblets[$index]['title'] . '<ul>';
            foreach ($rating as $mid) {
                $page .= '<li>' . $this->template->meta(
                    'user-link',
                    $mid,
                    $mdata[$mid][1],
                    $mdata[$mid][0],
                ) . '</li>';
            }

            $page .= '</ul></div>';
        }

        $this->page->command('listrating', $pid, $page);
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    private function viewrss(int $tid): void
    {
        $boardURL = $this->domainDefinitions->getBoardURL();
        $feed = new RSSFeed(
            [
                'description' => $this->topicdata['subtitle'],
                'link' => "{$boardURL}?act=vt{$tid}",
                'title' => $this->topicdata['topic_title'],
            ],
        );
        $result = $this->database->safespecial(
            <<<'MySQL'
                SELECT p.`id` AS `id`
                    , p.`post` AS `post`
                    , UNIX_TIMESTAMP(p.`date`) AS `date`
                    , m.`display_name` AS `display_name`
                FROM %t p
                LEFT JOIN %t m
                    ON p.`auth_id` = m.`id`
                    WHERE p.`tid` = ?

                MySQL,
            ['posts', 'members'],
            $this->database->basicvalue($tid),
        );
        while ($post = $this->database->arow($result)) {
            $feed->additem(
                [
                    'description' => $this->textFormatting->blockhtml($this->textFormatting->theworks($post['post'])),
                    'guid' => $post['id'],
                    'link' => "{$boardURL}?act=vt{$tid}&amp;findpost={$post['id']}",
                    'pubDate' => gmdate('r', $post['date']),
                    'title' => $post['display_name'] . ':',
                ],
            );
        }

        $feed->publish();
    }
}
