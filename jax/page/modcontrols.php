<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Database;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Page;
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
        private readonly IPAddress $ipAddress,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {
        $this->page->loadmeta('modcp');
    }

    public function load(): void
    {
        $script = file_get_contents('dist/modcontrols.js');

        if (!$this->page->jsaccess) {
            header('Content-Type: application/javascript; charset=utf-8');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2_592_000) . ' GMT');

            echo $script;

            exit(0);
        }

        $this->page->JS('softurl');
        $this->page->JS('script', $script);
    }

    public function route(): void
    {
        $this->perms = $this->user->getPerms();
        if (!$this->perms['can_moderate'] && !$this->user->get('mod')) {
            $this->page->JS('softurl');
            $this->page->JS(
                'alert',
                'Your account does not have moderator permissions.',
            );

            return;
        }

        if (isset($this->jax->b['cancel']) && $this->jax->b['cancel']) {
            $this->cancel();

            return;
        }

        if ($this->page->jsupdate && empty($this->jax->p)) {
            return;
        }

        if (isset($this->jax->p['dot']) && $this->jax->p['dot']) {
            $this->dotopics($this->jax->p['dot']);

            return;
        }

        if (isset($this->jax->p['dop']) && $this->jax->p['dop']) {
            $this->doposts($this->jax->p['dop']);

            return;
        }

        switch ($this->jax->b['do']) {
            case 'modp':
                $this->modpost($this->jax->b['pid']);

                break;

            case 'modt':
                $this->modtopic($this->jax->b['tid']);

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

    public function dotopics($do): void
    {
        switch ($do) {
            case 'move':
                $this->page->JS('modcontrols_move', 0);

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
                    $this->database->basicvalue($this->jax->p['id']),
                );
                $rowfound = $this->database->arow($result);
                $this->database->disposeresult($result);
                if (!is_numeric($this->jax->p['id']) || !$rowfound) {
                    return;
                }

                $result = $this->database->safeselect(
                    ['fid'],
                    'topics',
                    'WHERE `id` IN ?',
                    explode(',', (string) $this->session->vars['modtids']),
                );
                while ($f = $this->database->arow($result)) {
                    $fids[$f['fid']] = 1;
                }

                $fids = array_flip($fids);
                $this->database->safeupdate(
                    'topics',
                    [
                        'fid' => $this->jax->p['id'],
                    ],
                    'WHERE `id` IN ?',
                    explode(',', (string) $this->session->vars['modtids']),
                );
                $this->cancel();
                $fids[] = $this->jax->p['id'];
                foreach ($fids as $v) {
                    $this->database->fixForumLastPost($v);
                }

                $this->page->location('?act=vf' . $this->jax->p['id']);

                break;

            case 'pin':
                $this->database->safeupdate(
                    'topics',
                    [
                        'pinned' => 1,
                    ],
                    'WHERE `id` IN ?',
                    explode(',', (string) $this->session->vars['modtids']),
                );
                $this->page->JS(
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
                    explode(',', (string) $this->session->vars['modtids']),
                );
                $this->page->JS(
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
                    explode(',', (string) $this->session->vars['modtids']),
                );
                $this->page->JS(
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
                    explode(',', (string) $this->session->vars['modtids']),
                );
                $this->page->JS('alert', 'topics unlocked!');
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

    public function doposts($do): void
    {
        switch ($do) {
            case 'move':
                $this->page->JS('modcontrols_move', 1);

                break;

            case 'moveto':
                $this->database->safeupdate(
                    'posts',
                    [
                        'tid' => $this->jax->p['id'],
                    ],
                    'WHERE `id` IN ?',
                    explode(',', (string) $this->session->vars['modpids']),
                );
                $this->cancel();
                $this->page->location('?act=vt' . $this->jax->p['id']);

                break;

            case 'delete':
                $this->deleteposts();
                $this->cancel();

                break;

            default:
        }
    }

    public function cancel(): void
    {
        $this->session->delvar('modpids');
        $this->session->delvar('modtids');
        $this->sync();
        $this->page->JS('modcontrols_clearbox');
    }

    public function modpost($pid)
    {
        if (!is_numeric($pid)) {
            return null;
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
            return null;
        }

        if ($postdata['newtopic']) {
            return $this->modtopic($postdata['tid']);
        }

        $this->page->JS('softurl');

        // See if they have permission to manipulate this post.
        if (!$this->perms['can_moderate']) {
            $result = $this->database->safespecial(
                <<<'EOT'
                    SELECT `mods`
                    FROM %t
                    WHERE `id`=(
                        SELECT `fid`
                        FROM %t
                        WHERE `id`=?
                    )
                    EOT
                ,
                ['forums', 'topics'],
                $postdata['tid'],
            );

            $mods = $this->database->arow($result);
            $this->database->disposeresult($result);

            if (!$mods) {
                return null;
            }

            $mods = explode(',', (string) $mods['mods']);
            if (!in_array($this->user->get('id'), $mods)) {
                return $this->page->JS(
                    'error',
                    "You don't have permission to be moderating in this forum",
                );
            }
        }

        $currentPids = isset($this->session->vars['modpids'])
            ? explode(',', $this->session->vars['modpids']) : [];
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

        $this->session->addvar('modpids', implode(',', $pids));

        $this->sync();

        return null;
    }

    public function modtopic($tid)
    {
        global $PERMS;
        $this->page->JS('softurl');
        if (!is_numeric($tid)) {
            return null;
        }

        $tid = (int) $tid;
        if (!$PERMS['can_moderate']) {
            $result = $this->database->safespecial(
                <<<'EOT'
                    SELECT `mods`
                    FROM %t
                    WHERE `id`=(
                        SELECT `fid`
                        FROM %t
                        WHERE `id`=?
                    )
                    EOT
                ,
                ['forums', 'topics'],
                $this->database->basicvalue($tid),
            );
            $mods = $this->database->arow($result);
            $this->database->disposeresult($result);

            if (!$mods) {
                return $this->page->JS('error', $this->database->error());
            }

            $mods = explode(',', (string) $mods['mods']);
            if (!in_array($this->user->get('id'), $mods)) {
                return $this->page->JS(
                    'error',
                    "You don't have permission to be moderating in this forum",
                );
            }
        }

        $currentTids = isset($this->session->vars['modtids'])
            ? explode(',', $this->session->vars['modtids']) : [];
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

        $this->session->addvar('modtids', implode(',', $tids));

        $this->sync();

        return null;
    }

    public function sync(): void
    {
        $this->page->JS(
            'modcontrols_postsync',
            $this->session->vars['modpids'] ?? '',
            $this->session->vars['modtids'] ?? '',
        );
    }

    public function deleteposts()
    {
        if (
            !isset($this->session->vars['modpids'])
            || !$this->session->vars['modpids']
        ) {
            return $this->page->JS('error', 'No posts to delete.');
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
            explode(',', (string) $this->session->vars['modpids']),
        );

        // Build list of topic ids that the posts were in.
        $tids = [];
        $pids = explode(',', (string) $this->session->vars['modpids']);
        while ($f = $this->database->arow($result)) {
            $tids[] = (int) $f['tid'];
        }

        $tids = array_unique($tids);

        if ($trashcan !== 0) {
            // Get first & last post.
            foreach ($pids as $v) {
                if (!isset($op) || !$op || $v < $op) {
                    $op = $v;
                }

                if (isset($lp) && $lp && $v <= $lp) {
                    continue;
                }

                $lp = $v;
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
                    'lp_date' => gmdate('Y-m-d H:i:s'),
                    'lp_uid' => $lp['auth_id'],
                    'op' => $op,
                    'replies' => 0,
                    'title' => 'Posts deleted from: '
                        . implode(',', $tids),
                ],
            );
            $tid = $this->database->insert_id();
            $this->database->safeupdate(
                'posts',
                [
                    'newtopic' => 0,
                    'tid' => $tid,
                ],
                'WHERE `id` IN ?',
                explode(',', (string) $this->session->vars['modpids']),
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
                explode(',', (string) $this->session->vars['modpids']),
            );
        }

        foreach ($tids as $tid) {
            // Recount replies.
            $this->database->safespecial(
                <<<'EOT'
                    UPDATE %t
                    SET `replies`=(
                        SELECT COUNT(`id`)
                        FROM %t
                        WHERE `tid`=?
                    )-1
                    WHERE `id`=?
                    EOT
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
        while ($f = $this->database->arow($result)) {
            if (!is_numeric($f['fid'])) {
                continue;
            }

            if ($f['fid'] <= 0) {
                continue;
            }

            $fids[] = (int) $f['fid'];
        }

        $this->database->disposeresult($result);
        $fids = array_unique($fids);
        foreach ($fids as $fid) {
            $this->database->fixForumLastPost($fid);
        }

        // Remove them from the page.
        foreach ($pids as $v) {
            $this->page->JS('removeel', '#pid_' . $v);
        }

        return null;
    }

    public function deletetopics()
    {
        if (!$this->session->vars['modtids']) {
            return $this->page->JS('error', 'No topics to delete');
        }

        $data = [];

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
            explode(',', (string) $this->session->vars['modtids']),
        );
        $delete = [];
        while ($f = $this->database->arow($result)) {
            if (!isset($data[$f['fid']])) {
                $data[$f['fid']] = 0;
            }

            ++$data[$f['fid']];
            if (!$trashcan) {
                continue;
            }

            if ($trashcan !== $f['fid']) {
                continue;
            }

            $delete[] = $f['id'];
        }

        if ($trashcan) {
            $this->database->safeupdate(
                'topics',
                [
                    'fid' => $trashcan,
                ],
                'WHERE `id` IN ?',
                explode(',', (string) $this->session->vars['modtids']),
            );
            $delete = implode(',', $delete);
            $data[$trashcan] = 1;
        } else {
            $delete = $this->session->vars['modtids'];
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

        foreach (array_keys($data) as $k) {
            $this->database->fixForumLastPost($k);
        }

        $this->session->delvar('modtids');
        $this->page->JS('modcontrols_clearbox');
        $this->page->JS('alert', 'topics deleted!');

        return null;
    }

    public function mergetopics(): void
    {
        $page = '';
        $exploded = isset($this->session->vars['modtids'])
            ? explode(',', $this->session->vars['modtids']) : [];
        if (
            isset($this->jax->p['ot'])
            && is_numeric($this->jax->p['ot'])
            && in_array($this->jax->p['ot'], $exploded)
        ) {
            // Move the posts and set all posts to normal (newtopic=0).
            $this->database->safeupdate(
                'posts',
                [
                    'newtopic' => '0',
                    'tid' => $this->jax->p['ot'],
                ],
                'WHERE `tid` IN ?',
                explode(',', (string) $this->session->vars['modtids']),
            );

            // Make the first post in the topic have newtopic=1.
            // Get the op.
            $result = $this->database->safeselect(
                'MIN(`id`)',
                'posts',
                'WHERE `tid`=?',
                $this->database->basicvalue($this->jax->p['ot']),
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
                $this->database->basicvalue($this->jax->p['ot']),
            );
            unset($exploded[array_search($this->jax->p['ot'], $exploded, true)]);
            if ($exploded !== []) {
                $this->database->safedelete(
                    'topics',
                    'WHERE `id` IN ?',
                    $exploded,
                );
            }

            $this->cancel();
            $this->page->location('?act=vt' . $this->jax->p['ot']);
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

        if (isset($this->session->vars['modtids'])) {
            $result = $this->database->safeselect(
                ['id', 'title'],
                'topics',
                'WHERE `id` IN ?',
                explode(',', $this->session->vars['modtids']),
            );
            $titles = [];
            while ($f = $this->database->arow($result)) {
                $titles[$f['id']] = $f['title'];
            }

            foreach ($exploded as $v) {
                if (!isset($titles[$v])) {
                    continue;
                }

                $page .= '<input type="radio" name="ot" value="' . $v . '" /> '
                    . $titles[$v] . '<br>';
            }
        }

        $page .= '<input type="submit" value="Merge" /></form>';
        $page = $this->page->collapsebox('Merging Topics', $page);
        $this->page->JS('update', 'page', $page);
        $this->page->append('page', $page);
    }

    public function banposts(): void
    {
        $this->page->JS('alert', 'under construction');
    }

    public function showmodcp($cppage = ''): void
    {
        global $PERMS;
        if (!$PERMS['can_moderate']) {
            return;
        }

        $page = $this->page->meta('modcp-index', $cppage);
        $page = $this->page->meta('box', ' id="modcp"', 'Mod CP', $page);

        $this->page->append('page', $page);
        $this->page->JS('update', 'page', $page);
    }

    public function editmembers(): void
    {
        global $PERMS;
        if (!$PERMS['can_moderate']) {
            return;
        }

        $e = '';
        $data = [];
        $page = '<form method="post" data-ajax-form="true">'
            . $this->jax->hiddenFormFields(
                [
                    'act' => 'modcontrols',
                    'do' => 'emem',
                    'submit' => 'showform',
                ],
            )
            . 'Member name: <input type="text" title="Enter member name" name="mname" '
            . 'data-autocomplete-action="searchmembers" '
            . 'data-autocomplete-output="#mid" '
            . 'data-autocomplete-indicator="#validname" />'
            . '<span id="validname"></span>
            <input type="hidden" name="mid" id="mid" onchange="this.form.onsubmit();" />
            <input type="submit" type="View member details" value="Go" />
            </form>';
        if (
            isset($this->jax->p['submit'])
            && $this->jax->p['submit'] === 'save'
        ) {
            if (
                trim((string) $this->jax->p['display_name']) === ''
                || trim((string) $this->jax->p['display_name']) === '0'
            ) {
                $page .= $this->page->meta('error', 'Display name is invalid.');
            } else {
                $this->database->safeupdate(
                    'members',
                    [
                        'about' => $this->jax->p['about'],
                        'avatar' => $this->jax->p['avatar'],
                        'display_name' => $this->jax->p['display_name'],
                        'full_name' => $this->jax->p['full_name'],
                        'sig' => $this->jax->p['signature'],
                    ],
                    'WHERE `id`=?',
                    $this->database->basicvalue($this->jax->p['mid']),
                );
                $error = $this->database->error();
                if ($error) {
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
            (isset($this->jax->p['submit'])
            && $this->jax->p['submit'] === 'showform')
            || isset($this->jax->b['mid'])
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
            // Get the member data.
            if (is_numeric($this->jax->b['mid'])) {
                $result = $this->database->safeselect(
                    $memberFields,
                    'members',
                    'WHERE `id`=?',
                    $this->database->basicvalue($this->jax->b['mid']),
                );
                $data = $this->database->arow($result);
                $this->database->disposeresult($result);
            } elseif ($this->jax->p['mname']) {
                $result = $this->database->safeselect(
                    $memberFields,
                    'members',
                    'WHERE `display_name` LIKE ?',
                    $this->database->basicvalue($this->jax->p['mname'] . '%'),
                );
                $data = [];
                while ($f = $this->database->arow($result)) {
                    $data[] = $f;
                }

                if (count($data) > 1) {
                    $e = 'Many users found!';
                } else {
                    $data = array_shift($data);
                }
            } else {
                $e = 'Member name is a required field.';
            }

            if (!$data) {
                $e = 'No members found that matched the criteria.';
            }

            if (
                $this->user->get('group_id') !== 2
                || $data['group_id'] === 2
                && ($this->user->get('id') !== 1
                && $data['id'] !== $this->user->get('id'))
            ) {
                $e = 'You do not have permission to edit this profile.';
            }

            if ($e !== '' && $e !== '0') {
                $page .= $this->page->meta('error', $e);
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
                        'mid' => $data['id'],
                        'submit' => 'save',
                    ],
                );
                $page .= field(
                    'Display Name',
                    'display_name',
                    $data['display_name'],
                )
                    . field('Avatar', 'avatar', $data['avatar'])
                    . field('Full Name', 'full_name', $data['full_name'])
                    . field(
                        'About',
                        'about',
                        $this->textFormatting->blockhtml($data['about']),
                        'textarea',
                    )
                    . field(
                        'Signature',
                        'signature',
                        $this->textFormatting->blockhtml($data['sig']),
                        'textarea',
                    );
                $page .= '</table><input type="submit" value="Save" /></form>';
            }
        }

        $this->showmodcp($page);
    }

    public function iptools(): void
    {
        $page = '';

        $ip = $this->jax->b['ip'] ?? '';
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '';
        }

        $changed = false;

        if (isset($this->jax->p['ban']) && $this->jax->p['ban']) {
            if (!$this->ipAddress->isBanned($ip)) {
                $changed = true;
                $this->jax->ipbancache[] = $ip;
            }
        } elseif (isset($this->jax->p['unban']) && $this->jax->p['unban']) {
            if ($entry = $this->ipAddress->isBanned($ip)) {
                $changed = true;
                unset($this->jax->ipbancache[array_search($entry, $this->jax->ipbancache, true)]);
            }
        }

        if ($changed) {
            $o = fopen(BOARDPATH . '/bannedips.txt', 'w');
            fwrite($o, implode(PHP_EOL, $this->jax->ipbancache));
            fclose($o);
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
                <input type='text' name='ip' title="Enter IP address" value='{$ip}' /></label>
                <input type='submit' value='Submit' title="Search for IP" />
            </form>
            EOT;
        if ($ip) {
            $page .= "<h3>Data for {$ip}:</h3>";

            $hiddenFields = $this->jax->hiddenFormFields(
                [
                    'act' => 'modcontrols',
                    'do' => 'iptools',
                    'ip' => $ip,
                ],
            );
            $banCode = $this->ipAddress->isBanned($ip) ? <<<'EOT'
                <span style="color:#900">
                    banned
                </span>
                <input type="submit" name="unban"
                    onclick="this.form.submitButton=this" value="Unban" />
                EOT : <<<'EOT'
                <span style="color:#090">
                    not banned
                </span>
                <input type="submit" name="ban"
                    onclick="this.form.submitButton=this" value="Ban" />
                EOT;

            $torDate = gmdate('Y-m-d', strtotime('-2 days'));
            $page .= $this->box(
                'Info',
                <<<EOT
                    <form method='post' data-ajax-form='true'>
                        {$hiddenFields}
                        IP ban status: {$banCode}<br>
                    </form>
                    IP Lookup Services: <ul>
                        <li><a href="https://whois.domaintools.com/{$ip}">DomainTools Whois</a></li>
                        <li><a href="https://www.domaintools.com/research/traceroute/?query={$ip}">
                            DomainTools Traceroute
                        </a></li>
                        <li><a href="https://www.ip2location.com/{$ip}">IP2Location Lookup</a></li>
                        <li><a href="https://www.dan.me.uk/torcheck?ip={$ip}">IP2Location Lookup</a></li>
                        <li><a href="https://metrics.torproject.org/exonerator.html?ip={$ip}&timestamp={$torDate}">
                            ExoneraTor Lookup
                        </a></li>
                        <li><a href="https://www.projecthoneypot.org/ip_{$ip}">Project Honeypot Lookup</a></li>
                        <li><a href="https://www.stopforumspam.com/ipcheck/{$ip}">StopForumSpam Lookup</a></li>
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
                $this->database->basicvalue($this->ipAddress->asBinary($ip)),
            );
            while ($f = $this->database->arow($result)) {
                $content[] = $this->page->meta(
                    'user-link',
                    $f['id'],
                    $f['group_id'],
                    $f['display_name'],
                );
            }

            $page .= $this->box('Users with this IP:', implode(', ', $content));

            if ($this->config->getSetting('shoutbox')) {
                $content = '';
                $result = $this->database->safespecial(
                    <<<'EOT'
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
                        EOT
                    ,
                    [
                        'shouts',
                        'members',
                    ],
                    $this->database->basicvalue($this->ipAddress->asBinary($ip)),
                );
                while ($f = $this->database->arow($result)) {
                    $content .= $this->page->meta(
                        'user-link',
                        $f['uid'],
                        $f['group_id'],
                        $f['display_name'],
                    );
                    $content .= ': ' . $f['shout'] . '<br>';
                }

                $page .= $this->box('Last 5 shouts:', $content);
            }

            $content = '';
            $result = $this->database->safeselect(
                ['post'],
                'posts',
                'WHERE `ip`=? ORDER BY `id` DESC LIMIT 5',
                $this->database->basicvalue($this->ipAddress->asBinary($ip)),
            );
            while ($f = $this->database->arow($result)) {
                $content .= "<div class='post'>"
                    . nl2br($this->textFormatting->blockhtml($this->textFormatting->textonly($f['post'])))
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
