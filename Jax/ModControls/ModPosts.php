<?php

declare(strict_types=1);

namespace Jax\ModControls;

use Jax\Database;
use Jax\Page;
use Jax\Request;
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

final readonly class ModPosts
{
    public function __construct(
        private Database $database,
        private ModTopics $modTopics,
        private Page $page,
        private Request $request,
        private Session $session,
        private User $user,
    ) {}

    public function addPost(int $pid): void
    {
        $post = $pid !== 0 ? $this->fetchPost($pid) : null;
        if (!$post) {
            return;
        }

        // If the post is a topic post, handle it as a topic instead.
        if ($post['newtopic']) {
            $this->modTopics->addTopic($post['tid']);

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

    public function doPosts(array|string $doAct): void
    {
        $pids = $this->getModPids();

        if ($pids === []) {
            $this->page->command('error', 'No posts to work on.');

            return;
        }

        $shouldClear = match ($doAct) {
            'move' => $this->page->command('modcontrols_move', 1),
            'moveto' => $this->movePostsTo($pids, (int) $this->request->post('id')),
            'delete' => $this->deletePosts($pids),
            default => null,
        };

        if (!$shouldClear) {
            return;
        }

        $this->clear();
    }

    /**
     * Does the user have moderation permission of a post?
     * If they're a global mod, automatically yes.
     * If not, then we need to check the forum permissions to
     * see if they're an assigned moderator of the forum.
     *
     * @param array<string,mixed> $post
     */
    private function canModPost(array $post): bool
    {
        if ($this->user->getPerm('can_moderate')) {
            return true;
        }

        $mods = $this->fetchForumMods($post);

        return in_array($this->user->get('id'), $mods, true);
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
        $trashCanForum = $this->fetchTrashCanForum();

        $result = $this->database->safeselect(
            ['tid'],
            'posts',
            Database::WHERE_ID_IN,
            $pids,
        );

        // Build list of topic ids that the posts were in.
        $tids = array_unique(array_map(
            static fn($topic): int => (int) $topic['tid'],
            $this->database->arows($result) ?? [],
        ));

        if ($trashCanForum !== null) {
            $tids[] = $this->movePostsToTrashcan(
                $pids,
                $trashCanForum,
                'Posts deleted from: ' . implode(',', $tids),
            );
        }

        if ($trashCanForum === null) {
            $this->database->safedelete(
                'posts',
                Database::WHERE_ID_IN,
                $pids,
            );
        }

        $this->updateTopicStats($tids);

        // Fix forum last post for all forums topics were in.
        // Add trashcan here too.
        $result = $this->database->safeselect(['fid'], 'topics', Database::WHERE_ID_IN, $tids);
        $fids = array_unique(array_merge(
            $trashCanForum ? [$trashCanForum['id']] : [],
            array_map(
                static fn($topic): int => (int) $topic['fid'],
                $this->database->arows($result) ?? [],
            ),
        ));
        $this->database->disposeresult($result);

        array_map(fn($fid) => $this->database->fixForumLastPost($fid), $fids);

        // Remove them from the page.
        array_map(fn($postId) => $this->page->command('removeel', '#pid_' . $postId), $pids);

        return true;
    }

    /**
     * Returns sorted list of post IDs we're working with.
     *
     * @return list<int>
     */
    private function getModPids(): array
    {
        $modPids = $this->session->getVar('modpids');
        $intPids = $modPids
            ? array_map(static fn($pid): int => (int) $pid, explode(',', (string) $modPids))
            : [];
        sort($intPids);

        return $intPids;
    }

    /**
     * @return null|array<string,mixed>
     */
    private function fetchPost(int $pid): ?array
    {
        $result = $this->database->safeselect(
            ['auth_id', 'newtopic', 'tid'],
            'posts',
            Database::WHERE_ID_EQUALS,
            $this->database->basicvalue($pid),
        );
        $post = $this->database->arow($result);
        $this->database->disposeresult($result);

        return $post;
    }

    /**
     * @return list<int> list of mod user IDs assigned to a forum. Empty array when none.
     */
    private function fetchForumMods(array $post): array
    {
        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT `mods`
                FROM %t
                WHERE `id`=(
                    SELECT `fid`
                    FROM %t
                    WHERE `id`=?
                )
                SQL
            ,
            ['forums', 'topics'],
            $post['tid'],
        );
        $mods = $this->database->arow($result);
        $this->database->disposeresult($result);

        return $mods
            ? array_map(static fn($mid): int => (int) $mid, explode(',', (string) $mods['mods']))
            : [];
    }

    /**
     * @return null|array<string,mixed>
     */
    private function fetchTrashCanForum(): ?array
    {
        $result = $this->database->safeselect(
            '`id`',
            'forums',
            'WHERE `trashcan`=1 LIMIT 1',
        );
        $trashcan = $this->database->arow($result);
        $this->database->disposeresult($result);

        return $trashcan;
    }

    /**
     * @param list<int> $pids
     *
     * @return bool true on success
     */
    private function movePostsTo(array $pids, int $tid): bool
    {
        if ($tid !== 0) {
            $this->updatePosts($pids, ['tid' => $tid]);
            $this->page->location('?act=vt' . $tid);
        }

        return true;
    }

    /**
     * Move posts to trashcan by creating a new topic there,
     * then moving all posts to it.
     *
     * @param array<string,mixed> $trashCanForum
     * @param list<int>           $pids
     *
     * @return int The new topic's ID
     */
    private function movePostsToTrashcan(
        array $pids,
        array $trashCanForum,
        string $newTopicTitle,
    ): int {
        $lastPost = $this->fetchPost(end($pids));

        // Create a new topic.
        $this->database->safeinsert(
            'topics',
            [
                'auth_id' => $this->user->get('id'),
                'fid' => $trashCanForum['id'],
                'lp_date' => $this->database->datetime(),
                'lp_uid' => $lastPost['auth_id'],
                'op' => $pids[0],
                'poll_q' => '',
                'poll_choices' => '',
                'replies' => 0,
                'title' => $newTopicTitle,
            ],
        );
        $newTopicId = (int) $this->database->insertId();

        // Set the OP and move posts into the new topic
        $this->updatePosts([$pids[0]], ['newtopic' => 1]);
        $this->movePostsTo($pids, $newTopicId);

        return $newTopicId;
    }

    /**
     * @param list<int>           $pids
     * @param array<string,mixed> $data
     */
    private function updatePosts(array $pids, array $data): void
    {
        $this->database->safeupdate(
            'posts',
            $data,
            Database::WHERE_ID_IN,
            $pids,
        );
    }

    /**
     * @param list<int> $tids
     */
    private function updateTopicStats(array $tids): void
    {
        foreach ($tids as $tid) {
            // Recount replies.
            $this->database->safespecial(
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
