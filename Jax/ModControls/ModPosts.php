<?php

namespace Jax\ModControls;

use Jax\Database;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\User;

class ModPosts {
    public function __construct(
        private readonly Database $database,
        private readonly ModTopics $modTopics,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Session $session,
        private readonly User $user,
    ){}

    public function addPost(int $pid): void
    {
        if (!$pid) {
            return;
        }

        $result = $this->database->safeselect(
            [
                'newtopic',
                'tid',
            ],
            'posts',
            'WHERE id=?',
            $this->database->basicvalue($pid),
        );
        $post = $this->database->arow($result);
        $this->database->disposeresult($result);

        if (!$post) {
            return;
        }

        if ($post['newtopic']) {
            $this->modTopics->addTopic($post['tid']);

            return;
        }

        $this->page->command('softurl');

        // See if they have permission to manipulate this post.
        if (!$this->user->getPerm('can_moderate')) {
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

            if (!$mods) {
                return;
            }

            $mods = explode(',', (string) $mods['mods']);
            if (!in_array($this->user->get('id'), $mods)) {
                $this->page->command(
                    'error',
                    "You don't have permission to be moderating in this forum",
                );

                return;
            }
        }

        $currentPids = explode(',', $this->session->getVar('modpids') ?? '');
        $pids = [];
        foreach ($currentPids as $currentPid) {
            if (!is_numeric($currentPid)) {
                continue;
            }

            $pids[] = (int) $currentPid;
        }

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
        switch ($do) {
            case 'move':
                $this->page->command('modcontrols_move', 1);

                break;

            case 'moveto':
                $this->database->safeupdate(
                    'posts',
                    [
                        'tid' => $this->request->post('id'),
                    ],
                    'WHERE `id` IN ?',
                    explode(',', (string) $this->session->getVar('modpids')),
                );
                $this->cancel();
                $this->page->location('?act=vt' . $this->request->post('id'));

                break;

            case 'delete':
                $this->deleteposts();
                $this->cancel();

                break;

            default:
        }
    }

    private function deleteposts()
    {
        if (
            !$this->session->getVar('modpids')
        ) {
            return $this->page->command('error', 'No posts to delete.');
        }

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
            explode(',', (string) $this->session->getVar('modpids')),
        );

        // Build list of topic ids that the posts were in.
        $tids = [];
        $pids = explode(',', (string) $this->session->getVar('modpids'));
        while ($post = $this->database->arow($result)) {
            $tids[] = (int) $post['tid'];
        }

        $tids = array_unique($tids);

        if ($trashcan !== 0) {
            // Get first & last post.
            foreach ($pids as $postId) {
                if (!isset($op) || !$op || $postId < $op) {
                    $op = $postId;
                }

                if (isset($lp) && $lp && $postId <= $lp) {
                    continue;
                }

                $lp = $postId;
            }

            $result = $this->database->safeselect(
                ['auth_id'],
                'posts',
                'WHERE `id`=?',
                $this->database->basicvalue($lp),
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
                    'op' => $op,
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
                explode(',', (string) $this->session->getVar('modpids')),
            );
            $this->database->safeupdate(
                'posts',
                [
                    'newtopic' => 1,
                ],
                'WHERE `id`=?',
                $this->database->basicvalue($op),
            );
            $tids[] = $tid;
        } else {
            $this->database->safedelete(
                'posts',
                'WHERE `id` IN ?',
                explode(',', (string) $this->session->getVar('modpids')),
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

        return null;
    }

    private function cancel(): void
    {
        $this->session->deleteVar('modpids');
        $this->sync();
        $this->page->command('modcontrols_clearbox');
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
