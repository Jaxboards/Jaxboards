<?php

declare(strict_types=1);

namespace Jax\ModControls;

use Jax\Database;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\User;

use function array_diff;
use function array_keys;
use function array_map;
use function array_pop;
use function array_search;
use function array_unique;
use function explode;
use function implode;
use function in_array;
use function is_numeric;

final class ModTopics
{
    public function __construct(
        private readonly Database $database,
        private readonly Page $page,
        private readonly Jax $jax,
        private readonly Request $request,
        private readonly Session $session,
        private readonly User $user,
    ) {}

    public function doTopics(string $do): void
    {
        match ($do) {
            'move' => $this->page->command('modcontrols_move', 0),
            'moveto' => $this->moveTo(),
            'pin' => $this->pin(),
            'unpin' => $this->unpin(),
            'lock' => $this->lock(),
            'unlock' => $this->unlock(),
            'delete' => $this->deleteTopics(),
            'merge' => $this->mergeTopics(),
            default => null,
        };
    }

    public function addTopic(int $tid): void
    {
        $this->page->command('softurl');

        if (!$tid) {
            return;
        }

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
                $this->database->basicvalue($tid),
            );
            $mods = $this->database->arow($result);
            $this->database->disposeresult($result);

            if (!$mods) {
                $this->page->command('error', $this->database->error());

                return;
            }

