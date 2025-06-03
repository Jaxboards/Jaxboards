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
use Jax\Page\Badges;
use Jax\Request;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_key_exists;
use function array_map;
use function explode;
use function in_array;
use function ucwords;

final readonly class ProfileTabs
{
    /**
     * @var array<string>
     */
    private array $tabs;

    public function __construct(
        private Activity $activity,
        private Badges $badges,
        private Comments $comments,
        private Database $database,
        private Date $date,
        private Page $page,
        private Request $request,
        private Template $template,
        private TextFormatting $textFormatting,
        private User $user,
    ) {
        $tabs = [
            'about',
            'activity',
            'posts',
            'topics',
            'comments',
            'friends',
        ];

        if ($this->badges->isEnabled()) {
            $tabs[] = 'badges';
        }

        $this->tabs = $tabs;
    }

    /**
     * @return list{list<string>,string}
     */
    public function render(Member $member): array
    {
        $page = $this->request->both('page');
        $selectedTab = in_array($page, $this->tabs, true) ? $page : 'activity';

        $tabHTML = match ($selectedTab) {
            'posts' => $this->showTabPosts($member),
            'topics' => $this->showTabTopics($member),
            'about' => $this->showTabAbout($member),
            'friends' => $this->showTabFriends($member),
            'comments' => $this->comments->render($member),
            'badges' => $this->showTabBadges($member),
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
            $this->tabs,
        );

        $this->page->command('update', 'pfbox', $tabHTML);

        return [$tabs, $tabHTML];
    }

    public function showTabBadges(Member $member): string
    {
        if (!$this->badges->isEnabled()) {
            $this->page->location("?act=vu{$member->id}");

            return '';
        }

        $badgesPerMember = $this->badges->fetchBadges([$member], static fn(Member $member): int => $member->id);

        if (!array_key_exists($member->id, $badgesPerMember)) {
            return 'No badges yet!';
        }

        $badgesHTML = '<table class="badges" style="width: 100%">';
        foreach ($badgesPerMember[$member->id] as $badgeTuple) {
            $badgesHTML .= '<tr>'
                . "<td><img src='{$badgeTuple->badge->imagePath}' title='{$badgeTuple->badge->badgeTitle}'></td>"
                . "<td>{$badgeTuple->badge->badgeTitle}</td>"
                . "<td>{$badgeTuple->badge->description}</td>"
                . '</tr>'
                . '<tr>'
                . '<td>Awarded for:</td>'
                . "<td>{$badgeTuple->badgeAssociation->reason}</td>"
                . "<td>{$this->date->autodate($badgeTuple->badgeAssociation->awardDate)}</td>"
                . '</tr>';
        }

        return $badgesHTML . '</table>';
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
                    $friend->groupID,
                    $friend->displayName,
                ),
            );
        }

        return "<div class='contacts'>{$tabHTML}<br clear='all' /></div>";
    }

    private function showTabTopics(Member $member): string
    {
        $tabHTML = '';

        $posts = Post::selectMany(
            $this->database,
            'WHERE `author`=? AND `newtopic`=1
            ORDER BY `id` DESC
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
                'userprofile-topic',
                $post->tid,
                $topic->title,
                $this->date->autoDate($post->date),
                $this->textFormatting->theWorks($post->post),
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
            'WHERE author = ?
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
