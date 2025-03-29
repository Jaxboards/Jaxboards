<?php

$PAGE->loadmeta('topic');

$IDX = new TOPIC();
final class TOPIC
{
    public $id = 0;

    /**
     * @var int
     */
    public $page = '';

    /**
     * @var int
     */
    public $numperpage = 0;

    public $canmod = false;

    public $firstPostID = 0;

    public $lastPostID;

    public $topicdata;

    public function __construct()
    {
        global $JAX,$PAGE;

        preg_match('@\d+$@', (string) $JAX->b['act'], $act);
        $this->id = $act[0] ?: 0;
        $id = $act[0] ?: 0;
        if (!$id) {
            $PAGE->location('?');

            return;
        }

        $this->getTopicData($id);
        if (!$this->topicdata) {
            $PAGE->location('?');

            return;
        }

        $this->page = isset($JAX->b['page']) ? (int) $JAX->b['page'] : 0;
        if ($this->page <= 0 || !is_numeric($this->page)) {
            $this->page = 1;
        }

        --$this->page;

        $this->numperpage = 10;
        if (
            isset($JAX->b['qreply'])
            && $JAX->b['qreply']
            && !$PAGE->jsupdate
        ) {
            if ($PAGE->jsaccess && !$PAGE->jsdirectlink) {
                $this->qreplyform($id);
            } else {
                $PAGE->location('?act=post&tid=' . $id);
            }
        } elseif (isset($JAX->b['ratepost']) && $JAX->b['ratepost']) {
            $this->ratepost($JAX->b['ratepost'], $JAX->b['niblet']);
        } elseif (isset($JAX->b['votepoll']) && $JAX->b['votepoll']) {
            $this->votepoll($id);
        } elseif (isset($JAX->b['findpost']) && $JAX->b['findpost']) {
            $this->findpost($JAX->b['findpost']);
        } elseif (isset($JAX->b['getlast']) && $JAX->b['getlast']) {
            $this->getlastpost($id);
        } elseif (isset($JAX->b['edit']) && $JAX->b['edit']) {
            $this->qeditpost($JAX->b['edit']);
        } elseif (isset($JAX->b['quote']) && $JAX->b['quote']) {
            $this->multiquote($id);
        } elseif (isset($JAX->b['markread']) && $JAX->b['markread']) {
            $this->markread($id);
        } elseif (
            isset($JAX->b['listrating'])
            && $JAX->b['listrating']
        ) {
            $this->listrating($JAX->b['listrating']);
        } elseif ($PAGE->jsupdate) {
            $this->update($id);
        } else {
            $this->viewtopic($id);
        }
    }

    public function getTopicData($id): void
    {
        global $DB,$JAX,$USER;
        $result = $DB->safespecial(
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
                LEFT JOIN %t b
                		ON a.`fid` = b.`id`
                LEFT JOIN %t AS c
                		ON b.`cat_id` = c.`id`
                WHERE a.`id` = ?
                LIMIT 1

                MySQL,
            ['topics', 'forums', 'categories'],
            $id,
        );
        $this->topicdata = $DB->arow($result);
        $DB->disposeresult($result);

        $this->topicdata['topic_title'] = $JAX->wordfilter($this->topicdata['topic_title']);
        $this->topicdata['subtitle'] = $JAX->wordfilter($this->topicdata['subtitle']);
        $this->topicdata['fperms'] = $JAX->parseperms(
            $this->topicdata['fperms'],
            $USER ? $USER['group_id'] : 3,
        );
    }

