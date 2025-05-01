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
use function array_flip;
use function array_keys;
use function array_pop;
use function array_search;
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
        switch ($do) {
            case 'move':
                $this->page->command('modcontrols_move', 0);

                break;

            case 'moveto':
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
                    explode(',', (string) $this->session->getVar('modtids')),
                );
                while ($topic = $this->database->arow($result)) {
                    $fids[$topic['fid']] = 1;
                }

                $fids = array_flip($fids);
                $this->database->safeupdate(
                    'topics',
                    [
                        'fid' => $this->request->post('id'),
                    ],
                    Database::WHERE_ID_IN,
                    explode(',', (string) $this->session->getVar('modtids')),
                );
                $this->cancel();
                $fids[] = $this->request->post('id');
                foreach ($fids as $forumId) {
                    $this->database->fixForumLastPost($forumId);
                }

                $this->page->location('?act=vf' . $this->request->post('id'));

                break;

            case 'pin':
                $this->database->safeupdate(
                    'topics',
                    [
                        'pinned' => 1,
                    ],
                    Database::WHERE_ID_IN,
                    explode(',', (string) $this->session->getVar('modtids')),
                );
                $this->page->command(
                    'alert',
                    'topics pinned!',
                );
                $this->cancel();

                break;

            case 'unpin':
                $this->database->safeupdate(
                    'topics',
                    [
                        'pinned' => 0,
                    ],
                    Database::WHERE_ID_IN,
                    explode(',', (string) $this->session->getVar('modtids')),
                );
                $this->page->command(
                    'alert',
                    'topics unpinned!',
                );
                $this->cancel();

                break;

            case 'lock':
                $this->database->safeupdate(
                    'topics',
                    [
                        'locked' => 1,
                    ],
                    Database::WHERE_ID_IN,
                    explode(',', (string) $this->session->getVar('modtids')),
                );
                $this->page->command(
                    'alert',
                    'topics locked!',
                );
                $this->cancel();

                break;

            case 'unlock':
                $this->database->safeupdate(
                    'topics',
                    [
                        'locked' => 0,
                    ],
                    Database::WHERE_ID_IN,
                    explode(',', (string) $this->session->getVar('modtids')),
                );
                $this->page->command('alert', 'topics unlocked!');
                $this->cancel();

                break;

            case 'delete':
                $this->deletetopics();
                $this->cancel();

                break;

            case 'merge':
                $this->mergetopics();

                break;

            default:
                break;
        }
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

            $mods = explode(',', (string) $mods['mods']);
            if (!in_array($this->user->get('id'), $mods)) {
                $this->page->command(
                    'error',
                    "You don't have permission to be moderating in this forum",
                );

                return;
            }
        }

        $currentTids = explode(',', $this->session->getVar('modtids') ?? '');
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

    // TODO: Handle deletion of topics already in Trash
    private function deletetopics(): void
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
            explode(',', (string) $this->session->getVar('modtids')),
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

            $delete[] = $topic['id'];
        }

        if ($trashcan) {
            $this->database->safeupdate(
                'topics',
                [
                    'fid' => $trashcan,
                ],
                Database::WHERE_ID_IN,
                explode(',', (string) $this->session->getVar('modtids')),
            );
            $delete = implode(',', $delete);
            $forumData[$trashcan] = 1;
        } else {
            $delete = $this->session->getVar('modtids');
        }

        if (!empty($delete)) {
            $this->database->safedelete(
                'posts',
                'WHERE `tid` IN ?',
                explode(',', (string) $delete),
            );
            $this->database->safedelete(
                'topics',
                Database::WHERE_ID_IN,
                explode(',', (string) $delete),
            );
        }

        foreach (array_keys($forumData) as $forumId) {
            $this->database->fixForumLastPost($forumId);
        }

        $this->session->deleteVar('modtids');
        $this->page->command('modcontrols_clearbox');
        $this->page->command('alert', 'topics deleted!');
    }

    private function mergetopics(): void
    {
        $page = '';
        $topicIds = explode(',', $this->session->getVar('modtids') ?? '');
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
                explode(',', (string) $this->session->getVar('modtids')),
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
                explode(',', (string) $this->session->getVar('modtids')),
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

    private function cancel(): void
    {
        $this->session->deleteVar('modtids');
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
