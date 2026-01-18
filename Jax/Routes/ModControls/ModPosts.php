<?php

declare(strict_types=1);

namespace Jax\Routes\ModControls;

use Jax\Database\Database;
use Jax\Models\Forum;
use Jax\Models\Post;
use Jax\Models\Topic;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Session;
use Jax\User;

use function array_diff;
use function array_map;
use function array_merge;
use function array_unique;
use function end;
use function explode;
use function implode;
use function in_array;
use function sort;

use const SORT_REGULAR;

final readonly class ModPosts
{
    public function __construct(
        private Database $database,
        private ModTopics $modTopics,
        private Page $page,
        private Request $request,
        private Router $router,
        private Session $session,
        private User $user,
    ) {}

    public function addPost(int $pid): void
    {
        $post = $pid !== 0 ? Post::selectOne($pid) : null;
        if ($post === null) {
            return;
        }

        // If the post is a topic post, handle it as a topic instead.
        if ($post->newtopic !== 0) {
            $this->modTopics->addTopic($post->tid);

            return;
        }

        if (!$this->canModPost($post)) {
            $this->page->command(
                'error',
                "You don't have permission to be moderating in this forum",
            );

            return;
        }

        $this->page->command('softurl');

        $pids = $this->getModPids();

        // Toggle's the PID as being selected
        $pids = in_array($pid, $pids, true)
            ? array_diff($pids, [$pid])
            : [...$pids, $pid];

        $this->session->addVar('modpids', implode(',', $pids));

        $this->sync();
    }

    public function doPosts(string $doAct): void
    {
        $pids = $this->getModPids();

        if ($pids === []) {
            $this->page->command('error', 'No posts to work on.');

            return;
        }

        $shouldClear = match ($doAct) {
            'move' => $this->page->command('modcontrols_move', 1),
            'moveto' => $this->movePostsTo(
                $pids,
                (int) $this->request->post('id'),
            ),
            'delete' => $this->deletePosts($pids),
            default => null,
        };

        if ($shouldClear === null) {
            return;
        }

        $this->clear();
    }

    /**
     * Does the user have moderation permission of a post?
     * If they're a global mod, automatically yes.
     * If not, then we need to check the forum permissions to
     * see if they're an assigned moderator of the forum.
     */
    private function canModPost(Post $post): bool
    {
        if ($this->user->isModerator()) {
            return true;
        }

        $mods = $this->fetchForumMods($post);

        return in_array($this->user->get()->id, $mods, true);
    }

    private function clear(): void
    {
        $this->session->deleteVar('modpids');
        $this->sync();
        $this->page->command('modcontrols_clearbox');
    }

    /**
     * @param list<int> $pids
     */
    private function deletePosts(array $pids): bool
    {
        $trashCanForum = Forum::selectOne('WHERE `trashcan`=?', 1);

        $posts = Post::selectMany(
            Database::WHERE_ID_IN,
            $pids,
        );

        // Build list of topic ids that the posts were in.
        $tids = array_unique(array_map(
            static fn(Post $post): int => $post->id,
            $posts,
        ), SORT_REGULAR);

        if ($trashCanForum !== null) {
            $tids[] = $this->movePostsToTrashcan(
                $pids,
                $trashCanForum,
                'Posts deleted from: ' . implode(',', $tids),
            );
        }

        if ($trashCanForum === null) {
            $this->database->delete(
                'posts',
                Database::WHERE_ID_IN,
                $pids,
            );
        }

        $this->updateTopicStats($tids);

        // Fix forum last post for all forums topics were in.
        // Add trashcan here too.
        $topics = Topic::selectMany(Database::WHERE_ID_IN, $tids);
        $fids = array_unique(array_merge(
            $trashCanForum !== null ? [$trashCanForum->id] : [],
            array_map(
                static fn(Topic $topic): int => (int) $topic->fid,
                $topics,
            ),
        ), SORT_REGULAR);

        array_map(Forum::fixLastPost(...), $fids);

        // Remove them from the page.
        array_map(
            fn(int $postId) => $this->page->command(
                'removeel',
                '#pid_' . $postId,
            ),
            $pids,
        );

        return true;
    }

    /**
     * Returns sorted list of post IDs we're working with.
     *
     * @return list<int>
     */
    private function getModPids(): array
    {
        $modPids = (string) $this->session->getVar('modpids');
        $intPids = $modPids !== ''
            ? array_map(
                static fn($pid): int => (int) $pid,
                explode(',', $modPids),
            )
            : [];
        sort($intPids);

        return $intPids;
    }

    /**
     * @return list<int> list of mod user IDs assigned to a forum. Empty array when none.
     */
    private function fetchForumMods(Post $post): array
    {
        $topic = Topic::selectOne($post->tid);
        $forum = $topic !== null
            ? Forum::selectOne($topic->fid)
            : null;

        return $forum?->mods
            ? array_map(
                static fn($mid): int => (int) $mid,
                explode(',', $forum->mods),
            )
            : [];
    }

    /**
     * @param list<int> $pids
     */
    private function movePostsTo(array $pids, int $tid): bool
    {
        if ($tid === 0) {
            return false;
        }

        $this->updatePosts($pids, ['tid' => $tid]);
        $this->router->redirect('topic', ['id' => $tid]);

        return true;
    }

    /**
     * Move posts to trashcan by creating a new topic there,
     * then moving all posts to it.
     *
     * @param list<int> $pids
     *
     * @return int The new topic's ID
     */
    private function movePostsToTrashcan(
        array $pids,
        Forum $trashCanForum,
        string $newTopicTitle,
    ): int {
        $lastPost = Post::selectOne(end($pids));

        $topic = new Topic();
        $topic->author = $this->user->get()->id;
        $topic->fid = $trashCanForum->id;
        $topic->date = $this->database->datetime();
        $topic->lastPostDate = $this->database->datetime();
        $topic->lastPostUser = $lastPost?->author;
        $topic->op = $pids[0];
        $topic->pollQuestion = '';
        $topic->pollChoices = '';
        $topic->replies = 0;
        $topic->title = $newTopicTitle;
        $topic->insert();

        // Set the OP and move posts into the new topic
        $this->updatePosts([$pids[0]], ['newtopic' => 1]);
        $this->movePostsTo($pids, $topic->id);

        return $topic->id;
    }

    /**
     * @param list<int>           $pids
     * @param array<string,mixed> $data
     */
    private function updatePosts(array $pids, array $data): void
    {
        $this->database->update(
            'posts',
            $data,
            Database::WHERE_ID_IN,
            $pids,
        );
    }

    /**
     * @param array<int> $tids
     */
    private function updateTopicStats(array $tids): void
    {
        foreach ($tids as $tid) {
            // Recount replies.
            $this->database->special(
                <<<'SQL'
                    UPDATE %t
                    SET `replies`=(
                        SELECT COUNT(`id`) FROM %t WHERE `tid`=?)-1
                    WHERE `id`=?
                    SQL,
                ['topics', 'posts'],
                $tid,
                $tid,
            );
        }
    }

    private function sync(): void
    {
        $this->page->command(
            'modcontrols_postsync',
            $this->session->getVar('modpids') ?? '',
            $this->session->getVar('modtids') ?? '',
        );
    }
}