    public function viewtopic($id)
    {
        global $DB,$PAGE,$JAX,$SESS,$USER,$PERMS;
        $page = $this->page;

        if ($USER && $this->topicdata['lp_date'] > $USER['last_visit']) {
            $this->markread($id);
        }

        if (!$this->topicdata['fperms']['read']) {
            // No business being here.
            return $PAGE->location('?');
        }

        $PAGE->append('TITLE', ' -> ' . $this->topicdata['topic_title']);
        $SESS->location_verbose = "In topic '" . $this->topicdata['topic_title'] . "'";

        // Output RSS instead.
        if (isset($JAX->b['fmt']) && $JAX->b['fmt'] === 'RSS') {
            include_once __DIR__ . '/inc/classes/rssfeed.php';
            $link = 'https://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
            $feed = new rssfeed(
                [
                    'description' => $this->topicdata['subtitle'],
                    'link' => $link . '?act=vt' . $id,
                    'title' => $this->topicdata['topic_title'],
                ],
            );
            $result = $DB->safespecial(
                <<<'MySQL'
                    SELECT p.`id` AS `id`
                        , p.`post` AS `post`
                        , UNIX_TIMESTAMP(p.`date`) AS `date`
                        , m.`id` AS `id`
                        , m.`display_name` AS `display_name`
                    FROM %t p
                    LEFT JOIN %t m
                        ON p.`auth_id` = m.`id`
                        WHERE p.`tid` = ?

                    MySQL,
                ['posts', 'members'],
                $DB->basicvalue($id),
            );
            echo $DB->error(1);
            while ($f = $DB->arow($result)) {
                $feed->additem(
                    [
                        'description' => $JAX->blockhtml($JAX->theworks($f['post'])),
                        'guid' => $f['id'],
                        'link' => $link . '?act=vt' . $id . '&amp;findpost=' . $f['id'],
                        'pubDate' => date('r', $f['date']),
                        'title' => $f['display_name'] . ':',
                    ],
                );
            }

            $feed->publish();

            exit;
        }

        // Fix this to work with subforums.
        $PAGE->path(
            [
                $this->topicdata['cat_title'] => '?act=vc' . $this->topicdata['cat_id'],
                $this->topicdata['forum_title'] => '?act=vf' . $this->topicdata['fid'],
                $this->topicdata['topic_title'] => "?act=vt{$id}",
            ],
        );

        // Generate pages.
        $result = $DB->safeselect(
            'COUNT(`id`)',
            'posts',
            'WHERE `tid`=?',
            $id,
        );
        $thisrow = $DB->arow($result);
        $posts = array_pop($thisrow);
        $DB->disposeresult($result);

        $totalpages = ceil($posts / $this->numperpage);
        $pagelist = '';
        foreach ($JAX->pages($totalpages, $this->page + 1, 10) as $x) {
            $pagelist .= $PAGE->meta(
                'topic-pages-part',
                $id,
                $x,
                $x === $this->page + 1 ? ' class="active"' : '',
                $x,
            );
        }

        // Are they on the last page? This stores a session variable.
        $SESS->addvar('topic_lastpage', $page + 1 === $totalpages);

        // If it's a poll, put it in.
        $poll = $this->topicdata['poll_type'] ? $PAGE->meta(
            'box',
            " id='poll'",
            $this->topicdata['poll_q'],
            $this->generatepoll(
                $this->topicdata['poll_q'],
                $this->topicdata['poll_type'],
                $JAX->json_decode(
                    $this->topicdata['poll_choices'],
                ),
                $this->topicdata['poll_results'],
            ),
        ) : '';

        // Generate post listing.
        $page = $PAGE->meta('topic-table', $this->postsintooutput());
        $page = $PAGE->meta(
            'topic-wrapper',
            $this->topicdata['topic_title']
            . ($this->topicdata['subtitle'] ? ', ' . $this->topicdata['subtitle'] : ''),
            $page,
            '<a href="./?act=vt' . $id . '&amp;fmt=RSS" class="social rss" title="RSS Feed for this Topic">RSS</a>',
        );

        // Add buttons.
        $buttons = [
            $this->topicdata['fperms']['start']
                ? "<a href='?act=post&fid=" . $this->topicdata['fid'] . "'>"
            . $PAGE->meta(
                $PAGE->metaexists('button-newtopic')
                    ? 'button-newtopic'
                    : 'topic-button-newtopic',
            ) . '</a>'
                : '&nbsp;',
            $this->topicdata['fperms']['reply']
            && (!$this->topicdata['locked']
            || $PERMS['can_override_locked_topics'])
                ? "<a href='?act=vt{$id}&qreply=1'>"
            . $PAGE->meta(
                $PAGE->metaexists('button-qreply')
                    ? 'button-qreply'
                    : 'topic-button-qreply',
            )
                : '',
            $this->topicdata['fperms']['reply']
            && (!$this->topicdata['locked']
            || $PERMS['can_override_locked_topics'])
                ? "<a href='?act=post&tid={$id}'>"
            . $PAGE->meta(
                $PAGE->metaexists('button-reply')
                    ? 'button-reply'
                    : 'topic-button-reply',
            ) . '</a>'
                : '',
        ];

        // Make the users online list.
        $usersonline = '';
        foreach ($DB->getUsersOnline() as $f) {
            if (empty($f['uid']) || $f['location'] !== "vt{$id}") {
                continue;
            }

            $usersonline .= isset($f['is_bot']) && $f['is_bot']
                ? '<a class="user' . $f['uid'] . '">' . $f['name'] . '</a>'
                : $PAGE->meta(
                    'user-link',
                    $f['uid'],
                    $f['group_id'] . ($f['status'] === 'idle' ? ' idle' : ''),
                    $f['name'],
                );
        }

        $page .= $PAGE->meta('topic-users-online', $usersonline);

        // Add in other page elements.
        $page = $poll . $PAGE->meta(
            'topic-pages-top',
            $pagelist,
        ) . $PAGE->meta(
            'topic-buttons-top',
            $buttons,
        ) . $page . $PAGE->meta(
            'topic-pages-bottom',
            $pagelist,
        ) . $PAGE->meta(
            'topic-buttons-bottom',
            $buttons,
        );

        // Update view count.
        $DB->safespecial(
            <<<'MySQL'
                UPDATE %t
                SET `views` = `views` + 1
                WHERE `id` = ?

                MySQL,
            ['topics'],
            $id,
        );

        if ($PAGE->jsaccess) {
            $PAGE->JS('update', 'page', $page);
            $PAGE->updatepath();
            if (isset($JAX->b['pid']) && $JAX->b['pid']) {
                $PAGE->JS('scrollToPost', $JAX->b['pid']);
            } elseif (isset($JAX->b['page']) && $JAX->b['page']) {
                $PAGE->JS('scrollToPost', $this->firstPostID, 1);
            }
        } else {
            $PAGE->append('page', $page);
        }

        return null;
    }

