<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\TextFormatting;
use Jax\User;

use function array_diff;
use function array_flip;
use function array_keys;
use function array_pop;
use function array_search;
use function array_shift;
use function array_unique;
use function count;
use function explode;
use function fclose;
use function file_get_contents;
use function filter_var;
use function fopen;
use function fwrite;
use function gmdate;
use function header;
use function implode;
use function in_array;
use function is_numeric;
use function nl2br;
use function strtotime;
use function time;
use function trim;

use const FILTER_VALIDATE_IP;
use const PHP_EOL;

final class ModControls
{
    private $perms;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly IPAddress $ipAddress,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {
        $this->page->loadMeta('modcp');
    }

    public function render(): void
    {
        $this->perms = $this->user->getPerms();
        if (!$this->perms['can_moderate'] && !$this->user->get('mod')) {
            $this->page->command('softurl');
            $this->page->command(
                'alert',
                'Your account does not have moderator permissions.',
            );

            return;
        }

        if ($this->request->both('cancel') !== null) {
            $this->cancel();

            return;
        }

        if ($this->request->isJSUpdate() && !$this->request->hasPostData()) {
            return;
        }

        if ($this->request->post('dot') !== null) {
            $this->dotopics($this->request->post('dot'));

            return;
        }

        if ($this->request->post('dop') !== null) {
            $this->doposts($this->request->post('dop'));

            return;
        }

        switch ($this->request->both('do')) {
            case 'modp':
                $this->modpost($this->request->both('pid'));

                break;

            case 'modt':
                $this->modtopic($this->request->both('tid'));

                break;

            case 'load':
                $this->load();

                break;

            case 'cp':
                $this->showmodcp();

                break;

            case 'emem':
                $this->editmembers();

                break;

            case 'iptools':
                $this->iptools();

                break;

            default:
        }
    }

    private function load(): void
    {
        $script = file_get_contents('dist/modcontrols.js');

        if (!$this->request->isJSAccess()) {
            header('Content-Type: application/javascript; charset=utf-8');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2_592_000) . ' GMT');

            echo $script;

