<?php

declare(strict_types=1);

namespace Jax\Page\UserProfile;

use Jax\Database;
use Jax\Date;
use Jax\Models\Forum;
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Models\Topic;
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
     * @return list{list<string>,string}
     */
    public function render(Member $member): array
    {
        $page = $this->request->both('page');
        $selectedTab = in_array($page, self::TABS, true) ? $page : 'activity';

        $tabHTML = match ($selectedTab) {
            'posts' => $this->showTabPosts($member),
            'topics' => $this->showTabTopics($member),
            'about' => $this->showTabAbout($member),
            'friends' => $this->showTabFriends($member),
            'comments' => $this->comments->render($member),
            default => $this->activity->render($member),
        };

        $tabs = array_map(
            static function ($tab) use ($selectedTab, $member): string {
                $active = ($tab === $selectedTab ? ' class="active"' : '');
                $uppercase = ucwords($tab);
                $profileId = $member->id;

                return <<<HTML
                    <a href="?act=vu{$profileId}&page={$tab}" {$active}>{$uppercase}</a>
                    HTML;
            },
            self::TABS,
        );

        $this->page->command('update', 'pfbox', $tabHTML);

        return [$tabs, $tabHTML];
    }

    private function showTabAbout(Member $member): string
    {
        return $this->template->meta(
            'userprofile-about',
            $this->textFormatting->theWorks($member->about),
            $this->textFormatting->theWorks($member->sig),
        );
    }

    /**
     * @return array<Member>
     */
    private function fetchFriends(Member $member): array
    {
        if ($member->friends === '') {
            return [];
        }

        return Member::selectMany(
            $this->database,
            Database::WHERE_ID_IN,
            explode(',', $member->friends),
        );
    }

    private function showTabFriends(Member $member): string
    {
        $friends = $this->fetchFriends($member);
        if ($friends === []) {
            return "I'm pretty lonely, I have no friends. :(";
        }

        $tabHTML = '';
        foreach ($friends as $friend) {
            $tabHTML .= $this->template->meta(
                'userprofile-friend',
                $friend->id,
                $friend->avatar ?: $this->template->meta('default-avatar'),
                $this->template->meta(
                    'user-link',
                    $friend->id,
                    $friend->group_id,
                    $friend->display_name,
                ),
            );
        }

        return "<div class='contacts'>{$tabHTML}<br clear='all' /></div>";
    }

    private function showTabTopics(Member $member): string
    {
        $tabHTML = '';
        $result = $this->database->special(
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
            $member->id,
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

        if ($tabHTML === '') {
            return 'No topics to show.';
        }

        return $tabHTML;
    }

    private function showTabPosts(Member $member): string
    {
        $tabHTML = '';

        $posts = Post::selectMany(
            $this->database,
            'WHERE auth_id = ?
            ORDER BY id DESC
            LIMIT 10',
            $member->id,
        );

        $topics = Topic::joinedOn(
            $this->database,
            $posts,
            static fn(Post $post): int => $post->tid,
        );

        $forums = Forum::joinedOn(
            $this->database,
            $topics,
            static fn(Topic $topic): ?int => $topic->fid,
        );

        foreach ($posts as $post) {
            $topic = $topics[$post->tid];
            $forum = $forums[$topic->fid];

            $perms = $this->user->getForumPerms($forum->perms);
            if (!$perms['read']) {
                continue;
            }

            $tabHTML .= $this->template->meta(
                'userprofile-post',
                $post->tid,
                $topic->title,
                $post->id,
                $this->date->autoDate($post->date),
                $this->textFormatting->theWorks($post->post),
            );
        }

        return $tabHTML;
    }
}