    public function update($id): void
    {
        global $SESS,$PAGE,$DB,$JAX;

        // Check for new posts and append them.
        if ($SESS->location !== "vt{$id}") {
            $SESS->delvar('topic_lastpid');
        }

        if (
            isset($SESS->vars['topic_lastpid'], $SESS->vars['topic_lastpage'])
            && is_numeric($SESS->vars['topic_lastpid'])
            && $SESS->vars['topic_lastpage']
        ) {
            $newposts = $this->postsintooutput($SESS->vars['topic_lastpid']);
            if ($newposts) {
                $PAGE->JS('appendrows', '#intopic', $newposts);
            }
        }

        // Update users online list.
        $list = [];
        $oldcache = array_flip(explode(',', (string) $SESS->users_online_cache));
        $newcache = '';
        foreach ($DB->getUsersOnline() as $f) {
            if (!$f['uid'] || $f['location'] !== "vt{$id}") {
                continue;
            }

            if (!isset($oldcache[$f['uid']])) {
                $list[] = [
                    $f['uid'],
                    $f['group_id'],
                    $f['status'] !== 'active' ? $f['status'] : '',
                    $f['name'],
                ];
            } else {
                unset($oldcache[$f['uid']]);
            }

            $newcache .= $f['uid'] . ',';
        }

        if ($list !== []) {
            $PAGE->JS('onlinelist', $list);
        }

        $oldcache = implode(',', array_flip($oldcache));
        $newcache = mb_substr($newcache, 0, -1);
        if ($oldcache !== '' && $oldcache !== '0') {
            $PAGE->JS('setoffline', $oldcache);
        }

        $SESS->users_online_cache = $newcache;
    }

