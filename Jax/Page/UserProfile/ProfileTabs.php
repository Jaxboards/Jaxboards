<?php

namespace Jax\Page\UserProfile;

use Jax\Database;
use Jax\Date;
use Jax\DomainDefinitions;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

class ProfileTabs {

    private const TABS = [
        'about',
        'activity',
        'posts',
        'topics',
        'comments',
        'friends',
    ];

    /**
     * var array<string,mixed> the profile we are currently viewing.
     */
    private ?array $profile = null;

    public function __construct(
        private Activity $activity,
        private Database $database,
        private Date $date,
        private DomainDefinitions $domainDefinitions,
        private Jax $jax,
        private Page $page,
        private Request $request,
        private Template $template,
        private TextFormatting $textFormatting,
        private User $user,
    ) {}


    /**
     * @param array<string,mixed> $profile
     * @return list{string,string}
     */
    public function render($profile): array {
        $this->profile = $profile;

        $page = $this->request->both('page');
        $selectedTab = in_array($page, self::TABS, true) ? $page : 'activity';


        $tabHTML = match ($selectedTab) {
            'posts' => $this->showTabPosts(),
            'topics' => $this->showTabTopics(),
            'about' => $this->showTabAbout(),
            'friends' => $this->showTabFriends(),
            'comments' => $this->showTabComments(),
            default => $this->showTabActivity(),
        };

        foreach (self::TABS as $tabIndex => $tab) {
            $active = ($tab === $selectedTab ? ' class="active"' : '');
            $uppercase = ucwords($tab);
            $tabs[$tabIndex] = <<<HTML
                <a href="?act=vu{$this->profile['id']}&view=profile&page={$tab}" {$active}>{$uppercase}</a>
                HTML;
        }

        $this->page->command('update', 'pfbox', $tabHTML);

        return [$tabs, $tabHTML];
    }

    private function showTabAbout()
    {
        return $this->template->meta(
            'userprofile-about',
            $this->textFormatting->theWorks($this->profile['about']),
            $this->textFormatting->theWorks($this->profile['sig']),
        );
    }

    private function showTabActivity(): string
    {
        return $this->activity->render($this->profile);
    }

    private function showTabComments(): string
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
                        'affected_uid' => $this->profile['id'],
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
                        'to' => $this->profile['id'],
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
                        'act' => 'vu' . $this->profile['id'],
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
            $this->profile['id'],
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

    private function showTabFriends(): string
    {
        $tabHTML = '';
        if ($this->profile['friends']) {
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
                explode(',', (string) $this->profile['friends']),
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
            ? '<div class="contacts">' . $tabHTML . '<br clear="all" /></div>'
            : "I'm pretty lonely, I have no friends. :(";
    }

    private function showTabTopics(): string
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
            $this->profile['id'],
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

    private function showTabPosts(): string
    {
        $profile = $this->profile;
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
            $profile['id'],
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
