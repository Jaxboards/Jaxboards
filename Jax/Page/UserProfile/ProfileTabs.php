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

final readonly class ProfileTabs
{
    private const TABS = [
        'about',
        'activity',
        'posts',
        'topics',
        'comments',
        'friends',
    ];

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
     * @param array<string,null|float|int|string> $profile
     *
     * @return list{list<string>,string}
     */
    public function render(array $profile): array
    {
        $page = $this->request->both('page');
        $selectedTab = in_array($page, self::TABS, true) ? $page : 'activity';

        $tabHTML = match ($selectedTab) {
            'posts' => $this->showTabPosts($profile),
            'topics' => $this->showTabTopics($profile),
            'about' => $this->showTabAbout($profile),
            'friends' => $this->showTabFriends($profile),
            'comments' => $this->comments->render($profile),
            default => $this->activity->render($profile),
        };

        $tabs = array_map(
            static function ($tab) use ($selectedTab, $profile): string {
                $active = ($tab === $selectedTab ? ' class="active"' : '');
                $uppercase = ucwords($tab);
                $profileId = $profile['id'];

                return <<<HTML
                    <a href="?act=vu{$profileId}&page={$tab}" {$active}>{$uppercase}</a>
                    HTML;
            },
            self::TABS,
        );

        $this->page->command('update', 'pfbox', $tabHTML);

        return [$tabs, $tabHTML];
    }

    /**
     * @param array<string,null|float|int|string> $profile
     */
    private function showTabAbout(array $profile): string
    {
        return $this->template->meta(
            'userprofile-about',
            $this->textFormatting->theWorks((string) $profile['about']),
            $this->textFormatting->theWorks((string) $profile['sig']),
        );
    }

    /**
     * @param array<string,null|float|int|string> $profile
     *
     * @return null|array<array<string,int|string>>
     */
    private function fetchFriends(array $profile): ?array
    {
        if (!$profile['friends']) {
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
            explode(',', (string) $profile['friends']),
        );
        $friends = $this->database->arows($result);
        $this->database->disposeresult($result);

        return $friends;
    }

    /**
     * @param array<string,null|float|int|string> $profile
     */
    private function showTabFriends(array $profile): string
    {
        $friends = $this->fetchFriends($profile);
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

    /**
     * @param array<string,null|float|int|string> $profile
     */
    private function showTabTopics(array $profile): string
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
            $profile['id'],
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
            return 'No topics to show.';
        }

        return $tabHTML;
    }

    /**
     * @param array<string,null|float|int|string> $profile
     */
    private function showTabPosts(array $profile): string
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