    public function qreplyform($id): void
    {
        global $PAGE,$SESS,$DB,$JAX;
        $prefilled = '';
        $PAGE->JS('softurl');
        if (isset($SESS->vars['multiquote']) && $SESS->vars['multiquote']) {
            $result = $DB->safespecial(
                <<<'MySQL'
                    SELECT p.`id` AS `id`
                        , p.`auth_id` AS `auth_id`
                        , p.`post` AS `post`
                        , UNIX_TIMESTAMP(p.`date`) AS `date`
                        , p.`showsig` AS `showsig`
                        , p.`showemotes` AS `showemotes`
                        , p.`tid` AS `tid`
                        , p.`newtopic` AS `newtopic`
                        , INET6_NTOA(p.`ip`) AS `ip`
                        , UNIX_TIMESTAMP(p.`edit_date`) AS `edit_date`
                        , p.`editby` AS `editby`
                        , p.`rating` AS `rating`
                        , m.`display_name` AS `name`
                    FROM %t p
                    LEFT JOIN %t m
                        ON p.`auth_id` = m.`id`
                        WHERE p.`id` IN ?

                    MySQL,
                ['posts', 'members'],
                explode(',', (string) $SESS->vars['multiquote']),
            );

            while ($f = $DB->arow($result)) {
                $prefilled .= '[quote=' . $f['name'] . ']' . $f['post'] . '[/quote]' . PHP_EOL;
            }

            $SESS->delvar('multiquote');
        }

        $result = $DB->safeselect(
            'title',
            'topics',
            'WHERE `id`=?',
            $id,
        );
        $tdata = $DB->arow($result);
        $DB->disposeresult($result);

        $PAGE->JS(
            'window',
            [
                'content' => $PAGE->meta(
                    'topic-reply-form',
                    $id,
                    $JAX->blockhtml($prefilled),
                ),
                'id' => 'qreply',
                'resize' => 'textarea',
                'title' => $JAX->wordfilter($tdata['title']),
            ],
        );
        $PAGE->JS('updateqreply', '');
    }