            exit(0);
        }

        $this->page->command('softurl');
        $this->page->command('script', $script);
    }

    private function dotopics(array|string $do): void
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
                    'WHERE `id`=?',
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
                    'WHERE `id` IN ?',
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
                    'WHERE `id` IN ?',
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
                    'WHERE `id` IN ?',
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
                    'WHERE `id` IN ?',
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
                    'WHERE `id` IN ?',
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
                    'WHERE `id` IN ?',
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
        }
    }

    private function doposts(array|string $do): void
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

    private function cancel(): void
    {
        $this->session->deleteVar('modpids');
        $this->session->deleteVar('modtids');
        $this->sync();
        $this->page->command('modcontrols_clearbox');
    }

    private function modpost(null|array|string $pid): void
    {
        if (!is_numeric($pid)) {
            return;
        }

        $pid = (int) $pid;

        $result = $this->database->safeselect(
            [
                'newtopic',
                'tid',
            ],
            'posts',
            'WHERE id=?',
            $this->database->basicvalue($pid),
        );
        $postdata = $this->database->arow($result);
        $this->database->disposeresult($result);

        if (!$postdata) {
            return;
        }

        if ($postdata['newtopic']) {
            $this->modtopic($postdata['tid']);

            return;
        }

        $this->page->command('softurl');

        // See if they have permission to manipulate this post.
        if (!$this->perms['can_moderate']) {
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
                $postdata['tid'],
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

    private function modtopic($tid): void
    {
        $this->page->command('softurl');
        if (!is_numeric($tid)) {
            return;
        }

        $tid = (int) $tid;
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

    private function sync(): void
    {
        $this->page->command(
            'modcontrols_postsync',
            $this->session->getVar('modpids') ?? '',
            $this->session->getVar('modtids') ?? '',
        );
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
            'WHERE `id` IN ?',
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
                'WHERE `id` IN ?',
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
                'WHERE `id` IN ?',
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
                'WHERE `id`=?',
                $op,
            );

            // Also fix op.
            $this->database->safeupdate(
                'topics',
                [
                    'op' => $op,
                ],
                'WHERE `id`=?',
                $this->database->basicvalue($this->request->post('ot')),
            );
            unset($topicIds[array_search($this->request->post('ot'), $topicIds, true)]);
            if ($topicIds !== []) {
                $this->database->safedelete(
                    'topics',
                    'WHERE `id` IN ?',
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
                'WHERE `id` IN ?',
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

    private function showmodcp(string $cppage = ''): void
    {
        if (!$this->user->getPerm('can_moderate')) {
            return;
        }

        $page = $this->page->meta('modcp-index', $cppage);
        $page = $this->page->meta('box', ' id="modcp"', 'Mod CP', $page);

        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
    }

    private function editmembers(): void
    {
        if (!$this->user->getPerm('can_moderate')) {
            return;
        }

        $error = null;
        $hiddenFormFields = $this->jax->hiddenFormFields(
            [
                'act' => 'modcontrols',
                'do' => 'emem',
                'submit' => 'showform',
            ],
        );
        $page = <<<HTML
                <form method="post" data-ajax-form="true">
                    {$hiddenFormFields}
                    Member name:
                    <input type="text" title="Enter member name" name="mname"
                        data-autocomplete-action="searchmembers"
                        data-autocomplete-output="#mid"
                        data-autocomplete-indicator="#validname" />
                    <span id="validname"></span>
                    <input type="hidden" name="mid" id="mid" onchange="this.form.onsubmit();" />
                    <input type="submit" type="View member details" value="Go" />
                </form>
            HTML;
        if (
            $this->request->post('submit') === 'save'
        ) {
            if (
                trim((string) $this->request->post('display_name')) === ''
                || trim((string) $this->request->post('display_name')) === '0'
            ) {
                $page .= $this->page->meta('error', 'Display name is invalid.');
            } else {
                $this->database->safeupdate(
                    'members',
                    [
                        'about' => $this->request->post('about'),
                        'avatar' => $this->request->post('avatar'),
                        'display_name' => $this->request->post('display_name'),
                        'full_name' => $this->request->post('full_name'),
                        'sig' => $this->request->post('signature'),
                    ],
                    'WHERE `id`=?',
                    $this->database->basicvalue($this->request->post('mid')),
                );

                if ($this->database->error() !== '') {
                    $page .= $this->page->meta(
                        'error',
                        'Error updating profile information.',
                    );
                } else {
                    $page .= $this->page->meta('success', 'Profile information saved.');
                }
            }
        }

        if (
            $this->request->post('submit') === 'showform'
            || $this->request->both('mid') !== null
        ) {
            $memberFields = [
                'group_id',
                'id',
                'display_name',
                'avatar',
                'full_name',
                'about',
                'sig',
            ];

            $member = null;

            // Get the member data.
            if (is_numeric($this->request->both('mid'))) {
                $result = $this->database->safeselect(
                    $memberFields,
                    'members',
                    'WHERE `id`=?',
                    $this->database->basicvalue($this->request->both('mid')),
                );
                $member = $this->database->arow($result);
                $this->database->disposeresult($result);
            } elseif ($this->request->post('mname')) {
                $result = $this->database->safeselect(
                    $memberFields,
                    'members',
                    'WHERE `display_name` LIKE ?',
                    $this->database->basicvalue($this->request->post('mname') . '%'),
                );
                $members = [];
                while ($member = $this->database->arow($result)) {
                    $members[] = $member;
                }

                if (count($members) > 1) {
                    $error = 'Many users found!';
                } else {
                    $member = array_shift($members);
                }
            } else {
                $error = 'Member name is a required field.';
            }

            if (!$member) {
                $error = 'No members found that matched the criteria.';
            }

            if (
                $this->user->get('group_id') !== 2
                || $member['group_id'] === 2
                && ($this->user->get('id') !== 1
                && $member['id'] !== $this->user->get('id'))
            ) {
                $error = 'You do not have permission to edit this profile.';
            }

            if ($error !== null) {
                $page .= $this->page->meta('error', $error);
            } else {
                function field($label, $name, $value, $type = 'input'): string
                {
                    return '<tr><td><label for="m_' . $name . '">' . $label
                        . '</label></td><td>'
                        . ($type === 'textarea' ? '<textarea name="' . $name
                        . '" id="m_' . $name . '">' . $value . '</textarea>'
                        : '<input type="text" id="m_' . $name . '" name="' . $name
                        . '" value="' . $value . '" />') . '</td></tr>';
                }

                $page .= '<form method="post" '
                    . 'data-ajax-form="true"><table>';
                $page .= $this->jax->hiddenFormFields(
                    [
                        'act' => 'modcontrols',
                        'do' => 'emem',
                        'mid' => $member['id'],
                        'submit' => 'save',
                    ],
                );
                $page .= field(
                    'Display Name',
                    'display_name',
                    $member['display_name'],
                )
                    . field('Avatar', 'avatar', $member['avatar'])
                    . field('Full Name', 'full_name', $member['full_name'])
                    . field(
                        'About',
                        'about',
                        $this->textFormatting->blockhtml($member['about']),
                        'textarea',
                    )
                    . field(
                        'Signature',
                        'signature',
                        $this->textFormatting->blockhtml($member['sig']),
                        'textarea',
                    );
                $page .= '</table><input type="submit" value="Save" /></form>';
            }
        }

        $this->showmodcp($page);
    }

    private function iptools(): void
    {
        $page = '';

        $ipAddress = $this->request->both('ip') ?? '';
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            $ipAddress = '';
        }

        $changed = false;

        if ($this->request->post('ban') !== null) {
            if (!$this->ipAddress->isBanned($ipAddress)) {
                $changed = true;
                $this->ipAddress->ban($ipAddress);
            }
        } elseif ($this->request->post('unban') !== null) {
            if ($this->ipAddress->isBanned($ipAddress)) {
                $changed = true;
                $this->ipAddress->unBan($ipAddress);
            }
        }

        if ($changed) {
            $fileHandle = fopen($this->domainDefinitions->getBoardPath() . '/bannedips.txt', 'w');
            fwrite($fileHandle, implode(PHP_EOL, $this->ipAddress->getBannedIps()));
            fclose($fileHandle);
        }

        $hiddenFields = $this->jax->hiddenFormFields(
            [
                'act' => 'modcontrols',
                'do' => 'iptools',
            ],
        );
        $form = <<<EOT
            <form method='post' data-ajax-form='true'>
                {$hiddenFields}
                <label>IP:
                <input type='text' name='ip' title="Enter IP address" value='{$ipAddress}' /></label>
                <input type='submit' value='Submit' title="Search for IP" />
            </form>
            EOT;
        if ($ipAddress) {
            $page .= "<h3>Data for {$ipAddress}:</h3>";

            $hiddenFields = $this->jax->hiddenFormFields(
                [
                    'act' => 'modcontrols',
                    'do' => 'iptools',
                    'ip' => $ipAddress,
                ],
            );
            $banCode = $this->ipAddress->isBanned($ipAddress) ? <<<'HTML'
                <span style="color:#900">
                    banned
                </span>
                <input type="submit" name="unban"
                    onclick="this.form.submitButton=this" value="Unban" />
                HTML : <<<'HTML'
                <span style="color:#090">
                    not banned
                </span>
                <input type="submit" name="ban"
                    onclick="this.form.submitButton=this" value="Ban" />
                HTML;

            $torDate = gmdate('Y-m-d', strtotime('-2 days'));
            $page .= $this->box(
                'Info',
                <<<EOT
                    <form method='post' data-ajax-form='true'>
                        {$hiddenFields}
                        IP ban status: {$banCode}<br>
                    </form>
                    IP Lookup Services: <ul>
                        <li><a href="https://whois.domaintools.com/{$ipAddress}">DomainTools Whois</a></li>
                        <li><a href="https://www.domaintools.com/research/traceroute/?query={$ipAddress}">
                            DomainTools Traceroute
                        </a></li>
                        <li><a href="https://www.ip2location.com/{$ipAddress}">IP2Location Lookup</a></li>
                        <li><a href="https://www.dan.me.uk/torcheck?ip={$ipAddress}">IP2Location Lookup</a></li>
                        <li><a href="https://metrics.torproject.org/exonerator.html?ip={$ipAddress}&timestamp={$torDate}">
                            ExoneraTor Lookup
                        </a></li>
                        <li><a href="https://www.projecthoneypot.org/ip_{$ipAddress}">Project Honeypot Lookup</a></li>
                        <li><a href="https://www.stopforumspam.com/ipcheck/{$ipAddress}">StopForumSpam Lookup</a></li>
                    </ul>
                    EOT,
            );

            $content = [];
            $result = $this->database->safeselect(
                [
                    'display_name',
                    'group_id',
                    'id',
                ],
                'members',
                'WHERE `ip`=?',
                $this->database->basicvalue($this->ipAddress->asBinary($ipAddress)),
            );
            while ($member = $this->database->arow($result)) {
                $content[] = $this->page->meta(
                    'user-link',
                    $member['id'],
                    $member['group_id'],
                    $member['display_name'],
                );
            }

            $page .= $this->box('Users with this IP:', implode(', ', $content));

            if ($this->config->getSetting('shoutbox')) {
                $content = '';
                $result = $this->database->safespecial(
                    <<<'SQL'
                        SELECT
                            m.`display_name` AS `display_name`,
                            m.`group_id` AS `group_id`,
                            s.`id` AS `id`,
                            s.`shout` AS `shout`,
                            s.`uid` AS `uid`,
                            UNIX_TIMESTAMP(s.`date`) AS `date`
                        FROM %t s
                        LEFT JOIN %t m
                            ON m.`id`=s.`uid`
                        WHERE s.`ip`=?
                        ORDER BY `id`
                        DESC LIMIT 5
                        SQL
                    ,
                    [
                        'shouts',
                        'members',
                    ],
                    $this->database->basicvalue($this->ipAddress->asBinary($ipAddress)),
                );
                while ($shout = $this->database->arow($result)) {
                    $content .= $this->page->meta(
                        'user-link',
                        $shout['uid'],
                        $shout['group_id'],
                        $shout['display_name'],
                    );
                    $content .= ': ' . $shout['shout'] . '<br>';
                }

                $page .= $this->box('Last 5 shouts:', $content);
            }

            $content = '';
            $result = $this->database->safeselect(
                ['post'],
                'posts',
                'WHERE `ip`=? ORDER BY `id` DESC LIMIT 5',
                $this->database->basicvalue($this->ipAddress->asBinary($ipAddress)),
            );
            while ($post = $this->database->arow($result)) {
                $content .= "<div class='post'>"
                    . nl2br($this->textFormatting->blockhtml($this->textFormatting->textonly($post['post'])))
                    . '</div>';
            }

            $page .= $this->box('Last 5 posts:', $content);
        }

        $this->showmodcp($form . $page);
    }

    private function box(string $title, string $content): string
    {
        $content = ($content ?: '--No Data--');

        return <<<EOT
            <div class='minibox'>
                <div class='title'>{$title}</div>
                <div class='content'>{$content}</div>
            </div>
            EOT;
    }
}
