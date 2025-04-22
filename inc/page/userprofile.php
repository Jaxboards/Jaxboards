<?php

declare(strict_types=1);

namespace Page;

use IPAddress;
use JAX;
use RSSFeed;

use function explode;
use function gmdate;
use function in_array;
use function is_numeric;
use function mb_substr;
use function preg_match;
use function sprintf;
use function ucfirst;
use function ucwords;

final class UserProfile
{
    public $num_activity = 30;

    public $contacturls = [
        'aim' => 'aim:goaim?screenname=%s',
        'bluesky' => 'https://bsky.app/profile/%s.bsky.social',
        'discord' => 'discord:%s',
        'googlechat' => 'gchat:chat?jid=%s',
        'msn' => 'msnim:chat?contact=%s',
        'skype' => 'skype:%s',
        'steam' => 'https://steamcommunity.com/id/%s',
        'twitter' => 'https://twitter.com/%s',
        'yim' => 'ymsgr:sendim?%s',
        'youtube' => 'https://youtube.com/%s',
    ];

    public function __construct()
    {
        global $PAGE;
        $PAGE->loadmeta('userprofile');
    }

    public function route(): void
    {
        global $JAX,$PAGE;
        preg_match('@\d+@', (string) $JAX->b['act'], $m);
        $id = $m[0];
        if (!isset($JAX->b['view'])) {
            $JAX->b['view'] = false;
        }

        if ($id === '' || $id === '0') {
            $PAGE->location('?');
        } elseif (
            $PAGE->jsnewlocation
            && !$PAGE->jsdirectlink
            && !$JAX->b['view']
        ) {
            $this->showcontactcard($id);
        } else {
            $this->showfullprofile($id);
        }
    }

    public function showcontactcard($id): void
    {
        global $PAGE,$DB,$JAX,$SESS,$USER;
        $contactdetails = '';
        $result = $DB->safespecial(
            <<<'EOT'
                SELECT m.`id` AS `uid`,m.`display_name` AS `uname`,m.`usertitle` AS `usertitle`,
                    g.`title` AS `title`,m.`avatar` AS `avatar`,
                    m.`contact_gtalk` AS `contact_googlechat`,m.`contact_aim` AS `contact_aim`,
                    m.`website` AS `website`,
                    m.`contact_yim` AS `contact_yim`,m.`contact_msn` AS `contact_msn`,
                    m.`contact_skype` AS `contact_skype`,m.`contact_steam` AS `contact_steam`,
                    m.`contact_twitter` AS `contact_twitter`,
                    m.`contact_discord` AS `contact_discord`,
                    m.`contact_youtube` AS `contact_youtube`,
                    m.`contact_bluesky` AS `contact_bluesky`
                FROM %t m
                LEFT JOIN %t g
                    ON m.`group_id`=g.`id`
                WHERE m.`id`=?
                EOT
            ,
            ['members', 'member_groups'],
            $id,
        );
        $ud = $DB->arow($result);
        $DB->disposeresult($result);
        if (!$ud) {
            $PAGE->error("This user doesn't exist!");
        }

        foreach ($this->contacturls as $k => $v) {
            if (!$ud['contact_' . $k]) {
                continue;
            }

            $contactdetails .= '<a class="' . $k . ' contact" title="' . $k . ' contact" href="' . sprintf(
                $v,
                $JAX->blockhtml($ud['contact_' . $k]),
            ) . '">&nbsp;</a>';
        }

        $PAGE->JS('softurl');
        $PAGE->JS(
            'window',
            [
                'animate' => false,
                'className' => 'contact-card',
                'content' => $PAGE->meta(
                    'userprofile-contact-card',
                    $ud['uname'],
                    $JAX->pick($ud['avatar'], $PAGE->meta('default-avatar')),
                    $ud['usertitle'],
                    $ud['uid'],
                    $contactdetails,
                    $USER && in_array(
                        $ud['uid'],
                        explode(',', (string) $USER['friends']),
                    ) ? '<a href="?act=buddylist&remove=' . $ud['uid']
                    . '">Remove Contact</a>' : '<a href="?act=buddylist&add='
                    . $ud['uid'] . '">Add Contact</a>',
                    $USER && in_array(
                        $ud['uid'],
                        explode(',', (string) $USER['enemies']),
                    ) ? '<a href="?act=buddylist&unblock=' . $ud['uid']
                    . '">Unblock Contact</a>'
                    : '<a href="?act=buddylist&block=' . $ud['uid']
                    . '">Block Contact</>',
                ),
                'minimizable' => false,
                'title' => 'Contact Card',
                'useoverlay' => 1,
            ],
        );
    }