    public function postsintooutput($lastpid = 0): string
    {
        global $DB,$PAGE,$JAX,$SESS,$USER,$PERMS,$CFG;
        $usersonline = $DB->getUsersOnline();
        $postrating = '';
        $showrating = '';

        $topic_post_counter = 0;

        $query = $lastpid ? $DB->safespecial(
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
                    , CONCAT(MONTH(m.`birthdate`)
                    , ' '
                    , MONTH(m.`birthdate`)) as `birthday`
                    , m.`mod` AS `mod`
                    , m.`posts` AS `posts`
                    , p.`tid` AS `tid`
                    , p.`id` AS `pid`
                    , INET6_NTOA(p.`ip`) AS `ip`
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
            $this->id,
            $lastpid,
        ) : $DB->safespecial(
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
                    , INET6_NTOA(m.`ip`) AS `ip`
                    , m.`mod` AS `mod`
                    , m.`wysiwyg` AS `wysiwyg`
                    , p.`tid` AS `tid`
                    , p.`id` AS `pid`
                    , INET6_NTOA(p.`ip`) AS `ip`
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
            $this->id,
            $topic_post_counter = $this->page * $this->numperpage,
            $this->numperpage,
        );

        $rows = '';
        while ($post = $DB->arow($query)) {
            if (!$this->firstPostID) {
                $this->firstPostID = $post['pid'];
            }

            $postt = $post['post'];

            $postt = $JAX->theworks($postt);

            // Post rating content goes here.
            if (isset($CFG['ratings']) && $CFG['ratings'] & 1) {
                $postrating = '';
                $showrating = '';
                $prating = [];
                if ($post['rating']) {
                    $prating = json_decode((string) $post['rating'], true);
                }

                $rniblets = $DB->getRatingNiblets();
                if ($rniblets) {
                    foreach ($rniblets as $k => $v) {
                        $postrating .= '<a href="?act=topic&amp;ratepost='
                            . $post['pid'] . '&amp;niblet=' . $k . '">'
                            . $PAGE->meta(
                                'rating-niblet',
                                $v['img'],
                                $v['title'],
                            ) . '</a>';
                        if (!isset($prating[$k]) || !$prating[$k]) {
                            continue;
                        }

                        $num = 'x' . count($prating[$k]);
                        $postrating .= $num;
                        $showrating .= $PAGE->meta(
                            'rating-niblet',
                            $v['img'],
                            $v['title'],
                        ) . $num;
                    }

                    $postrating = $PAGE->meta(
                        'rating-wrapper',
                        $postrating,
                        ($CFG['ratings'] & 2) === 0
                            ? '<a href="?act=vt' . $this->id
                        . '&amp;listrating=' . $post['pid'] . '">(List)</a>'
                            : '',
                        $showrating,
                    );
                }
            }

            $rows .= $PAGE->meta(
                'topic-post-row',
                $post['pid'],
                $this->id,
                $post['auth_id'] ? $PAGE->meta(
                    'user-link',
                    $post['auth_id'],
                    $post['group_id'],
                    $post['display_name'],
                ) : 'Guest',
                $JAX->pick($post['avatar'], $PAGE->meta('default-avatar')),
                $post['usertitle'],
                $post['posts'],
                $PAGE->meta(
                    'topic-status-'
                    . (isset($usersonline[$post['auth_id']])
                    && $usersonline[$post['auth_id']] ? 'online' : 'offline'),
                ),
                $post['title'],
                $post['auth_id'],
                // Adds the Edit button
                ($this->canedit($post)
                    ? "<a href='?act=vt" . $this->id . '&amp;edit=' . $post['pid']
                . "' class='edit'>" . $PAGE->meta('topic-edit-button')
                . '</a>'
                    : '')
                // Adds the Quote button
                . ($this->topicdata['fperms']['reply']
                    ? " <a href='?act=vt" . $this->id . '&amp;quote=' . $post['pid']
                . "' onclick='RUN.handleQuoting(this);return false;' "
                . "class='quotepost'>" . $PAGE->meta('topic-quote-button') . '</a> '
                    : '')
                // Adds the Moderate options
                . ($this->canmoderate()
                    ? "<a href='?act=modcontrols&amp;do=modp&amp;pid=" . $post['pid']
                . "' class='modpost' onclick='RUN.modcontrols.togbutton(this)'>"
                . $PAGE->meta('topic-mod-button') . '</a>'
                    : ''),
                $JAX->date($post['date']),
                '<a href="?act=vt' . $this->id . '&amp;findpost=' . $post['pid']
                . '" onclick="prompt(\'Link to this post:\',this.href)">'
                . $PAGE->meta('topic-perma-button') . '</a>',
                $postt,
                isset($post['sig']) && $post['sig']
                    ? $JAX->theworks($post['sig'])
                    : '',
                $post['auth_id'],
                $post['edit_date'] ? $PAGE->meta(
                    'topic-edit-by',
                    $PAGE->meta(
                        'user-link',
                        $post['editby'],
                        $post['egroup_id'],
                        $post['ename'],
                    ),
                    $JAX->date($post['edit_date']),
                ) : '',
                $PERMS['can_moderate']
                    ? '<a href="?act=modcontrols&amp;do=iptools&amp;ip='
                . $post['ip'] . '">' . $PAGE->meta(
                    'topic-mod-ipbutton',
                    $post['ip'],
                ) . '</a>'
                    : '',
                $post['icon'] ? $PAGE->meta(
                    'topic-icon-wrapper',
                    $post['icon'],
                ) : '',
                ++$topic_post_counter,
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
                $postrating,
            );
            $lastpid = $post['pid'];
        }

        $this->lastPostID = $lastpid;
        $SESS->addvar('topic_lastpid', $lastpid);

        return $rows;
    }

    public function canedit($post): bool
    {
        global $PERMS,$USER;
        if ($this->canmoderate()) {
            return true;
        }

        return $post['auth_id']
        && ($post['newtopic']
            ? $PERMS['can_edit_topics']
            : $PERMS['can_edit_posts'])
        && $post['auth_id'] === $USER['id'];
    }

