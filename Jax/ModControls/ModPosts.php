<?php

declare(strict_types=1);

namespace Jax\ModControls;

use Jax\Database;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\User;

use function array_unique;
use function explode;
use function implode;
use function in_array;
use function is_numeric;

final class ModPosts
{
    public function __construct(
        private readonly Database $database,
        private readonly ModTopics $modTopics,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Session $session,
        private readonly User $user,
    ) {}

    public function addPost(int $pid): void
    {
        if (!$pid) {
            return;
        }

        $post = $this->fetchPost($pid);
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
        if (in_array($pid, $pids, true)) {
            $pids = array_diff($pids, [$pid]);
        } else {
            $pids[] = $pid;
        }

        $this->session->addVar('modpids', implode(',', $pids));

        $this->sync();
    }

    public function doPosts(array|string $do): void
    {
        $pids = $this->getModPids();

        if ($pids === []) {
            $this->page->command('error', 'No posts to work on.');
            $this->clear();

            return;
        }

        match ($do) {
            'move' => $this->page->command('modcontrols_move', 1),
            'moveto' => $this->movePostsTo($pids),
            'delete' => $this->deleteposts($pids)
        };
    }

    /**
     * Does the user have moderation permission of a post?
     * If they're a global mod, automatically yes.
     * If not, then we need to check the forum permissions to
     * see if they're an assigned moderator of the forum.
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

    private function deleteposts(): void
    {
        // Get trashcan.
        $result = $this->database->safeselect(
            '`id`',
            'forums',
            'WHERE `trashcan`=1 LIMIT 1',
        );
        $trashcan = $this->database->arow($result);
        $trashcan = isset($trashcan['id']) ? (int) $trashcan['id'] : 0;

        $this->database->disposeresult($result);

        $result = $this->database->safeselect(
            '`tid`',
            'posts',
            'WHERE `id` IN ?',
            $this->getModPids(),
        );

        // Build list of topic ids that the posts were in.
        $tids = [];
        $pids = $this->getModPids();
        while ($post = $this->database->arow($result)) {
            $tids[] = (int) $post['tid'];
        }
        $tids = array_unique($tids);

        if ($trashcan !== 0) {
            $result = $this->database->safeselect(
                ['auth_id'],
                'posts',
                'WHERE `id`=?',
                $this->database->basicvalue(end($pids)),
            );
            $lp = $this->database->arow($result);
            $this->database->disposeresult($result);

            // Create a new topic.
            $this->database->safeinsert(
                'topics',
                [
                    'auth_id' => $this->user->get('id'),
                    'fid' => $trashcan,
                    'lp_date' => $this->database->datetime(),
                    'lp_uid' => $lp['auth_id'],
                    'op' => $pids[0],
                    'replies' => 0,
                    'poll_choices' => '',
                    'title' => 'Posts deleted from: '
                        . implode(',', $tids),
                ],
            );
            $tid = $this->database->insertId();
            $this->database->safeupdate(
                'posts',
                [
                    'newtopic' => 0,
                    'tid' => $tid,
                ],
                'WHERE `id` IN ?',
                $this->getModPids(),
            );
            $this->database->safeupdate(
                'posts',
                [
                    'newtopic' => 1,
                ],
                'WHERE `id`=?',
                $this->database->basicvalue($pids[0]),
            );
            $tids[] = $tid;
        } else {
            $this->database->safedelete(
                'posts',
                'WHERE `id` IN ?',
                $this->getModPids(),
            );
        }

        foreach ($tids as $tid) {
            // Recount replies.
            $this->database->safespecial(
                <<<'SQL'
                    UPDATE %t
                    SET `replies`=(
                        SELECT COUNT(`id`)
                        FROM %t
                        WHERE `tid`=?
                    )-1
                    WHERE `id`=?
                    SQL
                ,
                ['topics', 'posts'],
                $tid,
                $tid,
            );
        }

        // Fix forum last post for all forums topics were in.
        $fids = [];
        // Add trashcan here too just in case.
        if ($trashcan !== 0) {
            $fids[] = $trashcan;
        }

        $result = $this->database->safeselect(
            ['fid'],
            'topics',
            'WHERE `id` IN ?',
            $tids,
        );
        while ($topic = $this->database->arow($result)) {
            if (!is_numeric($topic['fid'])) {
                continue;
            }

            if ($topic['fid'] <= 0) {
                continue;
            }

            $fids[] = (int) $topic['fid'];
        }

        $this->database->disposeresult($result);
        $fids = array_unique($fids);
        foreach ($fids as $fid) {
            $this->database->fixForumLastPost($fid);
        }

        // Remove them from the page.
        foreach ($pids as $postId) {
            $this->page->command('removeel', '#pid_' . $postId);
        }

        $this->clear();
        return;
    }

    /**
     * Returns sorted list of post IDs we're working with
     * @return list<int>
     */
    private function getModPids(): array
    {
        $modPids = $this->session->getVar('modpids');
        $intPids = $modPids ? array_map(fn($pid) => (int) $pid, explode(',', $modPids)) : [];
        sort($intPids);
        return $intPids;
    }

    private function fetchPost(int $pid): ?array
    {
        $result = $this->database->safeselect(
            ['newtopic', 'tid'],
            'posts',
            'WHERE id=?',
            $this->database->basicvalue($pid),
        );
        $post = $this->database->arow($result);
        $this->database->disposeresult($result);
        return $post;
    }

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

        return $mods ? explode(',', (string) $mods['mods']) : null;
    }

    private function movePostsTo(array $pids) {
        $this->database->safeupdate(
            'posts',
            [
                'tid' => $this->request->post('id'),
            ],
            'WHERE `id` IN ?',
            $pids,
        );
        $this->page->location('?act=vt' . $this->request->post('id'));
        $this->clear();
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
