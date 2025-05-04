<?php

declare(strict_types=1);

namespace Jax\Page\UserProfile;

use Jax\Database;
use Jax\Date;
use Jax\Page;
use Jax\Request;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_map;
use function explode;
use function in_array;
use function ucwords;

final class ProfileTabs
{
    private const TABS = [
        'about',
        'activity',
        'posts',
        'topics',
        'comments',
        'friends',
    ];

    /**
     * @var array<string,null|float|int|string> the profile we are currently viewing
     */
    private ?array $profile = null;

    public function __construct(
        private Activity $activity,
        private Comments $comments,
        private Database $database,
        private Date $date,
        private Page $page,
        private Request $request,
        private Template $template,
        private TextFormatting $textFormatting,
        private User $user,
    ) {}

    /**
     * @param array<string,mixed> $profile
     *
     * @return list{list<string>,string}
     */
    public function render($profile): array
    {
        $this->profile = $profile;

        $page = $this->request->both('page');
        $selectedTab = in_array($page, self::TABS, true) ? $page : 'activity';


        $tabHTML = match ($selectedTab) {
            'posts' => $this->showTabPosts(),
            'topics' => $this->showTabTopics(),
            'about' => $this->showTabAbout(),
            'friends' => $this->showTabFriends(),
            'comments' => $this->comments->render($this->profile),
            default => $this->activity->render($this->profile),
        };

        $tabs = array_map(
            function ($tab) use ($selectedTab) {
                $active = ($tab === $selectedTab ? ' class="active"' : '');
                $uppercase = ucwords($tab);
                $profileId = $this->profile['id'];

                return <<<HTML
                    <a href="?act=vu{$profileId}&page={$tab}" {$active}>{$uppercase}</a>
                    HTML;
            },
            self::TABS,
        );

        $this->page->command('update', 'pfbox', $tabHTML);

        return [$tabs, $tabHTML];
    }

    private function showTabAbout(): string
    {
        return $this->template->meta(
            'userprofile-about',
            $this->textFormatting->theWorks($this->profile['about']),
            $this->textFormatting->theWorks($this->profile['sig']),
        );
    }

    /**
     * @return null|array<array<string,int|string>>
     */
    private function fetchFriends(): ?array
    {
        if (!$this->profile['friends']) {
            return null;
        }

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
        $friends = $this->database->arows($result);
        $this->database->disposeresult($result);

        return $friends;
    }

    private function showTabFriends(): string
    {
        $friends = $this->fetchFriends();
        if (!$friends) {
            return "I'm pretty lonely, I have no friends. :(";
        }

        $tabHTML = '';
        foreach ($friends as $friend) {
            $tabHTML .= $this->template->meta(
                'userprofile-friend',
                $friend['id'],
                $friend['avatar'] ?: $this->template->meta('default-avatar'),
                $this->template->meta(
                    'user-link',
                    $friend['id'],
                    $friend['group_id'],
                    $friend['display_name'],
                ),
            );
        }

        return "<div class='contacts'>{$tabHTML}<br clear='all' /></div>";
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
