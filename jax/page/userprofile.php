<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\RSSFeed;
use Jax\Session;
use Jax\TextFormatting;
use Jax\User;

use function explode;
use function gmdate;
use function in_array;
use function is_numeric;
use function mb_substr;
use function preg_match;
use function sprintf;
use function ucfirst;
use function ucwords;

/**
 * @psalm-api
 */
final class UserProfile
{
    private $num_activity = 30;

    private $contacturls = [
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

    public function __construct(
        private readonly Database $database,
        private readonly IPAddress $ipAddress,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {
        $this->page->loadmeta('userprofile');
    }

    public function render(): void
    {
        preg_match('@\d+@', (string) $this->request->both('act'), $match);
        $userId = $match[0];

        if ($userId === '' || $userId === '0') {
            $this->page->location('?');
        } elseif (
            $this->request->isJSNewLocation()
            && !$this->request->isJSDirectLink()
            && !$this->request->both('view')
        ) {
            $this->showcontactcard($userId);
        } else {
            $this->showfullprofile($userId);
        }
    }

    public function showcontactcard($id): void
    {
        $contactdetails = '';
        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT
                    m.`id` AS `uid`,
                    m.`display_name` AS `uname`,
                    m.`usertitle` AS `usertitle`,
                    g.`title` AS `title`,
                    m.`avatar` AS `avatar`,
                    m.`contact_gtalk` AS `contact_googlechat`,
                    m.`contact_aim` AS `contact_aim`,
                    m.`website` AS `website`,
                    m.`contact_yim` AS `contact_yim`,
                    m.`contact_msn` AS `contact_msn`,
                    m.`contact_skype` AS `contact_skype`,
                    m.`contact_steam` AS `contact_steam`,
                    m.`contact_twitter` AS `contact_twitter`,
                    m.`contact_discord` AS `contact_discord`,
                    m.`contact_youtube` AS `contact_youtube`,
                    m.`contact_bluesky` AS `contact_bluesky`
                FROM %t m
                LEFT JOIN %t g
                    ON m.`group_id`=g.`id`
                WHERE m.`id`=?
                SQL
            ,
            ['members', 'member_groups'],
            $id,
        );
        $contactUser = $this->database->arow($result);
        $this->database->disposeresult($result);
        if (!$contactUser) {
            $this->page->error("This user doesn't exist!");
        }

        foreach ($this->contacturls as $field => $url) {
            if (!$contactUser['contact_' . $field]) {
                continue;
            }

            $contactdetails .= '<a class="' . $field . ' contact" title="' . $field . ' contact" href="' . sprintf(
                $url,
                $this->textFormatting->blockhtml($contactUser['contact_' . $field]),
            ) . '">&nbsp;</a>';
        }

        $this->page->JS('softurl');
        $this->page->JS(
            'window',
            [
                'animate' => false,
                'className' => 'contact-card',
                'content' => $this->page->meta(
                    'userprofile-contact-card',
                    $contactUser['uname'],
                    $this->jax->pick($contactUser['avatar'], $this->page->meta('default-avatar')),
                    $contactUser['usertitle'],
                    $contactUser['uid'],
                    $contactdetails,
                    !$this->user->isGuest() && in_array(
                        $contactUser['uid'],
                        explode(',', (string) $this->user->get('friends')),
                    ) ? '<a href="?act=buddylist&remove=' . $contactUser['uid']
                    . '">Remove Contact</a>' : '<a href="?act=buddylist&add='
                    . $contactUser['uid'] . '">Add Contact</a>',
                    !$this->user->isGuest() && in_array(
                        $contactUser['uid'],
                        explode(',', (string) $this->user->get('enemies')),
                    ) ? '<a href="?act=buddylist&unblock=' . $contactUser['uid']
                    . '">Unblock Contact</a>'
                    : '<a href="?act=buddylist&block=' . $contactUser['uid']
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
        if ($this->request->isJSUpdate() && !$this->request->hasPostData()) {
            return false;
        }

        if (!$this->user->getPerm('can_view_fullprofile')) {
            return $this->page->location('?');
        }

        $nouser = false;
        $user = null;
        if (!$id || !is_numeric($id)) {
            $nouser = true;
        } else {
            $result = $this->database->safespecial(
                <<<'SQL'
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
                    SQL,
                ['members', 'member_groups'],
                $id,
            );
            echo $this->database->error();
            $user = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        if (!$user || $nouser) {
            $error = $this->page->meta('error', "Sorry, this user doesn't exist.");
            $this->page->JS('update', 'page', $error);
            $this->page->append('page', $error);

            return null;
        }

        $pfpageloc = $this->request->both('page') ?? '';
        $pfbox = '';

        switch ($pfpageloc) {
            case 'posts':
                $result = $this->database->safespecial(
                    <<<'SQL'
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
                        SQL,
                    ['posts', 'topics', 'forums'],
                    $id,
                );
                while ($post = $this->database->arow($result)) {
                    $perms = $this->user->parseForumPerms($post['perms']);
                    if (!$perms['read']) {
                        continue;
                    }

                    $pfbox .= $this->page->meta(
                        'userprofile-post',
                        $post['tid'],
                        $post['title'],
                        $post['pid'],
                        $this->jax->date($post['date']),
                        $this->textFormatting->theworks($post['post']),
                    );
                }

                break;

            case 'topics':
                $result = $this->database->safespecial(
                    <<<'SQL'
                        SELECT
                            p.`post` AS `post`,
                            p.`id` AS `pid`,
                            p.`tid` AS `tid`,
                            t.`title` AS `title`,
                            UNIX_TIMESTAMP(p.`date`) AS `date`,
                            f.`perms` AS `perms`
                        FROM %t p
                        LEFT JOIN %t t
                            ON p.`tid`=t.`id`
                        LEFT JOIN %t f
                            ON f.`id`=t.`fid`
                        WHERE p.`auth_id`=?
                            AND p.`newtopic`=1
                        ORDER BY p.`id` DESC
                        LIMIT 10
                        SQL,
                    ['posts', 'topics', 'forums'],
                    $id,
                );
                while ($post = $this->database->arow($result)) {
                    $perms = $this->user->parseForumPerms($post['perms']);
                    if (!$perms['read']) {
                        continue;
                    }

                    $pfbox .= $this->page->meta(
                        'userprofile-topic',
                        $post['tid'],
                        $post['title'],
                        $this->jax->date($post['date']),
                        $this->textFormatting->theworks($post['post']),
                    );
                }

                if ($pfbox === '' || $pfbox === '0') {
                    $pfbox = 'No topics to show.';
                }

                break;

            case 'about':
                $pfbox = $this->page->meta(
                    'userprofile-about',
                    $this->textFormatting->theworks($user['about']),
                    $this->textFormatting->theworks($user['sig']),
                );

                break;

            case 'friends':
                if ($user['friends']) {
                    $result = $this->database->safespecial(
                        <<<'SQL'
                            SELECT
                                m.`avatar` AS `avatar`,
                                m.`id` AS `id`,
                                m.`display_name` AS `name`,
                                m.`group_id` AS `group_id`,
                                m.`usertitle` AS `usertitle`
                            FROM %t m
                            LEFT JOIN %t g
                                ON m.`group_id`=g.`id`
                            WHERE m.`id` IN ?
                            ORDER BY `name`
                            SQL,
                        ['members', 'member_groups'],
                        explode(',', (string) $user['friends']),
                    );

                    while ($member = $this->database->arow($result)) {
                        $pfbox .= $this->page->meta(
                            'userprofile-friend',
                            $member['id'],
                            $this->jax->pick(
                                $member['avatar'],
                                $this->page->meta('default-avatar'),
                            ),
                            $this->page->meta(
                                'user-link',
                                $member['id'],
                                $member['group_id'],
                                $member['name'],
                            ),
                        );
                    }
                }

                $pfbox = $pfbox === '' || $pfbox === '0'
                    ? "I'm pretty lonely, I have no friends. :("
                    : '<div class="contacts">' . $pfbox . '<br clear="all" /></div>';

                break;

            case 'comments':
                if (
                    is_numeric($this->request->both('del'))
                ) {
                    if ($this->user->getPerm('can_moderate')) {
                        $this->database->safedelete(
                            'profile_comments',
                            'WHERE `id`=?',
                            $this->database->basicvalue($this->request->both('del')),
                        );
                    } elseif ($this->user->getPerm('can_delete_comments')) {
                        $this->database->safedelete(
                            'profile_comments',
                            'WHERE `id`=? AND `from`=?',
                            $this->database->basicvalue($this->request->both('del')),
                            $this->database->basicvalue($this->user->get('id')),
                        );
                    }
                }

                if (
                    $this->request->post('comment') !== ''
                ) {
                    $error = null;
                    if (
                        $this->user->isGuest()
                        || !$this->user->getPerm('can_add_comments')
                    ) {
                        $error = 'No permission to add comments!';
                    } else {
                        $this->database->safeinsert(
                            'activity',
                            [
                                'affected_uid' => $id,
                                'date' => $this->database->datetime(),
                                'type' => 'profile_comment',
                                'uid' => $this->user->get('id'),
                            ],
                        );
                        $this->database->safeinsert(
                            'profile_comments',
                            [
                                'comment' => $this->request->post('comment'),
                                'date' => $this->database->datetime(),
                                'from' => $this->user->get('id'),
                                'to' => $id,
                            ],
                        );
                    }

                    if ($error !== null) {
                        $this->page->JS('error', $error);
                        $pfbox .= $this->page->meta('error', $error);
                    }
                }

                if (
                    !$this->user->isGuest()
                    && $this->user->getPerm('can_add_comments')
                ) {
                    $pfbox = $this->page->meta(
                        'userprofile-comment-form',
                        $this->user->get('name') ?? '',
                        $this->jax->pick($this->user->get('avatar'), $this->page->meta('default-avatar')),
                        $this->jax->hiddenFormFields(
                            [
                                'act' => 'vu' . $id,
                                'page' => 'comments',
                                'view' => 'profile',
                            ],
                        ),
                    );
                }

                $result = $this->database->safespecial(
                    <<<'SQL'
                        SELECT
                            c.`id` AS `id`,
                            c.`to` AS `to`,
                            c.`from` AS `from`,
                            c.`comment` AS `comment`,
                            UNIX_TIMESTAMP(c.`date`) AS `date`,
                            m.`display_name` AS `display_name`,
                            m.`group_id` AS `group_id`,
                            m.`avatar` AS `avatar`
                        FROM %t c
                        LEFT JOIN %t m
                            ON c.`from`=m.`id`
                        WHERE c.`to`=?
                        ORDER BY c.`id` DESC
                        LIMIT 10
                        SQL,
                    ['profile_comments', 'members'],
                    $id,
                );
                $found = false;
                while ($comment = $this->database->arow($result)) {
                    $pfbox .= $this->page->meta(
                        'userprofile-comment',
                        $this->page->meta(
                            'user-link',
                            $comment['from'],
                            $comment['group_id'],
                            $comment['display_name'],
                        ),
                        $this->jax->pick(
                            $comment['avatar'],
                            $this->page->meta('default-avatar'),
                        ),
                        $this->jax->date($comment['date']),
                        $this->textFormatting->theworks($comment['comment'])
                        . ($this->user->getPerm('can_delete_comments')
                        && $comment['from'] === $this->user->get('id')
                        || $this->user->getPerm('can_moderate')
                        ? ' <a href="?act=' . $this->request->both('act')
                        . '&view=profile&page=comments&del=' . $comment['id']
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
                $result = $this->database->safespecial(
                    <<<'SQL'
                        SELECT
                            a.`id` AS `id`,
                            a.`type` AS `type`,
                            a.`arg1` AS `arg1`,
                            a.`uid` AS `uid`,
                            UNIX_TIMESTAMP(a.`date`) AS `date`,
                            a.`affected_uid` AS `affected_uid`,
                            a.`tid` AS `tid`,
                            a.`pid` AS `pid`,
                            a.`arg2` AS `arg2`,
                            a.`affected_uid` AS `aff_id`,
                            m.`display_name` AS `aff_name`,
                            m.`group_id` AS `aff_group_id`
                        FROM %t a
                        LEFT JOIN %t m
                            ON a.`affected_uid`=m.`id`
                        WHERE a.`uid`=?
                        ORDER BY a.`id` DESC
                        LIMIT ?
                        SQL,
                    ['activity', 'members'],
                    $id,
                    $this->num_activity,
                );
                if (
                    $this->request->both('fmt') === 'RSS'
                ) {
                    $feed = new RSSFeed(
                        [
                            'description' => $user['usertitle'],
                            'title' => $user['display_name'] . "'s recent activity",
                        ],
                    );
                    while ($activity = $this->database->arow($result)) {
                        $activity['name'] = $user['display_name'];
                        $activity['group_id'] = $user['group_id'];
                        $data = $this->parse_activity_rss($activity);
                        $feed->additem(
                            [
                                'description' => $data['text'],
                                'guid' => $activity['id'],
                                'link' => 'https://' . $_SERVER['SERVER_NAME']
                                . $_SERVER['PHP_SELF'] . $data['link'],
                                'pubDate' => gmdate('r', $activity['date']),
                                'title' => $data['text'],
                            ],
                        );
                    }

                    $feed->publish();
                }

                while ($activity = $this->database->arow($result)) {
                    $activity['name'] = $user['display_name'];
                    $activity['group_id'] = $user['group_id'];
                    $pfbox .= $this->parse_activity($activity);
                }

                $pfbox = $pfbox === '' || $pfbox === '0'
                    ? 'This user has yet to do anything noteworthy!'
                    : "<a href='./?act=vu" . $id
                    . "&amp;page=activity&amp;fmt=RSS' class='social rss' "
                    . "style='float:right'>RSS</a>" . $pfbox;
        }

        if (
            $this->request->both('page') !== null
            && $this->request->jsAccess()
            && !$this->request->isJSDirectLink()
        ) {
            $this->page->JS('update', 'pfbox', $pfbox);
        } else {
            $this->page->path(
                [
                    $user['display_name']
                    . "'s profile" => '?act=vu' . $id . '&view=profile',
                ],
            );
            $this->page->updatepath();

            $tabs = [
                'about',
                'activity',
                'posts',
                'topics',
                'comments',
                'friends',
            ];
            foreach ($tabs as $tabIndex => $tab) {
                $tabs[$tabIndex] = '<a href="?act=vu' . $id . '&view=profile&page='
                    . $tab . '"' . ($tab === $pfpageloc ? ' class="active"' : '')
                    . '>' . ucwords($tab) . '</a>';
            }

            $contactdetails = '';
            foreach ($user as $fieldIndex => $field) {
                if (mb_substr((string) $fieldIndex, 0, 8) !== 'contact_') {
                    continue;
                }

                if (!$field) {
                    continue;
                }

                $contactdetails .= '<div class="contact ' . mb_substr((string) $fieldIndex, 8)
                    . '"><a href="'
                    . sprintf($this->contacturls[mb_substr((string) $fieldIndex, 8)], $field)
                    . '">' . $field . '</a></div>';
            }

            $contactdetails .= '<div class="contact im">'
                . '<a href="javascript:void(0)" onclick="new IMWindow(\''
                . $user['id'] . "','" . $user['display_name'] . '\')">IM</a></div>';
            $contactdetails .= '<div class="contact pm">'
                . '<a href="?act=ucp&what=inbox&page=compose&mid='
                . $user['id'] . '">PM</a></div>';
            if ($this->user->getPerm('can_moderate')) {
                $contactdetails .= '<div>IP: <a href="'
                    . '?act=modcontrols&do=iptools&ip=' . $this->ipAddress->asHumanReadable($user['ip'])
                    . '">' . $this->ipAddress->asHumanReadable($user['ip']) . '</a></div>';
            }

            $page = $this->page->meta(
                'userprofile-full-profile',
                $user['display_name'],
                $this->jax->pick($user['avatar'], $this->page->meta('default-avatar')),
                $user['usertitle'],
                $contactdetails,
                $this->jax->pick($user['full_name'], 'N/A'),
                $this->jax->pick(ucfirst((string) $user['gender']), 'N/A'),
                $user['location'],
                $user['dob_year'] ? $user['dob_month'] . '/'
                . $user['dob_day'] . '/' . $user['dob_year'] : 'N/A',
                $user['website'] ? '<a href="' . $user['website'] . '">'
                . $user['website'] . '</a>' : 'N/A',
                $this->jax->date($user['join_date']),
                $this->jax->date($user['last_visit']),
                $user['id'],
                $user['posts'],
                $user['group'],
                $tabs[0],
                $tabs[1],
                $tabs[2],
                $tabs[3],
                $tabs[4],
                $tabs[5],
                $pfbox,
                $this->user->getPerm('can_moderate')
                ? '<a class="moderate" href="?act=modcontrols&do=emem&mid='
                . $user['id'] . '">Edit</a>' : '',
            );
            $this->page->JS('update', 'page', $page);
            $this->page->append('page', $page);

            $this->session->set('location_verbose', 'Viewing ' . $user['display_name'] . "'s profile");
        }

        return null;
    }

    public function parse_activity($activity): array|string
    {
        $user = $this->page->meta(
            'user-link',
            $activity['uid'],
            $activity['group_id'],
            $this->user->get('id') === $activity['uid'] ? 'You' : $activity['name'],
        );
        $otherguy = $this->page->meta(
            'user-link',
            $activity['aff_id'],
            $activity['aff_group_id'],
            $activity['aff_name'],
        );

        $text = match ($activity['type']) {
            'profile_comment' => "{$user}  commented on  {$otherguy}'s profile",
            'new_post' => "{$user} posted in topic <a href='?act=vt{$activity['tid']}&findpost={$activity['pid']}'>{$activity['arg1']}</a>, " . $this->jax->smalldate($activity['date']),
            'new_topic' => "{$user} created new topic <a href='?act=vt{$activity['tid']}'>{$activity['arg1']}</a>, " . $this->jax->smalldate($activity['date']),
            'profile_name_change' => $this->page->meta(
                'user-link',
                $activity['uid'],
                $activity['group_id'],
                $activity['arg1'],
            ) . ' is now known as ' . $this->page->meta(
                'user-link',
                $activity['uid'],
                $activity['group_id'],
                $activity['arg2'],
            ) . ', ' . $this->jax->smalldate($activity['date']),
            'buddy_add' => $user . ' made friends with ' . $otherguy,
        };

        return "<div class=\"activity {$activity['type']}\">{$text}</div>";
    }

    public function parse_activity_rss($activity): array|string
    {
        return match ($activity['type']) {
            'profile_comment' => [
                'link' => $this->textFormatting->blockhtml('?act=vu' . $activity['aff_id']),
                'text' => $activity['name'] . ' commented on '
                . $activity['aff_name'] . "'s profile",
            ],
            'new_post' => [
                'link' => $this->textFormatting->blockhtml('?act=vt' . $activity['tid'] . '&findpost=' . $activity['pid']),
                'text' => $activity['name'] . ' posted in topic ' . $activity['arg1'],
            ],
            'new_topic' => [
                'link' => $this->textFormatting->blockhtml('?act=vt' . $activity['tid']),
                'text' => $activity['name'] . ' created new topic ' . $activity['arg1'],
            ],
            'profile_name_change' => [
                'link' => $this->textFormatting->blockhtml('?act=vu' . $activity['uid']),
                'text' => $activity['arg1'] . ' is now known as ' . $activity['arg2'],
            ],
            'buddy_add' => [
                'link' => $this->textFormatting->blockhtml('?act=vu' . $activity['uid']),
                'text' => $activity['name'] . ' made friends with ' . $activity['aff_name'],
            ],
        };
    }
}
