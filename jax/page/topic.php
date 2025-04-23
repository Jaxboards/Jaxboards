<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Database;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Page;
use Jax\RSSFeed;
use Jax\Session;

use function array_diff;
use function array_flip;
use function array_key_exists;
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
    /**
     * @var int
     */
    private $tid = 0;

    /**
     * @var int
     */
    private $pageNumber = 0;

    /**
     * @var int
     */
    private $numperpage = 0;

    /**
     * @var bool
     */
    private $canMod = false;

    /**
     * @var int
     */
    private $firstPostID = 0;

    /**
     * @var null|array
     */
    private $topicdata;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly IPAddress $ipAddress,
        private readonly Page $page,
        private readonly Session $session,
    ) {
        $this->page->loadmeta('topic');
    }

    public function route(): void
    {
        preg_match('@\d+$@', (string) $this->jax->b['act'], $act);
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

        $this->pageNumber = isset($this->jax->b['page'])
            ? (int) $this->jax->b['page']
            : 0;
        if ($this->pageNumber <= 0 || !is_numeric($this->pageNumber)) {
            $this->pageNumber = 1;
        }

        --$this->pageNumber;

        $this->numperpage = 10;

        if (
            isset($this->jax->b['qreply'])
            && $this->jax->b['qreply']
            && !$this->page->jsupdate
        ) {
            if ($this->page->jsaccess && !$this->page->jsdirectlink) {
                $this->qreplyform($this->tid);

                return;
            }

            $this->page->location('?act=post&tid=' . $this->tid);

            return;
        }

        if (isset($this->jax->b['ratepost']) && $this->jax->b['ratepost']) {
            $this->ratepost($this->jax->b['ratepost'], $this->jax->b['niblet']);

            return;
        }

        if (isset($this->jax->b['votepoll']) && $this->jax->b['votepoll']) {
            $this->votepoll();

            return;
        }

        if (isset($this->jax->b['findpost']) && $this->jax->b['findpost']) {
            $this->findpost($this->jax->b['findpost']);

            return;
        }

        if (isset($this->jax->b['getlast']) && $this->jax->b['getlast']) {
            $this->getlastpost($this->tid);

            return;
        }

        if (isset($this->jax->b['edit']) && $this->jax->b['edit']) {
            $this->qeditpost($this->jax->b['edit']);

            return;
        }

        if (isset($this->jax->b['quote']) && $this->jax->b['quote']) {
            $this->multiquote($this->tid);

            return;
        }

        if (isset($this->jax->b['markread']) && $this->jax->b['markread']) {
            $this->markread($this->tid);

            return;
        }

        if (
            isset($this->jax->b['listrating'])
            && $this->jax->b['listrating']
        ) {
            $this->listrating($this->jax->b['listrating']);

            return;
        }

        if ($this->page->jsupdate) {
            $this->update($this->tid);

            return;
        }

        if (isset($this->jax->b['fmt']) && $this->jax->b['fmt'] === 'RSS') {
            $this->viewrss($this->tid);

            return;
        }

        $this->viewtopic($this->tid);
    }

    public function getTopicData($tid): void
    {
        global $USER;
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

        $this->topicdata['topic_title'] = $this->jax->wordfilter($this->topicdata['topic_title']);
        $this->topicdata['subtitle'] = $this->jax->wordfilter($this->topicdata['subtitle']);
        $this->topicdata['fperms'] = $this->jax->parseperms(
            $this->topicdata['fperms'],
            $USER ? $USER['group_id'] : 3,
        );
    }

    public function viewtopic($tid): void
    {
        global $USER,$PERMS;

        if ($USER && $this->topicdata['lp_date'] > $USER['last_visit']) {
            $this->markread($tid);
        }

        $this->page->append('TITLE', ' -> ' . $this->topicdata['topic_title']);
        $this->session->location_verbose = "In topic '" . $this->topicdata['topic_title'] . "'";

        // Fix this to work with subforums.
        $this->page->path(
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
        foreach ($this->jax->pages($totalpages, $this->pageNumber + 1, 10) as $x) {
            $pagelist .= $this->page->meta(
                'topic-pages-part',
                $tid,
                $x,
                $x === $this->pageNumber + 1 ? ' class="active"' : '',
                $x,
            );
        }

        // Are they on the last page? This stores a session variable.
        $this->session->addvar('topic_lastpage', $this->pageNumber + 1 === $totalpages);

        // If it's a poll, put it in.
        $poll = $this->topicdata['poll_type'] ? $this->page->meta(
            'box',
            " id='poll'",
            $this->topicdata['poll_q'],
            $this->generatepoll(
                $this->topicdata['poll_q'],
                $this->topicdata['poll_type'],
                json_decode((string) $this->topicdata['poll_choices']),
                $this->topicdata['poll_results'],
            ),
        ) : '';

        // Generate post listing.
        $page = $this->page->meta('topic-table', $this->postsintooutput());
        $page = $this->page->meta(
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
            . $this->page->meta(
                $this->page->metaexists('button-newtopic')
                    ? 'button-newtopic'
                    : 'topic-button-newtopic',
            )
            . '</a>';
        }

        if (
            $this->topicdata['fperms']['reply']
            && (
                !$this->topicdata['locked']
                || $PERMS['can_override_locked_topics']
            )
        ) {
            $buttons[1] = "<a href='?act=vt{$tid}&qreply=1'>" . $this->page->meta(
                $this->page->metaexists('button-qreply')
                ? 'button-qreply'
                : 'topic-button-qreply',
            ) . '</a>';
        }

        if (
            $this->topicdata['fperms']['reply']
            && (
                !$this->topicdata['locked']
                || $PERMS['can_override_locked_topics']
            )
        ) {
            $buttons[2] = "<a href='?act=post&tid={$tid}'>" . $this->page->meta(
                $this->page->metaexists('button-reply')
                ? 'button-reply'
                : 'topic-button-reply',
            ) . '</a>';
        }


        // Make the users online list.
        $usersonline = '';
        foreach ($this->database->getUsersOnline() as $user) {
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

            $usersonline .= $this->page->meta(
                'user-link',
                $user['uid'],
                $user['group_id'] . ($user['status'] === 'idle' ? ' idle' : ''),
                $user['name'],
            );
        }

        $page .= $this->page->meta('topic-users-online', $usersonline);

        // Add in other page elements.
        $page = $poll . $this->page->meta(
            'topic-pages-top',
            $pagelist,
        ) . $this->page->meta(
            'topic-buttons-top',
            $buttons,
        ) . $page . $this->page->meta(
            'topic-pages-bottom',
            $pagelist,
        ) . $this->page->meta(
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

        if ($this->page->jsaccess) {
            $this->page->JS('update', 'page', $page);
            $this->page->updatepath();
            if (isset($this->jax->b['pid']) && $this->jax->b['pid']) {
                $this->page->JS('scrollToPost', $this->jax->b['pid']);

                return;
            }

            if (isset($this->jax->b['page']) && $this->jax->b['page']) {
                $this->page->JS('scrollToPost', $this->firstPostID, 1);

                return;
            }

            return;
        }

        $this->page->append('page', $page);
    }

    public function update($tid): void
    {

        // Check for new posts and append them.
        if ($this->session->location !== "vt{$tid}") {
            $this->session->delvar('topic_lastpid');
        }

        if (
            isset($this->session->vars['topic_lastpid'], $this->session->vars['topic_lastpage'])
            && is_numeric($this->session->vars['topic_lastpid'])
            && $this->session->vars['topic_lastpage']
        ) {
            $newposts = $this->postsintooutput($this->session->vars['topic_lastpid']);
            if ($newposts !== '' && $newposts !== '0') {
                $this->page->JS('appendrows', '#intopic', $newposts);
            }
        }

        // Update users online list.
        $list = [];
        $oldcache = array_flip(explode(',', (string) $this->session->users_online_cache));
        $newcache = [];
        foreach ($this->database->getUsersOnline() as $f) {
            if (!$f['uid']) {
                continue;
            }

            if ($f['location'] !== "vt{$tid}") {
                continue;
            }

            $newcache[] = $f['uid'];

            if (!isset($oldcache[$f['uid']])) {
                $list[] = [
                    $f['uid'],
                    $f['group_id'],
                    $f['status'] !== 'active' ? $f['status'] : '',
                    $f['name'],
                ];

                continue;
            }

            unset($oldcache[$f['uid']]);
        }

        if ($list !== []) {
            $this->page->JS('onlinelist', $list);
        }

        $oldcache = implode(',', array_flip($oldcache));
        $newcache = implode(',', $newcache);
        if ($oldcache !== '' && $oldcache !== '0') {
            $this->page->JS('setoffline', $oldcache);
        }

        $this->session->users_online_cache = $newcache;
    }

    public function qreplyform($tid): void
    {
        $prefilled = '';
        $this->page->JS('softurl');
        if (
            isset($this->session->vars['multiquote'])
            && $this->session->vars['multiquote']
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
                explode(',', (string) $this->session->vars['multiquote']),
            );

            while ($post = $this->database->arow($result)) {
                $prefilled .= '[quote=' . $post['name'] . ']' . $post['post'] . '[/quote]' . PHP_EOL;
            }

            $this->session->delvar('multiquote');
        }

        $result = $this->database->safeselect(
            'title',
            'topics',
            'WHERE `id`=?',
            $tid,
        );
        $tdata = $this->database->arow($result);
        $this->database->disposeresult($result);

        $this->page->JS(
            'window',
            [
                'content' => $this->page->meta(
                    'topic-reply-form',
                    $tid,
                    $this->jax->blockhtml($prefilled),
                ),
                'id' => 'qreply',
                'resize' => 'textarea',
                'title' => $this->jax->wordfilter($tdata['title']),
            ],
        );
        $this->page->JS('updateqreply', '');
    }

    public function postsintooutput($lastpid = 0): string
    {
        global $USER,$PERMS;
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
            if (!$this->firstPostID) {
                $this->firstPostID = $post['pid'];
            }

            $postt = $post['post'];

            $postt = $this->jax->theworks($postt);

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
                    foreach ($rniblets as $k => $v) {
                        $postratingbuttons .= '<a href="?act=vt' . $this->tid . '&amp;ratepost='
                            . $post['pid'] . '&amp;niblet=' . $k . '">'
                            . $this->page->meta(
                                'rating-niblet',
                                $v['img'],
                                $v['title'],
                            ) . '</a>';
                        if (!isset($prating[$k])) {
                            continue;
                        }

                        if (!$prating[$k]) {
                            continue;
                        }

                        $num = 'x' . count($prating[$k]);
                        $postratingbuttons .= $num;
                        $showrating .= $this->page->meta(
                            'rating-niblet',
                            $v['img'],
                            $v['title'],
                        ) . $num;
                    }

                    $postrating = $this->page->meta(
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
            . "' class='edit'>" . $this->page->meta('topic-edit-button')
            . '</a>'
                : '')
            // Adds the Quote button
            . ($this->topicdata['fperms']['reply']
                ? " <a href='?act=vt" . $this->tid . '&amp;quote=' . $post['pid']
            . "' onclick='RUN.handleQuoting(this);return false;' "
            . "class='quotepost'>" . $this->page->meta('topic-quote-button') . '</a> '
                : '')
            // Adds the Moderate options
            . ($this->canModerate()
                ? "<a href='?act=modcontrols&amp;do=modp&amp;pid=" . $post['pid']
            . "' class='modpost' onclick='RUN.modcontrols.togbutton(this)'>"
            . $this->page->meta('topic-mod-button') . '</a>'
                : '');

            $rows .= $this->page->meta(
                'topic-post-row',
                $post['pid'],
                $this->tid,
                $post['auth_id'] ? $this->page->meta(
                    'user-link',
                    $post['auth_id'],
                    $post['group_id'],
                    $post['display_name'],
                ) : 'Guest',
                $this->jax->pick($post['avatar'], $this->page->meta('default-avatar')),
                $post['usertitle'],
                $post['posts'],
                $this->page->meta(
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
                . '" onclick="prompt(\'Link to this post:\',this.href)">'
                . $this->page->meta('topic-perma-button') . '</a>',
                $postt,
                isset($post['sig']) && $post['sig']
                    ? $this->jax->theworks($post['sig'])
                    : '',
                $post['auth_id'],
                $post['edit_date'] ? $this->page->meta(
                    'topic-edit-by',
                    $this->page->meta(
                        'user-link',
                        $post['editby'],
                        $post['egroup_id'],
                        $post['ename'],
                    ),
                    $this->jax->date($post['edit_date']),
                ) : '',
                $PERMS['can_moderate']
                    ? '<a href="?act=modcontrols&amp;do=iptools&amp;ip='
                . $this->ipAddress->asHumanReadable($post['ip']) . '">' . $this->page->meta(
                    'topic-mod-ipbutton',
                    $this->ipAddress->asHumanReadable($post['ip']),
                ) . '</a>'
                    : '',
                $post['icon'] ? $this->page->meta(
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

        $this->session->addvar('topic_lastpid', $lastpid);

        return $rows;
    }

    public function canedit($post): bool
    {
        global $PERMS,$USER;
        if ($this->canModerate()) {
            return true;
        }

        return $post['auth_id']
        && ($post['newtopic']
            ? $PERMS['can_edit_topics']
            : $PERMS['can_edit_posts'])
        && $post['auth_id'] === $USER['id'];
    }

    public function canModerate()
    {
        global $PERMS,$USER;
        if ($this->canMod) {
            return $this->canMod;
        }

        $canMod = false;
        if ($PERMS['can_moderate']) {
            $canMod = true;
        }

        if ($USER && $USER['mod']) {
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
            if (in_array($USER['id'], explode(',', (string) $mods['mods']), true)) {
                $canMod = true;
            }
        }

        return $this->canMod = $canMod;
    }

    public function generatepoll($q, $type, $choices, $results): string
    {
        global $USER;

        if (!$choices) {
            $choices = [];
        }

        $page = '';
        $usersvoted = [];
        $voted = false;

        if ($USER) {
            // Accomplish three things at once:
            // * Determine if the user has voted.
            // * Count up the number of votes.
            // * Parse the result set.
            $presults = [];

            $totalvotes = 0;
            $numvotes = [];
            foreach (explode(';', (string) $results) as $k => $v) {
                $presults[$k] = $v !== '' && $v !== '0' ? explode(',', $v) : [];
                $totalvotes += ($numvotes[$k] = count($presults[$k]));
                if (in_array($USER['id'], $presults[$k], true)) {
                    $voted = true;
                }

                foreach ($presults[$k] as $user) {
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

    public function votepoll()
    {
        global $USER;
        $e = '';
        if (!$USER) {
            $e = 'You must be logged in to vote!';
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

            $choice = $this->jax->b['choice'];
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
                    if ($v2 === $USER['id']) {
                        $voted = true;

                        break;
                    }
                }
            }

            if ($voted) {
                $e = 'You have already voted on this poll!';
            }

            if ($row['poll_type'] === 'multi') {
                if (is_array($choice)) {
                    foreach ($choice as $c) {
                        if (is_numeric($c) && $c < $numchoices && $c >= 0) {
                            continue;
                        }

                        $e = 'Invalid choices';
                    }
                } else {
                    $e = 'Invalid Choice';
                }
            } elseif (
                !is_numeric($choice)
                || $choice >= $numchoices
                || $choice < 0
            ) {
                $e = 'Invalid choice';
            }
        }

        if ($e !== '' && $e !== '0') {
            return $this->page->JS('error', $e);
        }

        if ($row['poll_type'] === 'multi') {
            foreach ($choice as $c) {
                $results[$c][] = $USER['id'];
            }
        } else {
            $results[$choice][] = $USER['id'];
        }

        $presults = [];
        for ($x = 0; $x < $numchoices; ++$x) {
            $presults[$x] = isset($results[$x]) && $results[$x]
                ? implode(',', $results[$x]) : '';
        }

        $presults = implode(';', $presults);
        $this->page->JS(
            'update',
            '#poll .content',
            $this->generatePoll(
                $row['poll_q'],
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

        return null;
    }

    public function ratepost($postid, $nibletid): void
    {
        global $USER,$PAGE;
        $this->page->JS('softurl');
        if (!is_numeric($postid) || !is_numeric($nibletid)) {
            return;
        }

        $result = $this->database->safeselect(
            ['rating'],
            'posts',
            'WHERE `id`=?',
            $this->database->basicvalue($postid),
        );
        $f = $this->database->arow($result);
        $this->database->disposeresult($result);

        $niblets = $this->database->getRatingNiblets();
        $e = null;
        $ratings = [];
        if (!$USER['id']) {
            $e = "You don't have permission to rate posts.";
        } elseif (!$f) {
            $e = "That post doesn't exist.";
        } elseif (!$niblets[$nibletid]) {
            $e = 'Invalid rating';
        } else {
            $ratings = json_decode((string) $f['rating'], true);
            if (!$ratings) {
                $ratings = [];
            }
        }

        if ($e) {
            $this->page->JS('error', $e);

            return;
        }

        if (!array_key_exists((int) $nibletid, $ratings)) {
            $ratings[(int) $nibletid] = [];
        }

        $unrate = in_array((int) $USER['id'], $ratings[(int) $nibletid], true);
        // Unrate
        if ($unrate) {
            $ratings[(int) $nibletid] = array_diff($ratings[(int) $nibletid], [(int) $USER['id']]);
        } else {
            // Rate
            $ratings[(int) $nibletid][] = (int) $USER['id'];
        }

        $this->database->safeupdate(
            'posts',
            [
                'rating' => json_encode($ratings),
            ],
            'WHERE `id`=?',
            $this->database->basicvalue($postid),
        );
        $this->page->JS('alert', $unrate ? 'Unrated!' : 'Rated!');
    }

    public function qeditpost($pid): void
    {
        global $USER,$PERMS;
        if (!is_numeric($pid)) {
            return;
        }

        if (!$this->page->jsaccess) {
            $this->page->location('?act=post&pid=' . $pid);
        }

        $this->page->JS('softurl');
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

        if (!$this->page->jsnewlocation) {
            return;
        }

        if (!$post) {
            $this->page->JS('alert', 'Post not found!');

            return;
        }

        if (!$this->canedit($post)) {
            $this->page->JS('alert', "You don't have permission to edit this post.");

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

            $form = $this->page->meta(
                'topic-qedit-topic',
                $hiddenfields,
                $topic['title'],
                $topic['subtitle'],
                $this->jax->blockhtml($post['post']),
            );
        } else {
            $form = $this->page->meta(
                'topic-qedit-post',
                $hiddenfields,
                $this->jax->blockhtml($post['post']),
                $pid,
            );
        }

        $this->page->JS('update', "#pid_{$pid} .post_content", $form);
    }

    public function multiquote($tid): void
    {
        $pid = $this->jax->b['quote'];
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
            $e = "That post doesn't exist!";
            $this->page->JS('alert', $e);
            $this->page->append('PAGE', $this->page->meta('error', $e));

            return;
        }

        if ($this->jax->b['qreply']) {
            $this->page->JS(
                'updateqreply',
                '[quote=' . $post['name'] . ']' . $post['post'] . '[/quote]'
                . PHP_EOL . PHP_EOL,
            );
        } else {
            $multiquote = (string) ($this->session->vars['multiquote'] ?? '');
            $multiquotes = $multiquote !== '' ? explode(',', $multiquote) : [];
            if (!in_array((string) $pid, $multiquotes, true)) {
                $multiquotes[] = $pid;
                $this->session->addvar(
                    'multiquote',
                    implode(',', $multiquotes),
                );
            }

            // This line toggles whether or not the qreply window should open
            // on quote.
            if ($this->page->jsaccess) {
                $this->qreplyform($tid);
            } else {
                header('Location:?act=post&tid=' . $tid);
            }
        }

        $this->page->JS('softurl');
    }

    public function getlastpost($tid): void
    {
        $result = $this->database->safeselect(
            'MAX(`id`) AS `lastpid`,COUNT(`id`) AS `numposts`',
            'posts',
            'WHERE `tid`=?',
            $tid,
        );
        $f = $this->database->arow($result);
        $this->database->disposeresult($result);

        $this->page->JS('softurl');
        $this->page->location(
            "?act=vt{$tid}&page=" . ceil($f['numposts'] / $this->numperpage)
            . '&pid=' . $f['lastpid'] . '#pid_' . $f['lastpid'],
        );
    }

    public function findpost($pid): void
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

        $this->page->JS('softurl');
        if ($couldntfindit) {
            $this->page->JS('alert', "that post doesn't exist");

            return;
        }

        $this->page->location(
            '?act=vt' . $this->tid . '&page='
            . ceil($num / $this->numperpage) . '&pid=' . $pid . '#pid_' . $pid,
        );
    }

    public function markread($tid): void
    {
        $topicsread = $this->jax->parsereadmarkers($this->session->topicsread);
        $topicsread[$tid] = time();
        $this->session->topicsread = json_encode($topicsread);
    }

    public function listrating($pid): void
    {
        $ratingConfig = $this->config->getSetting('ratings') ?? 0;
        if (($ratingConfig & 2) !== 0) {
            return;
        }

        $this->page->JS('softurl');
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
            $this->page->JS('alert', 'This post has no ratings yet!');

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
        while ($f = $this->database->arow($result)) {
            $mdata[$f['id']] = [$f['display_name'], $f['group_id']];
        }

        unset($members);
        $niblets = $this->database->getRatingNiblets();
        $page = '';
        foreach ($ratings as $k => $v) {
            $page .= '<div class="column">';
            $page .= '<img src="' . $niblets[$k]['img'] . '" /> '
                . $niblets[$k]['title'] . '<ul>';
            foreach ($v as $mid) {
                $page .= '<li>' . $this->page->meta(
                    'user-link',
                    $mid,
                    $mdata[$mid][1],
                    $mdata[$mid][0],
                ) . '</li>';
            }

            $page .= '</ul></div>';
        }

        $this->page->JS('listrating', $pid, $page);
    }

    private function viewrss(int $tid): void
    {

        $link = 'https://' . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'];
        $feed = new RSSFeed(
            [
                'description' => $this->topicdata['subtitle'],
                'link' => $link . '?act=vt' . $tid,
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
                    'description' => $this->jax->blockhtml($this->jax->theworks($post['post'])),
                    'guid' => $post['id'],
                    'link' => "{$link}?act=vt{$tid}&amp;findpost={$post['id']}",
                    'pubDate' => gmdate('r', $post['date']),
                    'title' => $post['display_name'] . ':',
                ],
            );
        }

        $feed->publish();
    }
}