    public function canmoderate()
    {
        global $PAGE,$PERMS,$USER,$DB;
        if ($this->canmod) {
            return $this->canmod;
        }

        $canmod = false;
        if ($PERMS['can_moderate']) {
            $canmod = true;
        }

        if ($USER && $USER['mod']) {
            $result = $DB->safespecial(
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
                $DB->basicvalue($this->id),
            );
            $mods = $DB->arow($result);
            $DB->disposeresult($result);
            if (in_array($USER['id'], explode(',', (string) $mods['mods']))) {
                $canmod = true;
            }
        }

        return $this->canmod = $canmod;
    }

    public function generatepoll($q, $type, $choices, $results): string
    {
        if (!$choices) {
            $choices = [];
        }

        global $PAGE,$USER,$JAX;
        $page = '';
        if ($USER) {
            // Accomplish three things at once:
            // * Determine if the user has voted.
            // * Count up the number of votes.
            // * Parse the result set.
            $presults = [];
            $voted = false;
            $totalvotes = 0;
            $usersvoted = [];
            $numvotes = [];
            foreach (explode(';', (string) $results) as $k => $v) {
                $presults[$k] = $v !== '' && $v !== '0' ? explode(',', $v) : [];
                $totalvotes += ($numvotes[$k] = count($presults[$k]));
                if (in_array($USER['id'], $presults[$k])) {
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
            $page .= '</table>';
        } else {
            $page = "<form method='post' action='?' "
                . "data-ajax-form='true'>"
                . $JAX->hiddenFormFields(
                    [
                        'act' => 'vt' . $this->id,
                        'votepoll' => 1,
                    ],
                );
            if ($type === 'multi') {
                foreach ($choices as $k => $v) {
                    $page .= "<div class='choice'><input type='checkbox' "
                        . "name='choice[]' value='{$k}' id='poll_{$k}' /> "
                        . "<label for='poll_{$k}'>{$v}</label></div>";
                }
            } else {
                foreach ($choices as $k => $v) {
                    $page .= "<div class='choice'><input type='radio' "
                        . "name='choice' value='{$k}' id='poll_{$k}' /> "
                        . "<label for='poll_{$k}'>{$v}</label></div>";
                }
            }

            $page .= "<div class='buttons'><input type='submit' "
                . "value='Vote'></div></form>";
        }

        return $page;
    }

    public function votepoll($tid)
    {
        global $DB,$PAGE,$USER,$JAX;

        $e = '';

        if (!$USER) {
            $e = 'You must be logged in to vote!';
        } else {
            $result = $DB->safeselect(
                '`poll_q`,`poll_results`,`poll_choices`,`poll_type`',
                'topics',
                'WHERE `id`=?',
                $this->id,
            );
            $row = $DB->arow($result);
            $DB->disposeresult($result);

            $choice = $JAX->b['choice'];
            $choices = $JAX->json_decode($row['poll_choices']);
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
            } elseif (!is_numeric($choice) || $c >= $numchoices || $c < 0) {
                $e = 'Invalid choice';
            }
        }

        if ($e !== '' && $e !== '0') {
            return $PAGE->JS('error', $e);
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

        $PAGE->JS(
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

        $DB->safeupdate(
            'topics',
            [
                'poll_results' => $presults,
            ],
            'WHERE `id`=?',
            $this->id,
        );

        return null;
    }

    public function ratepost($postid, $nibletid): ?bool
    {
        global $DB,$USER,$PAGE;
        $PAGE->JS('softurl');
        if (!is_numeric($postid) || !is_numeric($nibletid)) {
            return false;
        }

        $result = $DB->safeselect(
            '`rating`',
            'posts',
            'WHERE `id`=?',
            $DB->basicvalue($postid),
        );
        $f = $DB->arow($result);
        $DB->disposeresult($result);

        $niblets = $DB->getRatingNiblets();
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
            } else {
                $found = false;
                foreach ($ratings as $k => $v) {
                    if (($pos = array_search($USER['id'], $v, true)) === false) {
                        continue;
                    }

                    unset($ratings[$k][$pos]);
                    if (!empty($ratings[$k])) {
                        continue;
                    }

                    unset($ratings[$k]);
                }
            }
        }

        if ($e !== '0') {
            $PAGE->JS('error', $e);
        } else {
            $ratings[(int) $nibletid][] = (int) $USER['id'];
            $DB->safeupdate(
                'posts',
                [
                    'rating' => json_encode($ratings),
                ],
                'WHERE `id`=?',
                $DB->basicvalue($postid),
            );
            $PAGE->JS('alert', 'Rated!');
        }

        return null;
    }

    public function qeditpost($id): void
    {
        global $DB,$JAX,$PAGE,$USER,$PERMS;
        if (!is_numeric($id)) {
            return;
        }

        if (!$PAGE->jsaccess) {
            $PAGE->location('?act=post&pid=' . $id);
        }

        $PAGE->JS('softurl');
        $result = $DB->safeselect(
            <<<'MySQL'
                `id`
                , `auth_id`
                , `post`
                , UNIX_TIMESTAMP(`date`)
                , `showsig`
                , `showemotes`
                , `tid`
                , `newtopic`
                , INET6_NTOA(`ip`) AS `ip`
                , UNIX_TIMESTAMP(`edit_date`) AS `edit_date`
                , `editby`
                , `rating`
                MySQL,
            'posts',
            'WHERE `id`=?',
            $id,
        );
        $post = $DB->arow($result);
        $DB->disposeresult($result);

        $hiddenfields = $JAX->hiddenFormFields(
            [
                'act' => 'post',
                'how' => 'qedit',
                'pid' => $id,
            ],
        );

        if (!$PAGE->jsnewlocation) {
            return;
        }

        if (!$post) {
            $PAGE->JS('alert', 'Post not found!');
        } elseif (!$this->canedit($post)) {
            $PAGE->JS('alert', "You don't have permission to edit this post.");
        } else {
            if ($post['newtopic']) {
                $hiddenfields .= $JAX->hiddenFormFields(
                    [
                        'tid' => $post['tid'],
                    ],
                );
                $result = $DB->safeselect(
                    <<<'MySQL'
                        `id`
                        , `title`
                        , `subtitle`
                        , `lp_uid`
                        , UNIX_TIMESTAMP(`lp_date`) AS `lp_date`
                        , `fid`
                        , `auth_id`
                        , `replies`
                        , `views`
                        , `pinned`
                        , `poll_choices`
                        , `poll_results`
                        , `poll_q`
                        , `poll_type`
                        , `summary`
                        , `locked`
                        , UNIX_TIMESTAMP(`date`) AS `date`
                        , `op`
                        , `cal_event`

                        MySQL,
                    'topics',
                    'WHERE `id`=?',
                    $post['tid'],
                );
                $topic = $DB->arow($result);
                $DB->disposeresult($result);

                $form = $PAGE->meta(
                    'topic-qedit-topic',
                    $hiddenfields,
                    $topic['title'],
                    $topic['subtitle'],
                    $JAX->blockhtml($post['post']),
                );
            } else {
                $form = $PAGE->meta(
                    'topic-qedit-post',
                    $hiddenfields,
                    $JAX->blockhtml($post['post']),
                    $id,
                );
            }

            $PAGE->JS('update', "#pid_{$id} .post_content", $form);
        }
    }

    public function multiquote($tid): void
    {
        global $PAGE,$JAX,$DB,$SESS;
        $pid = $JAX->b['quote'];
        $post = false;
        if ($pid && is_numeric($pid)) {
            $result = $DB->safespecial(
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
            $post = $DB->arow($result);
            $DB->disposeresult($result);
        }

        if (!$post) {
            $e = "That post doesn't exist!";
            $PAGE->JS('alert', $e);
            $PAGE->append('PAGE', $PAGE->meta('error', $e));

            return;
        }

        if ($JAX->b['qreply']) {
            $PAGE->JS(
                'updateqreply',
                '[quote=' . $post['name'] . ']' . $post['post'] . '[/quote]'
                . PHP_EOL . PHP_EOL,
            );
        } else {
            $multiquote = (string) ($SESS->vars['multiquote'] ?? '');
            $multiquotes = $multiquote !== '' ? explode(',', $multiquote) : [];
            if (!in_array((string) $pid, $multiquotes, true)) {
                $multiquotes[] = $pid;
                $SESS->addvar(
                    'multiquote',
                    implode(',', $multiquotes),
                );
            }

            // This line toggles whether or not the qreply window should open
            // on quote.
            if ($PAGE->jsaccess) {
                $this->qreplyform($tid);
            } else {
                header('Location:?act=post&tid=' . $tid);
            }
        }

        $PAGE->JS('softurl');
    }

    public function getlastpost($tid): void
    {
        global $DB,$PAGE;
        $result = $DB->safeselect(
            'MAX(`id`) AS `lastpid`,COUNT(`id`) AS `numposts`',
            'posts',
            'WHERE `tid`=?',
            $tid,
        );
        $f = $DB->arow($result);
        $DB->disposeresult($result);

        $PAGE->JS('softurl');
        $PAGE->location(
            "?act=vt{$tid}&page=" . ceil($f['numposts'] / $this->numperpage)
            . '&pid=' . $f['lastpid'] . '#pid_' . $f['lastpid'],
        );
    }

    public function findpost($pid): void
    {
        global $PAGE,$DB;
        if (!is_numeric($pid)) {
            $couldntfindit = true;
        } else {
            $result = $DB->safespecial(
                <<<'MySQL'
                    SELECT `id`
                        , `auth_id`
                        , `post`
                        , UNIX_TIMESTAMP(`date`) AS `date`
                        , `showsig`
                        , `showemotes`
                        , `tid`
                        , `newtopic`
                        , INET6_NTOA(`ip`) AS `ip`
                        , UNIX_TIMESTAMP(`edit_date`) AS `edit_date`
                        , `editby`
                        , `rating`
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
            while ($f = $DB->arow($result)) {
                if ($f['id'] === $pid) {
                    $pid = $f['id'];
                    $couldntfindit = false;

                    break;
                }

                ++$num;
            }
        }

        $PAGE->JS('softurl');
        if ($couldntfindit) {
            $PAGE->JS('alert', "that post doesn't exist");
        } else {
            $PAGE->location(
                '?act=vt' . $this->id . '&page='
                . ceil($num / $this->numperpage) . '&pid=' . $pid . '#pid_' . $pid,
            );
        }
    }

    public function markread($id): void
    {
        global $SESS,$PAGE,$JAX;
        $topicsread = $JAX->parsereadmarkers($SESS->topicsread);
        $topicsread[$id] = time();
        $SESS->topicsread = json_encode($topicsread, true);
    }

    public function listrating($pid): void
    {
        global $DB,$PAGE,$CFG;
        if (($CFG['ratings'] & 2) !== 0) {
            return;
        }

        $PAGE->JS('softurl');
        $result = $DB->safeselect(
            '`rating`',
            'posts',
            'WHERE `id`=?',
            $DB->basicvalue($pid),
        );
        $row = $DB->arow($result);
        $DB->disposeresult($result);
        $ratings = $row ? json_decode((string) $row[0], true) : [];

        if (empty($ratings)) {
            return;
        }

        $members = [];
        foreach ($ratings as $v) {
            $members = array_merge($members, $v);
        }

        $result = $DB->safeselect(
            '`id`,`display_name`,`group_id`',
            'members',
            'WHERE `id` IN ?',
            $members,
        );
        $mdata = [$result];
        while ($f = $DB->arow($result)) {
            $mdata[$f['id']] = [$f['display_name'], $f['group_id']];
        }

        unset($members);
        $niblets = $DB->getRatingNiblets();
        foreach ($ratings as $k => $v) {
            $page .= '<div class="column">';
            $page .= '<img src="' . $niblets[$k]['img'] . '" /> '
                . $niblets[$k]['title'] . '<ul>';
            foreach ($v as $mid) {
                $page .= '<li>' . $PAGE->meta(
                    'user-link',
                    $mid,
                    $mdata[$mid][1],
                    $mdata[$mid][0],
                ) . '</li>';
            }

            $page .= '</ul></div>';
        }

        $PAGE->JS('listrating', $pid, $page);
    }
}