            $mods = array_map(
                static fn($modId) => (int) $modId,
                explode(',', (string) $mods['mods']),
            );
            if (!in_array($this->user->get('id'), $mods, true)) {
                $this->page->command(
                    'error',
                    "You don't have permission to be moderating in this forum",
                );

                return;
            }
        }

        $currentTids = $this->getModTids();
        $tids = [];
        foreach ($currentTids as $currentTid) {
            if (!is_numeric($currentTid)) {
                continue;
            }

            $tids[] = (int) $currentTid;
        }

        if (in_array($tid, $tids, true)) {
            $tids = array_diff($tids, [$tid]);
        } else {
            $tids[] = $tid;
        }

        $this->session->addVar('modtids', implode(',', $tids));

        $this->sync();
    }

    private function cancel(): void
    {
        $this->session->deleteVar('modtids');
        $this->sync();
        $this->page->command('modcontrols_clearbox');
    }

    // TODO: Handle deletion of topics already in Trash
    private function deleteTopics(): void
    {
        if (!$this->session->getVar('modtids')) {
            $this->page->command('error', 'No topics to delete');

            return;
        }

        $forumData = [];

        // Get trashcan id.
        $result = $this->database->safeselect(
            ['id'],
            'forums',
            'WHERE `trashcan`=1 LIMIT 1',
        );
        $trashcan = $this->database->arow($result);
        $this->database->disposeresult($result);

        $trashcan = $trashcan['id'] ?? false;
        $result = $this->database->safeselect(
            ['id', 'fid'],
            'topics',
            Database::WHERE_ID_IN,
            $this->getModTids(),
        );
        $delete = [];
        while ($topic = $this->database->arow($result)) {
            if (!isset($forumData[$topic['fid']])) {
                $forumData[$topic['fid']] = 0;
            }

            ++$forumData[$topic['fid']];
            if (!$trashcan) {
                continue;
            }

            if ($trashcan !== $topic['fid']) {
                continue;
            }

            $delete[] = (int) $topic['id'];
        }

        if ($trashcan) {
            $this->database->safeupdate(
                'topics',
                [
                    'fid' => $trashcan,
                ],
                Database::WHERE_ID_IN,
                $this->getModTids(),
            );
            $forumData[$trashcan] = 1;
        } else {
            $delete = $this->getModTids();
        }

        if ($delete !== []) {
            $this->database->safedelete(
                'posts',
                'WHERE `tid` IN ?',
                $delete,
            );
            $this->database->safedelete(
                'topics',
                Database::WHERE_ID_IN,
                $delete,
            );
        }

        foreach (array_keys($forumData) as $forumId) {
            $this->database->fixForumLastPost($forumId);
        }

        $this->cancel();
        $this->page->command('alert', 'topics deleted!');
    }

    private function mergeTopics(): void
    {
        $page = '';
        $topicIds = $this->getModTids();
        if (
            is_numeric($this->request->post('ot'))
            && in_array($this->request->post('ot'), $topicIds)
        ) {
            // Move the posts and set all posts to normal (newtopic=0).
            $this->database->safeupdate(
                'posts',
                [
                    'newtopic' => '0',
                    'tid' => $this->request->post('ot'),
                ],
                'WHERE `tid` IN ?',
                $this->getModTids(),
            );

            // Make the first post in the topic have newtopic=1.
            // Get the op.
            $result = $this->database->safeselect(
                'MIN(`id`)',
                'posts',
                'WHERE `tid`=?',
                $this->database->basicvalue($this->request->post('ot')),
            );
            $thisrow = $this->database->arow($result);
            $op = array_pop($thisrow);
            $this->database->disposeresult($result);

            $this->database->safeupdate(
                'posts',
                [
                    'newtopic' => 1,
                ],
                Database::WHERE_ID_EQUALS,
                $op,
            );

            // Also fix op.
            $this->database->safeupdate(
                'topics',
                [
                    'op' => $op,
                ],
                Database::WHERE_ID_EQUALS,
                $this->database->basicvalue($this->request->post('ot')),
            );
            unset($topicIds[array_search($this->request->post('ot'), $topicIds, true)]);
            if ($topicIds !== []) {
                $this->database->safedelete(
                    'topics',
                    Database::WHERE_ID_IN,
                    $topicIds,
                );
            }

            $this->cancel();
            $this->page->location('?act=vt' . $this->request->post('ot'));
        }

        $page .= '<form method="post" data-ajax-form="true" '
            . 'style="padding:10px;">'
            . 'Which topic should the topics be merged into?<br>';
        $page .= $this->jax->hiddenFormFields(
            [
                'act' => 'modcontrols',
                'dot' => 'merge',
            ],
        );

        if ($this->session->getVar('modtids')) {
            $result = $this->database->safeselect(
                ['id', 'title'],
                'topics',
                Database::WHERE_ID_IN,
                $this->getModTids(),
            );
            $titles = [];
            while ($topic = $this->database->arow($result)) {
                $titles[$topic['id']] = $topic['title'];
            }

            foreach ($topicIds as $topicId) {
                if (!isset($titles[$topicId])) {
                    continue;
                }

                $page .= '<input type="radio" name="ot" value="' . $topicId . '" /> '
                    . $titles[$topicId] . '<br>';
            }
        }

        $page .= '<input type="submit" value="Merge" /></form>';
        $page = $this->page->collapseBox('Merging Topics', $page);
        $this->page->command('update', 'page', $page);
        $this->page->append('PAGE', $page);
    }

    private function moveTo(): void
    {
        $result = $this->database->safeselect(
            [
                'cat_id',
                'id',
                'lp_tid',
                'lp_topic',
                'lp_uid',
                'mods',
                'nocount',
                '`order`',
                'orderby',
                'path',
                'perms',
                'posts',
                'redirect',
                'redirects',
                'show_ledby',
                'show_sub',
                'subtitle',
                'title',
                'topics',
                'trashcan',
                'UNIX_TIMESTAMP(`lp_date`) AS `lp_date`',
            ],
            'forums',
            Database::WHERE_ID_EQUALS,
            $this->database->basicvalue($this->request->post('id')),
        );
        $rowfound = $this->database->arow($result);
        $this->database->disposeresult($result);
        if (!is_numeric($this->request->post('id')) || !$rowfound) {
            return;
        }

        $result = $this->database->safeselect(
            ['fid'],
            'topics',
            Database::WHERE_ID_IN,
            $this->getModTids(),
        );
        $fids = array_unique(array_map(
            static fn($topic) => (int) $topic['fid'],
            $this->database->arows($result),
        ));

        $this->database->safeupdate(
            'topics',
            [
                'fid' => $this->request->post('id'),
            ],
            Database::WHERE_ID_IN,
            $this->getModTids(),
        );
        $this->cancel();
        $fids[] = $this->request->post('id');
        foreach ($fids as $forumId) {
            $this->database->fixForumLastPost($forumId);
        }

        $this->page->location('?act=vf' . $this->request->post('id'));
    }

    /**
     * @return array<int>
     */
    private function getModTids(): array
    {
        return array_map(
            static fn($tid) => (int) $tid,
            explode(',', (string) $this->session->getVar('modtids')),
        );
    }

    private function lock(): void
    {
        $this->database->safeupdate(
            'topics',
            [
                'locked' => 1,
            ],
            Database::WHERE_ID_IN,
            $this->getModTids(),
        );
        $this->page->command(
            'alert',
            'topics locked!',
        );
        $this->cancel();
    }

    private function pin(): void
    {
        $this->database->safeupdate(
            'topics',
            [
                'pinned' => 1,
            ],
            Database::WHERE_ID_IN,
            $this->getModTids(),
        );
        $this->page->command(
            'alert',
            'topics pinned!',
        );
        $this->cancel();
    }

    private function unlock(): void
    {
        $this->database->safeupdate(
            'topics',
            [
                'locked' => 0,
            ],
            Database::WHERE_ID_IN,
            $this->getModTids(),
        );
        $this->page->command('alert', 'topics unlocked!');
        $this->cancel();
    }

    private function unpin(): void
    {
        $this->database->safeupdate(
            'topics',
            [
                'pinned' => 0,
            ],
            Database::WHERE_ID_IN,
            $this->getModTids(),
        );
        $this->page->command(
            'alert',
            'topics unpinned!',
        );
        $this->cancel();
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