    public function showfullprofile($id)
    {
        global $PAGE,$DB,$JAX,$USER,$SESS,$PERMS;
        if ($PAGE->jsupdate && empty($JAX->p)) {
            return false;
        }

        if (!$PERMS['can_view_fullprofile']) {
            return $PAGE->location('?');
        }

        $e = '';
        $nouser = false;
        $udata = null;
        if (!$id || !is_numeric($id)) {
            $nouser = true;
        } else {
            $result = $DB->safespecial(
                <<<'EOT'
                    SELECT
                        g.`title` AS `group`,
                        `ip`,
                        m.`about` AS `about`,
                        m.`avatar` AS `avatar`,
                        m.`birthdate` AS `birthdate`,
                        m.`contact_aim` AS `contact_aim`,
                        m.`contact_bluesky` AS `contact_bluesky`,
                        m.`contact_discord` AS `contact_discord`,
                        m.`contact_gtalk` AS `contact_googlechat`,
                        m.`contact_msn` AS `contact_msn`,
                        m.`contact_skype` AS `contact_skype`,
                        m.`contact_steam` AS `contact_steam`,
                        m.`contact_twitter` AS `contact_twitter`,
                        m.`contact_yim` AS `contact_yim`,
                        m.`contact_youtube` AS `contact_youtube`,
                        m.`display_name` AS `display_name`,
                        m.`email_settings` AS `email_settings`,
                        m.`email` AS `email`,
                        m.`enemies` AS `enemies`,
                        m.`friends` AS `friends`,
                        m.`full_name` AS `full_name`,
                        m.`gender` AS `gender`,
                        m.`group_id` AS `group_id`,
                        m.`id` AS `id`,
                        m.`location` AS `location`,
                        m.`mod` AS `mod`,
                        m.`name` AS `name`,
                        m.`notify_pm` AS `notify_pm`,
                        m.`notify_postinmytopic` AS `notify_postinmytopic`,
                        m.`notify_postinsubscribedtopic` AS `notify_postinsubscribedtopic`,
                        m.`nowordfilter` AS `nowordfilter`,
                        m.`posts` AS `posts`,
                        m.`sig` AS `sig`,
                        m.`skin_id` AS `skin_id`,
                        m.`sound_im` AS `sound_im`,
                        m.`sound_pm` AS `sound_pm`,
                        m.`sound_postinmytopic` AS `sound_postinmytopic`,
                        m.`sound_postinsubscribedtopic` AS `sound_postinsubscribedtopic`,
                        m.`sound_shout` AS `sound_shout`,
                        m.`ucpnotepad` AS `ucpnotepad`,
                        m.`usertitle` AS `usertitle`,
                        m.`website` AS `website`,
                        m.`wysiwyg` AS `wysiwyg`,
                        UNIX_TIMESTAMP(m.`join_date`) AS `join_date`,
                        UNIX_TIMESTAMP(m.`last_visit`) AS `last_visit`,
                        DAY(m.`birthdate`) AS `dob_day`,
                        MONTH(m.`birthdate`) AS `dob_month`,
                        YEAR(m.`birthdate`) AS `dob_year`
                    FROM %t m
                    LEFT JOIN %t g
                        ON m.`group_id`=g.`id`
                    WHERE m.`id`=?
                    EOT,
                ['members', 'member_groups'],
                $id,
            );
            echo $DB->error(1);
            $udata = $DB->arow($result);
            $DB->disposeresult($result);
        }

        if (!$udata || $nouser) {
            $e = $PAGE->meta('error', "Sorry, this user doesn't exist.");
            $PAGE->JS('update', 'page', $e);
            $PAGE->append('page', $e);

            return null;
        }

        $pfpageloc = $JAX->b['page'] ?? '';
        $pfbox = '';

        switch ($pfpageloc) {
            case 'posts':
                $result = $DB->safespecial(
                    <<<'EOT'
                        SELECT p.`post` AS `post`,p.`id` AS `pid`,p.`tid` AS `tid`,
                            t.`title` AS `title`,UNIX_TIMESTAMP(p.`date`) AS `date`,f.`perms` AS `perms`
                        FROM %t p
                        LEFT JOIN %t t
                            ON p.`tid`=t.`id`
                        LEFT JOIN %t f
                            ON f.`id`=t.`fid`
                        WHERE p.`auth_id`=?
                        ORDER BY p.`id` DESC
                        LIMIT 10
                        EOT,
                    ['posts', 'topics', 'forums'],
                    $id,
                );
                while ($f = $DB->arow($result)) {
                    $p = $JAX->parseperms($f['perms'], $USER ? $USER['group_id'] : 3);
                    if (!$p['read']) {
                        continue;
                    }

                    $pfbox .= $PAGE->meta(
                        'userprofile-post',
                        $f['tid'],
                        $f['title'],
                        $f['pid'],
                        $JAX->date($f['date']),
                        $JAX->theworks($f['post']),
                    );
                }

                break;

            case 'topics':
                $result = $DB->safespecial(
                    <<<'EOT'
                        SELECT p.`post` AS `post`,p.`id` AS `pid`,p.`tid` AS `tid`,
                            t.`title` AS `title`,UNIX_TIMESTAMP(p.`date`) AS `date`,f.`perms` AS `perms`
                        FROM %t p
                        LEFT JOIN %t t
                            ON p.`tid`=t.`id`
                        LEFT JOIN %t f
                            ON f.`id`=t.`fid`
                        WHERE p.`auth_id`=?
                            AND p.`newtopic`=1
                        ORDER BY p.`id` DESC
                        LIMIT 10
                        EOT,
                    ['posts', 'topics', 'forums'],
                    $id,
                );
                while ($f = $DB->arow($result)) {
                    $p = $JAX->parseperms($f['perms'], $USER ? $USER['group_id'] : 3);
                    if (!$p['read']) {
                        continue;
                    }

                    $pfbox .= $PAGE->meta(
                        'userprofile-topic',
                        $f['tid'],
                        $f['title'],
                        $JAX->date($f['date']),
                        $JAX->theworks($f['post']),
                    );
                }

                if ($pfbox === '' || $pfbox === '0') {
                    $pfbox = 'No topics to show.';
                }

                break;

            case 'about':
                $pfbox = $PAGE->meta(
                    'userprofile-about',
                    $JAX->theworks($udata['about']),
                    $JAX->theworks($udata['sig']),
                );

                break;

            case 'friends':
                if ($udata['friends']) {
                    $result = $DB->safespecial(
                        <<<'EOT'
                            SELECT m.`avatar` AS `avatar`,m.`id` AS `id`,m.`display_name` AS `name`,
                                m.`group_id` AS `group_id`,
                                m.`usertitle` AS `usertitle`
                            FROM %t m
                            LEFT JOIN %t g
                                ON m.`group_id`=g.`id`
                            WHERE m.`id` IN ?
                            ORDER BY `name`
                            EOT,
                        ['members', 'member_groups'],
                        explode(',', (string) $udata['friends']),
                    );

                    while ($f = $DB->arow($result)) {
                        $pfbox .= $PAGE->meta(
                            'userprofile-friend',
                            $f['id'],
                            $JAX->pick(
                                $f['avatar'],
                                $PAGE->meta('default-avatar'),
                            ),
                            $PAGE->meta(
                                'user-link',
                                $f['id'],
                                $f['group_id'],
                                $f['name'],
                            ),
                        );
                    }
                }

                $pfbox = $pfbox === '' || $pfbox === '0'
                    ? "I'm pretty lonely, I have no friends. :("
                    : '<div class="contacts">' . $pfbox . '<br clear="all" /></div>';

                break;

            case 'comments':
                if (isset($JAX->b['del']) && is_numeric($JAX->b['del'])) {
                    if ($PERMS['can_moderate']) {
                        $DB->safedelete(
                            'profile_comments',
                            'WHERE `id`=?',
                            $DB->basicvalue($JAX->b['del']),
                        );
                    } elseif ($PERMS['can_delete_comments']) {
                        $DB->safedelete(
                            'profile_comments',
                            'WHERE `id`=? AND `from`=?',
                            $DB->basicvalue($JAX->b['del']),
                            $DB->basicvalue($USER['id']),
                        );
                    }
                }

                if (isset($JAX->p['comment']) && $JAX->p['comment'] !== '') {
                    if (!$USER || !$PERMS['can_add_comments']) {
                        $e = 'No permission to add comments!';
                    } else {
                        $DB->safeinsert(
                            'activity',
                            [
                                'affected_uid' => $id,
                                'date' => gmdate('Y-m-d H:i:s'),
                                'type' => 'profile_comment',
                                'uid' => $USER['id'],
                            ],
                        );
                        $DB->safeinsert(
                            'profile_comments',
                            [
                                'comment' => $JAX->p['comment'],
                                'date' => gmdate('Y-m-d H:i:s'),
                                'from' => $USER['id'],
                                'to' => $id,
                            ],
                        );
                    }

                    if ($e !== '' && $e !== '0') {
                        $PAGE->JS('error', $e);
                        $pfbox .= $PAGE->meta('error', $e);
                    }
                }

                if ($USER && $PERMS['can_add_comments']) {
                    $pfbox = $PAGE->meta(
                        'userprofile-comment-form',
                        $USER['name'] ?? '',
                        $JAX->pick($USER['avatar'], $PAGE->meta('default-avatar')),
                        JAX::hiddenFormFields(
                            [
                                'act' => 'vu' . $id,
                                'page' => 'comments',
                                'view' => 'profile',
                            ],
                        ),
                    );
                }

                $result = $DB->safespecial(
                    <<<'EOT'
                        SELECT c.`id` AS `id`,c.`to` AS `to`,c.`from` AS `from`,
                            c.`comment` AS `comment`,UNIX_TIMESTAMP(c.`date`) AS `date`,
                            m.`display_name` AS `display_name`,m.`group_id` AS `group_id`,
                            m.`avatar` AS `avatar`
                        FROM %t c
                        LEFT JOIN %t m
                            ON c.`from`=m.`id`
                        WHERE c.`to`=?
                        ORDER BY c.`id` DESC
                        LIMIT 10
                        EOT,
                    ['profile_comments', 'members'],
                    $id,
                );
                $found = false;
                while ($f = $DB->arow($result)) {
                    $pfbox .= $PAGE->meta(
                        'userprofile-comment',
                        $PAGE->meta(
                            'user-link',
                            $f['from'],
                            $f['group_id'],
                            $f['display_name'],
                        ),
                        $JAX->pick(
                            $f['avatar'],
                            $PAGE->meta('default-avatar'),
                        ),
                        $JAX->date($f['date']),
                        $JAX->theworks($f['comment'])
                        . ($PERMS['can_delete_comments']
                        && $f['from'] === $USER['id']
                        || $PERMS['can_moderate']
                        ? ' <a href="?act=' . $JAX->b['act']
                        . '&view=profile&page=comments&del=' . $f['id']
                        . '" class="delete">[X]</a>' : ''),
                    );
                    $found = true;
                }

                if (!$found) {
                    $pfbox .= 'No comments to display!';
                }

                break;

            case 'activity':
            default:
                $pfpageloc = 'activity';
                $result = $DB->safespecial(
                    <<<'EOT'
                        SELECT a.`id` AS `id`,a.`type` AS `type`,a.`arg1` AS `arg1`,a.`uid` AS `uid`,
                            UNIX_TIMESTAMP(a.`date`) AS `date`,a.`affected_uid` AS `affected_uid`,
                            a.`tid` AS `tid`,a.`pid` AS `pid`,a.`arg2` AS `arg2`,
                            a.`affected_uid` AS `aff_id`,m.`display_name` AS `aff_name`,
                            m.`group_id` AS `aff_group_id`
                        FROM %t a
                        LEFT JOIN %t m
                            ON a.`affected_uid`=m.`id`
                        WHERE a.`uid`=?
                        ORDER BY a.`id` DESC
                        LIMIT ?
                        EOT,
                    ['activity', 'members'],
                    $id,
                    $this->num_activity,
                );
                if (isset($JAX->b['fmt']) && $JAX->b['fmt'] === 'RSS') {
                    $feed = new RSSFeed(
                        [
                            'description' => $udata['usertitle'],
                            'title' => $udata['display_name'] . "'s recent activity",
                        ],
                    );
                    while ($f = $DB->arow($result)) {
                        $f['name'] = $udata['display_name'];
                        $f['group_id'] = $udata['group_id'];
                        $data = $this->parse_activity_rss($f);
                        $feed->additem(
                            [
                                'description' => $data['text'],
                                'guid' => $f['id'],
                                'link' => 'https://' . $_SERVER['SERVER_NAME']
                                . $_SERVER['PHP_SELF'] . $data['link'],
                                'pubDate' => gmdate('r', $f['date']),
                                'title' => $data['text'],
                            ],
                        );
                    }

                    $feed->publish();
                }

                while ($f = $DB->arow($result)) {
                    $f['name'] = $udata['display_name'];
                    $f['group_id'] = $udata['group_id'];
                    $pfbox .= $this->parse_activity($f);
                }

                $pfbox = $pfbox === '' || $pfbox === '0'
                    ? 'This user has yet to do anything noteworthy!'
                    : "<a href='./?act=vu" . $id
                    . "&amp;page=activity&amp;fmt=RSS' class='social rss' "
                    . "style='float:right'>RSS</a>" . $pfbox;
        }

        if (
            isset($JAX->b['page'])
            && $JAX->b['page']
            && $PAGE->jsaccess
            && !$PAGE->jsdirectlink
        ) {
            $PAGE->JS('update', 'pfbox', $pfbox);
        } else {
            $PAGE->path(
                [
                    $udata['display_name']
                    . "'s profile" => '?act=vu' . $id . '&view=profile',
                ],
            );
            $PAGE->updatepath();

            $tabs = [
                'about',
                'activity',
                'posts',
                'topics',
                'comments',
                'friends',
            ];
            foreach ($tabs as $k => $v) {
                $tabs[$k] = '<a href="?act=vu' . $id . '&view=profile&page='
                    . $v . '"' . ($v === $pfpageloc ? ' class="active"' : '')
                    . '>' . ucwords($v) . '</a>';
            }

            $contactdetails = '';
            foreach ($udata as $k => $v) {
                if (mb_substr((string) $k, 0, 8) !== 'contact_') {
                    continue;
                }

                if (!$v) {
                    continue;
                }

                $contactdetails .= '<div class="contact ' . mb_substr((string) $k, 8)
                    . '"><a href="'
                    . sprintf($this->contacturls[mb_substr((string) $k, 8)], $v)
                    . '">' . $v . '</a></div>';
            }

            $contactdetails .= '<div class="contact im">'
                . '<a href="javascript:void(0)" onclick="new IMWindow(\''
                . $udata['id'] . "','" . $udata['display_name'] . '\')">IM</a></div>';
            $contactdetails .= '<div class="contact pm">'
                . '<a href="?act=ucp&what=inbox&page=compose&mid='
                . $udata['id'] . '">PM</a></div>';
            if ($PERMS['can_moderate']) {
                $contactdetails .= '<div>IP: <a href="'
                    . '?act=modcontrols&do=iptools&ip=' . IPAddress::asHumanReadable($udata['ip'])
                    . '">' . IPAddress::asHumanReadable($udata['ip']) . '</a></div>';
            }

            $page = $PAGE->meta(
                'userprofile-full-profile',
                $udata['display_name'],
                $JAX->pick($udata['avatar'], $PAGE->meta('default-avatar')),
                $udata['usertitle'],
                $contactdetails,
                $JAX->pick($udata['full_name'], 'N/A'),
                $JAX->pick(ucfirst((string) $udata['gender']), 'N/A'),
                $udata['location'],
                $udata['dob_year'] ? $udata['dob_month'] . '/'
                . $udata['dob_day'] . '/' . $udata['dob_year'] : 'N/A',
                $udata['website'] ? '<a href="' . $udata['website'] . '">'
                . $udata['website'] . '</a>' : 'N/A',
                $JAX->date($udata['join_date']),
                $JAX->date($udata['last_visit']),
                $udata['id'],
                $udata['posts'],
                $udata['group'],
                $tabs[0],
                $tabs[1],
                $tabs[2],
                $tabs[3],
                $tabs[4],
                $tabs[5],
                $pfbox,
                $PERMS['can_moderate']
                ? '<a class="moderate" href="?act=modcontrols&do=emem&mid='
                . $udata['id'] . '">Edit</a>' : '',
            );
            $PAGE->JS('update', 'page', $page);
            $PAGE->append('page', $page);

            $SESS->location_verbose = 'Viewing ' . $udata['display_name'] . "'s profile";
        }

        return null;
    }

