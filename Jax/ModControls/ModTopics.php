<?php

declare(strict_types=1);

namespace Jax\ModControls;

use Jax\Database;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\User;

use function _\keyBy;
use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_search;
use function array_unique;
use function explode;
use function implode;
use function in_array;

final readonly class ModTopics
{
    public function __construct(
        private Database $database,
        private Page $page,
        private Jax $jax,
        private Request $request,
        private Session $session,
        private User $user,
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

        if ($tid === 0) {
            return;
        }

        if (!$this->user->getPerm('can_moderate')) {
            $result = $this->database->special(
                <<<'SQL'
                    SELECT `mods`
                    FROM %t
                    WHERE `id`=(
                        SELECT `fid`
                        FROM %t
                        WHERE `id`=?
                    )
                    SQL,
                ['forums', 'topics'],
                $tid,
            );
            $mods = $this->database->arow($result);
            $this->database->disposeresult($result);

            if (!$mods) {
                return;
            }

            $mods = array_map(
                static fn($modId): int => (int) $modId,
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

        $tids = $this->getModTids();

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
        $result = $this->database->select(
            ['id'],
            'forums',
            'WHERE `trashcan`=1 LIMIT 1',
        );
        $trashcan = $this->database->arow($result);
        $this->database->disposeresult($result);

        $trashcan = $trashcan['id'] ?? false;
        $result = $this->database->select(
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
            $this->database->update(
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
            $this->database->delete(
                'posts',
                'WHERE `tid` IN ?',
                $delete,
            );
            $this->database->delete(
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
        $otherTopic = (int) $this->request->asString->post('ot');
        if (
            in_array($otherTopic, $topicIds)
        ) {
            // Move the posts and set all posts to normal (newtopic=0).
            $this->database->update(
                'posts',
                [
                    'newtopic' => '0',
                    'tid' => $otherTopic,
                ],
                'WHERE `tid` IN ?',
                $this->getModTids(),
            );

            // Make the first post in the topic have newtopic=1.
            // Get the op.
            $result = $this->database->select(
                'MIN(`id`) `minId`',
                'posts',
                'WHERE `tid`=?',
                $otherTopic,
            );
            $firstPost = $this->database->arow($result);
            $op = $firstPost ? (int) $firstPost['minId'] : 0;
            $this->database->disposeresult($result);

            if ($op !== 0) {
                $this->database->update(
                    'posts',
                    [
                        'newtopic' => 1,
                    ],
                    Database::WHERE_ID_EQUALS,
                    $op,
                );

                // Also fix op.
                $this->database->update(
                    'topics',
                    [
                        'op' => $op,
                    ],
                    Database::WHERE_ID_EQUALS,
                    $otherTopic,
                );
            }

            unset($topicIds[array_search($otherTopic, $topicIds, true)]);
            if ($topicIds !== []) {
                $this->database->delete(
                    'topics',
                    Database::WHERE_ID_IN,
                    $topicIds,
                );
            }

            $this->cancel();
            $this->page->location('?act=vt' . $otherTopic);

            return;
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
            $result = $this->database->select(
                ['id', 'title'],
                'topics',
                Database::WHERE_ID_IN,
                $this->getModTids(),
            );
            $titles = keyBy($this->database->arows($result), static fn($topic) => $topic['id']);

            foreach ($topicIds as $topicId) {
                if (!array_key_exists($topicId, $titles)) {
                    continue;
                }

                $page .= '<input type="radio" name="ot" value="' . $topicId . '" /> '
                    . $titles[$topicId]['title'] . '<br>';
            }
        }

        $page .= '<input type="submit" value="Merge" /></form>';
        $page = $this->page->collapseBox('Merging Topics', $page);
        $this->page->command('update', 'page', $page);
        $this->page->append('PAGE', $page);
    }

    private function moveTo(): void
    {
        $forumId = (int) $this->request->asString->post('id');
        $result = $this->database->select(
            ['id'],
            'forums',
            Database::WHERE_ID_EQUALS,
            $forumId,
        );
        $rowfound = $this->database->arow($result);
        $this->database->disposeresult($result);
        if (!$rowfound) {
            return;
        }

        $result = $this->database->select(
            ['fid'],
            'topics',
            Database::WHERE_ID_IN,
            $this->getModTids(),
        );
        $fids = array_unique(array_map(
            static fn($topic): int => (int) $topic['fid'],
            $this->database->arows($result),
        ));

        $this->database->update(
            'topics',
            [
                'fid' => $forumId,
            ],
            Database::WHERE_ID_IN,
            $this->getModTids(),
        );
        $this->cancel();
        $fids[] = $forumId;
        foreach ($fids as $fid) {
            $this->database->fixForumLastPost($fid);
        }

        $this->page->location('?act=vf' . $forumId);
    }

    /**
     * @return array<int>
     */
    private function getModTids(): array
    {
        $modtids = $this->session->getVar('modtids');

        return $modtids ? array_map(
            static fn($tid): int => (int) $tid,
            explode(',', (string) $this->session->getVar('modtids')),
        ) : [];
    }

    private function lock(): void
    {
        $this->database->update(
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
        $this->database->update(
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
        $this->database->update(
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
        $this->database->update(
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
