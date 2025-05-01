<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\Date;
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
use PHP_CodeSniffer\Generators\HTML;

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
    private const ACTIVITY_LIMIT = 30;

    private const TABS = [
        'about',
        'activity',
        'posts',
        'topics',
        'comments',
        'friends',
    ];

    private const CONTACT_URLS = [
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
        private readonly Date $date,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly IPAddress $ipAddress,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly Template $template,
        private readonly User $user,
    ) {
        $this->template->loadMeta('userprofile');
    }

    public function render(): void
    {
        preg_match('@\d+@', (string) $this->request->both('act'), $match);
        $userId = (int) $match[0];

        if (!$userId) {
            $this->page->location('?');

            return;
        }

        // Nothing is live updating on the profile page
        if ($this->request->isJSUpdate() && !$this->request->hasPostData()) {
            return;
        }

        $user = $this->fetchUser($userId);

        if (!$user) {
            $error = $this->template->meta('error', "Sorry, this user doesn't exist.");
            $this->page->command('update', 'page', $error);
            $this->page->append('PAGE', $error);

            return;
        }

        match (true) {
            $this->request->isJSNewLocation()
            && !$this->request->isJSDirectLink()
            && !$this->request->both('view') => $this->showContactCard($user),
            default => $this->showFullProfile($user),
        };
    }

    private function fetchGroupTitle(int $groupId): ?string
    {
        $result = $this->database->safeselect(
            ['title'],
            'member_groups',
            Database::WHERE_ID_EQUALS,
            $groupId,
        );
        $group = $this->database->arow($result);
        $this->database->disposeresult($result);

        return $group['title'] ?? null;
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchUser(int $userId): ?array
    {
        $result = $this->database->safeselect(
            [
                'ip',
                'about',
                'avatar',
                'birthdate',
                'contact_aim',
                'contact_bluesky',
                'contact_discord',
                // TODO: change this in the schema
                'contact_gtalk AS contact_googlechat',
                'contact_msn',
                'contact_skype',
                'contact_steam',
                'contact_twitter',
                'contact_yim',
                'contact_youtube',
                'display_name',
                'email_settings',
                'email',
                'enemies',
                'friends',
                'full_name',
                'gender',
                'group_id',
                'id',
                'location',
                '`mod`',
                'name',
                'notify_pm',
                'notify_postinmytopic',
                'notify_postinsubscribedtopic',
                'nowordfilter',
                'posts',
                'sig',
                'skin_id',
                'sound_im',
                'sound_pm',
                'sound_postinmytopic',
                'sound_postinsubscribedtopic',
                'sound_shout',
                'ucpnotepad',
                'usertitle',
                'website',
                'wysiwyg',
                'UNIX_TIMESTAMP(`join_date`) AS `join_date`',
                'UNIX_TIMESTAMP(`last_visit`) AS `last_visit`',
                'DAY(`birthdate`) AS `dob_day`',
                'MONTH(`birthdate`) AS `dob_month`',
                'YEAR(`birthdate`) AS `dob_year`',
            ],
            'members',
            Database::WHERE_ID_EQUALS,
            $userId,
        );
        $user = $this->database->arow($result);
        $this->database->disposeresult($result);

        return $user;
    }

    /**
     * @param array<string,mixed> $user
     */
    private function showContactCard(array $user): void
    {
        $contactdetails = '';

        foreach (self::CONTACT_URLS as $field => $url) {
            if (!$user['contact_' . $field]) {
                continue;
            }

            $href = sprintf(
                $url,
                $this->textFormatting->blockhtml(
                    $user["contact_{$field}"],
                ),
            );
            $contactdetails .= <<<"HTML"
                <a class="{$field} contact" title="{$field} contact" href="{$href}">&nbsp;</a>
                HTML;
        }

        $this->page->command('softurl');
        $this->page->command(
            'window',
            [
                'animate' => false,
                'className' => 'contact-card',
                'content' => $this->template->meta(
                    'userprofile-contact-card',
                    $user['display_name'],
                    $user['avatar'] ?: $this->template->meta('default-avatar'),
                    $user['usertitle'],
                    $user['id'],
                    $contactdetails,
                    !$this->user->isGuest() && in_array(
                        $user['id'],
                        explode(',', (string) $this->user->get('friends')),
                    ) ? '<a href="?act=buddylist&remove=' . $user['id']
                    . '">Remove Contact</a>' : '<a href="?act=buddylist&add='
                    . $user['id'] . '">Add Contact</a>',
                    !$this->user->isGuest() && in_array(
                        $user['id'],
                        explode(',', (string) $this->user->get('enemies')),
                    ) ? '<a href="?act=buddylist&unblock=' . $user['id']
                    . '">Unblock Contact</a>'
                    : '<a href="?act=buddylist&block=' . $user['id']
                    . '">Block Contact</>',
                ),
                'minimizable' => false,
                'title' => 'Contact Card',
                'useoverlay' => 1,
            ],
        );
    }

    /**
     * @param array<string,mixed> $user
     */
    private function showFullProfile(array $user): void
    {
        if (!$this->user->getPerm('can_view_fullprofile')) {
            $this->page->location('?');

            return;
        }

        $page = $this->request->both('page');
        $pfpageloc = in_array($page, self::TABS, true) ? $page : 'activity';

        $tabHTML = match ($pfpageloc) {
            'posts' => $this->showTabPosts($user),
            'topics' => $this->showTabTopics($user),
            'about' => $this->showTabAbout($user),
            'friends' => $this->showTabFriends($user),
            'comments' => $this->showTabComments($user),
            default => $this->showTabActivity($user),
        };

        if (
            $this->request->both('page') !== null
            && $this->request->isJSAccess()
            && !$this->request->isJSDirectLink()
        ) {
            $this->page->command('update', 'pfbox', $tabHTML);
        } else {
            $this->page->setBreadCrumbs(
                [
                    $user['display_name']
                    . "'s profile" => '?act=vu' . $user['id'] . '&view=profile',
                ],
            );

            foreach (self::TABS as $tabIndex => $tab) {
                $active = ($tab === $pfpageloc ? ' class="active"' : '');
                $uppercase = ucwords($tab);
                $tabs[$tabIndex] = <<<HTML
                    <a href="?act=vu{$user['id']}&view=profile&page={$tab}" $active>
                        {$uppercase}
                    </a>
                    HTML;
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
                    . sprintf(self::CONTACT_URLS[mb_substr((string) $fieldIndex, 8)], $field)
                    . '">' . $field . '</a></div>';
            }

            $contactdetails .= <<<HTML
                <div class="contact im">
                    <a href="javascript:void(0)"
                        onclick="new IMWindow('{$user['id']}','{$user['display_name']}')"
                        >IM</a>
                </div>
                HTML;
            $contactdetails .= <<<HTML
                <div class="contact pm">
                    <a href="?act=ucp&what=inbox&page=compose&mid={$user['id']}">PM</a>
                </div>
                HTML;
            if ($this->user->getPerm('can_moderate')) {
                $ipReadable = $this->ipAddress->asHumanReadable($user['ip']);
                $contactdetails .= <<<HTML
                    <div>IP: <a href="?act=modcontrols&do=iptools&ip={$ipReadable}">{$ipReadable}</a>
                    </div>
                HTML;
            }

            $page = $this->template->meta(
                'userprofile-full-profile',
                $user['display_name'],
                $user['avatar'] ?: $this->template->meta('default-avatar'),
                $user['usertitle'],
                $contactdetails,
                $user['full_name'] ?: 'N/A',
                ucfirst((string) $user['gender']) ?: 'N/A',
                $user['location'],
                $user['dob_year'] ? $user['dob_month'] . '/'
                . $user['dob_day'] . '/' . $user['dob_year'] : 'N/A',
                $user['website'] ? '<a href="' . $user['website'] . '">'
                . $user['website'] . '</a>' : 'N/A',
                $this->date->autoDate($user['join_date']),
                $this->date->autoDate($user['last_visit']),
                $user['id'],
                $user['posts'],
                $this->fetchGroupTitle($user['group_id']),
                $tabs[0],
                $tabs[1],
                $tabs[2],
                $tabs[3],
                $tabs[4],
                $tabs[5],
                $tabHTML,
                $this->user->getPerm('can_moderate')
                ? '<a class="moderate" href="?act=modcontrols&do=emem&mid='
                . $user['id'] . '">Edit</a>' : '',
            );
            $this->page->command('update', 'page', $page);
            $this->page->append('PAGE', $page);

            $this->session->set('location_verbose', 'Viewing ' . $user['display_name'] . "'s profile");
        }
    }

    /**
     * @param array<string,mixed> $activity
     */
    private function parseActivity(array $activity): array|string
    {
        $user = $this->template->meta(
            'user-link',
            $activity['uid'],
            $activity['group_id'],
            $this->user->get('id') === $activity['uid'] ? 'You' : $activity['name'],
        );
        $otherguy = $this->template->meta(
            'user-link',
            $activity['aff_id'],
            $activity['aff_group_id'],
            $activity['aff_name'],
        );

        $text = match ($activity['type']) {
            'profile_comment' => "{$user}  commented on  {$otherguy}'s profile",
            'new_post' => "{$user} posted in topic <a href='?act=vt{$activity['tid']}&findpost={$activity['pid']}'>{$activity['arg1']}</a>, " . $this->date->smallDate($activity['date']),
            'new_topic' => "{$user} created new topic <a href='?act=vt{$activity['tid']}'>{$activity['arg1']}</a>, " . $this->date->smallDate($activity['date']),
            'profile_name_change' => $this->template->meta(
                'user-link',
                $activity['uid'],
                $activity['group_id'],
                $activity['arg1'],
            ) . ' is now known as ' . $this->template->meta(
                'user-link',
                $activity['uid'],
                $activity['group_id'],
                $activity['arg2'],
            ) . ', ' . $this->date->smallDate($activity['date']),
            'buddy_add' => $user . ' made friends with ' . $otherguy,
            default => ''
        };

        return "<div class=\"activity {$activity['type']}\">{$text}</div>";
    }

    /**
     * @param array<string,mixed> $activity
     */
    private function parseActivityRSS(array $activity): array|string
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

    /**
     * @param array<string,mixed> $user
     */
    private function showTabAbout(array $user)
    {
        return $this->template->meta(
            'userprofile-about',
            $this->textFormatting->theWorks($user['about']),
            $this->textFormatting->theWorks($user['sig']),
        );
    }

    /**
     * @param array<string,mixed> $user
     */
    private function showTabActivity(array $user): string
    {
        $tabHTML = '';
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
            $user['id'],
            self::ACTIVITY_LIMIT,
        );
        if ($this->request->both('fmt') === 'RSS') {
            $feed = new RSSFeed(
                [
                    'description' => $user['usertitle'],
                    'title' => $user['display_name'] . "'s recent activity",
                ],
            );
            while ($activity = $this->database->arow($result)) {
                $activity['name'] = $user['display_name'];
                $activity['group_id'] = $user['group_id'];
                $data = $this->parseActivityRSS($activity);
                $feed->additem(
                    [
                        'description' => $data['text'],
                        'guid' => $activity['id'],
                        'link' => $this->domainDefinitions->getBoardUrl() . $data['link'],
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
            $tabHTML .= $this->parseActivity($activity);
        }
        $this->database->disposeresult($result);

        return !$tabHTML
            ? 'This user has yet to do anything noteworthy!'
            : "<a href='./?act=vu{$user['id']}&amp;page=activity&amp;fmt=RSS' class='social rss' "
            . "style='float:right'>RSS</a>{$tabHTML}";
    }

    /**
     * @param array<string,mixed> $user
     */
    private function showTabComments(array $user): string
    {
        $tabHTML = '';
        if (
            is_numeric($this->request->both('del'))
        ) {
            if ($this->user->getPerm('can_moderate')) {
                $this->database->safedelete(
                    'profile_comments',
                    Database::WHERE_ID_EQUALS,
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

        if ($this->request->post('comment')) {
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
                        'affected_uid' => $user['id'],
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
                        'to' => $user['id'],
                    ],
                );
            }

            if ($error !== null) {
                $this->page->command('error', $error);
                $tabHTML .= $this->template->meta('error', $error);
            }
        }

        if (
            !$this->user->isGuest()
            && $this->user->getPerm('can_add_comments')
        ) {
            $tabHTML = $this->template->meta(
                'userprofile-comment-form',
                $this->user->get('name') ?? '',
                $this->user->get('avatar') ?: $this->template->meta('default-avatar'),
                $this->jax->hiddenFormFields(
                    [
                        'act' => 'vu' . $user['id'],
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
            $user['id'],
        );
        $found = false;
        while ($comment = $this->database->arow($result)) {
            $tabHTML .= $this->template->meta(
                'userprofile-comment',
                $this->template->meta(
                    'user-link',
                    $comment['from'],
                    $comment['group_id'],
                    $comment['display_name'],
                ),
                $comment['avatar'] ?: $this->template->meta('default-avatar'),
                $this->date->autoDate($comment['date']),
                $this->textFormatting->theWorks($comment['comment'])
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
            $tabHTML .= 'No comments to display!';
        }

        return $tabHTML;
    }

    /**
     * @param array<string,mixed> $user
     */
    private function showTabFriends(array $user): string
    {
        $tabHTML = '';
        if ($user['friends']) {
            $result = $this->database->safeselect(
                [
                    'avatar',
                    'id',
                    'display_name',
                    'group_id',
                    'usertitle',
                ],
                'members',
                Database::WHERE_ID_IN,
                explode(',', (string) $user['friends']),
            );

            while ($member = $this->database->arow($result)) {
                $tabHTML .= $this->template->meta(
                    'userprofile-friend',
                    $member['id'],
                    $member['avatar'] ?: $this->template->meta('default-avatar'),
                    $this->template->meta(
                        'user-link',
                        $member['id'],
                        $member['group_id'],
                        $member['display_name'],
                    ),
                );
            }
        }

        return $tabHTML
            ? "I'm pretty lonely, I have no friends. :("
            : '<div class="contacts">' . $tabHTML . '<br clear="all" /></div>';
    }

    /**
     * @param array<string,mixed> $user
     */
    private function showTabTopics(array $user): string
    {
        $tabHTML = '';
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
            $user['id'],
        );
        while ($post = $this->database->arow($result)) {
            $perms = $this->user->getForumPerms($post['perms']);
            if (!$perms['read']) {
                continue;
            }

            $tabHTML .= $this->template->meta(
                'userprofile-topic',
                $post['tid'],
                $post['title'],
                $this->date->autoDate($post['date']),
                $this->textFormatting->theWorks($post['post']),
            );
        }

        if ($tabHTML === '' || $tabHTML === '0') {
            $tabHTML = 'No topics to show.';
        }

        return $tabHTML;
    }

    /**
     * @param array<string,mixed> $user
     */
    private function showTabPosts(array $user): string
    {
        $tabHTML = '';

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
            $user['id'],
        );
        while ($post = $this->database->arow($result)) {
            $perms = $this->user->getForumPerms($post['perms']);
            if (!$perms['read']) {
                continue;
            }

            $tabHTML .= $this->template->meta(
                'userprofile-post',
                $post['tid'],
                $post['title'],
                $post['pid'],
                $this->date->autoDate($post['date']),
                $this->textFormatting->theWorks($post['post']),
            );
        }

        return $tabHTML;
    }
}
