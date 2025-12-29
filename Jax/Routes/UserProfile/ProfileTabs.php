<?php

declare(strict_types=1);

namespace Jax\Routes\UserProfile;

use Jax\Database\Database;
use Jax\Date;
use Jax\Models\Forum;
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Models\Topic;
use Jax\Page;
use Jax\Router;
use Jax\Routes\Badges;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_map;
use function explode;
use function in_array;
use function ucwords;

final class ProfileTabs
{
    /**
     * @var array<string>
     */
    private array $tabs = [
        'about',
        'activity',
        'posts',
        'topics',
        'comments',
        'friends',
    ];

    public function __construct(
        private Activity $activity,
        private Badges $badges,
        private Comments $comments,
        private Date $date,
        private Page $page,
        private Router $router,
        private Template $template,
        private TextFormatting $textFormatting,
        private User $user,
    ) {
        if ($this->badges->isEnabled()) {
            $this->tabs[] = 'badges';
        }
    }

    /**
     * @return array<string>
     */
    public function getTabs(): array
    {
        return $this->tabs;
    }

    /**
     * @return list{list<string>,string}
     */
    public function render(string $page, Member $member): string
    {
        $selectedTab = in_array($page, $this->tabs, true) ? $page : 'activity';

        $tabHTML = match ($selectedTab) {
            'posts' => $this->showTabPosts($member),
            'topics' => $this->showTabTopics($member),
            'about' => $this->showTabAbout($member),
            'friends' => $this->showTabFriends($member),
            'comments' => $this->comments->render($member),
            'badges' => $this->badges->showTabBadges($member),
            default => $this->activity->render($member),
        };

        $this->page->command('update', 'pfbox', $tabHTML);

        return $tabHTML;
    }

    private function showTabAbout(Member $member): string
    {
        return $this->template->render(
            'userprofile/about',
            [
                'member' => $member,
            ]
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
            Database::WHERE_ID_IN,
            explode(',', $member->friends),
        );
    }

    private function showTabFriends(Member $member): string
    {
        return $this->template->render('userprofile/friends', [
            'friends' => $this->fetchFriends($member),
        ]);
    }

    private function showTabTopics(Member $member): string
    {
        $tabHTML = '';

        $posts = Post::selectMany(
            'WHERE `author`=? AND `newtopic`=1
            ORDER BY `id` DESC
            LIMIT 10',
            $member->id,
        );

        $topics = Topic::joinedOn(
            $posts,
            static fn(Post $post): int => $post->tid,
        );

        $forums = Forum::joinedOn(
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

            $tabHTML .= $this->template->render(
                'userprofile/topic',
                [
                    'post' => $post,
                    'topic' => $topic,
                ]
            );
        }

        if ($tabHTML === '') {
            return 'No topics to show.';
        }

        return $tabHTML;
    }

    private function showTabPosts(Member $member): string
    {
        $posts = Post::selectMany(
            'WHERE author = ?
            ORDER BY id DESC
            LIMIT 10',
            $member->id,
        );

        $topics = Topic::joinedOn(
            $posts,
            static fn(Post $post): int => $post->tid,
        );

        $forums = Forum::joinedOn(
            $topics,
            static fn(Topic $topic): ?int => $topic->fid,
        );

        return implode('', array_map(function (Post $post) use ($topics, $forums): string {
            $topic = $topics[$post->tid];
            $forum = $forums[$topic->fid];

            $perms = $this->user->getForumPerms($forum->perms);
            if (!$perms['read']) {
                return '';
            }

            return $this->template->render(
                'userprofile/post',
                [
                    'topic' => $topic,
                    'post' => $post,
                ]
            );
        }, $posts));
    }
}