    public function parse_activity($activity): array|string
    {
        global $PAGE,$USER,$JAX;
        $user = $PAGE->meta(
            'user-link',
            $activity['uid'],
            $activity['group_id'],
            $USER && $USER['id'] === $activity['uid'] ? 'You' : $activity['name'],
        );
        $otherguy = $PAGE->meta(
            'user-link',
            $activity['aff_id'],
            $activity['aff_group_id'],
            $activity['aff_name'],
        );

        $text = match ($activity['type']) {
            'profile_comment' => "{$user}  commented on  {$otherguy}'s profile",
            'new_post' => "{$user} posted in topic <a href='?act=vt{$activity['tid']}&findpost={$activity['pid']}'>{$activity['arg1']}</a>, " . $JAX->smalldate($activity['date']),
            'new_topic' => "{$user} created new topic <a href='?act=vt{$activity['tid']}'>{$activity['arg1']}</a>, " . $JAX->smalldate($activity['date']),
            'profile_name_change' => $PAGE->meta(
                'user-link',
                $activity['uid'],
                $activity['group_id'],
                $activity['arg1'],
            ) . ' is now known as ' . $PAGE->meta(
                'user-link',
                $activity['uid'],
                $activity['group_id'],
                $activity['arg2'],
            ) . ', ' . $JAX->smalldate($activity['date']),
            'buddy_add' => $user . ' made friends with ' . $otherguy,
        };

        return "<div class=\"activity {$activity['type']}\">{$text}</div>";
    }

    public function parse_activity_rss($activity): array|string
    {
        global $PAGE,$USER,$JAX;

        return match ($activity['type']) {
            'profile_comment' => [
                'link' => $JAX->blockhtml('?act=vu' . $activity['aff_id']),
                'text' => $activity['name'] . ' commented on '
                . $activity['aff_name'] . "'s profile",
            ],
            'new_post' => [
                'link' => $JAX->blockhtml('?act=vt' . $activity['tid'] . '&findpost=' . $activity['pid']),
                'text' => $activity['name'] . ' posted in topic ' . $activity['arg1'],
            ],
            'new_topic' => [
                'link' => $JAX->blockhtml('?act=vt' . $activity['tid']),
                'text' => $activity['name'] . ' created new topic ' . $activity['arg1'],
            ],
            'profile_name_change' => [
                'link' => $JAX->blockhtml('?act=vu' . $activity['uid']),
                'text' => $activity['arg1'] . ' is now known as ' . $activity['arg2'],
            ],
            'buddy_add' => [
                'link' => $JAX->blockhtml('?act=vu' . $activity['uid']),
                'text' => $activity['name'] . ' made friends with ' . $activity['aff_name'],
            ],
        };
    }
}
