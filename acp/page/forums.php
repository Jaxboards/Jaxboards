<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use ACP\Page\Forums\RecountStats;
use Jax\Database;
use Jax\Jax;
use Jax\TextFormatting;
use Jax\User;

use function array_keys;
use function array_pop;
use function array_search;
use function array_unshift;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function mb_strstr;
use function mb_substr;
use function pack;
use function preg_match;
use function preg_replace;
use function sscanf;
use function trim;
use function unpack;

use const PHP_EOL;

/**
 * @psalm-api
 */
final readonly class Forums
{
    public function __construct(
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly RecountStats $recountStats,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {}

    /**
     * Saves the posted tree to mysql.
     *
     * @param array  $tree  The tree to save
     * @param string $path  The path in the tree
     * @param int    $order where the tree is place n the database
     */
    public function mysqltree($tree, $path = '', $order = 0): void
    {
        if (!is_array($tree)) {
            return;
        }

        foreach ($tree as $k => $v) {
            $k = mb_substr($k, 1);
            ++$order;
            $childPath = $path . $k . ' ';
            sscanf($childPath, 'c_%d', $cat);
            $children = mb_strstr($path, ' ');
            $formattedPath = $children ? trim($children) : '';
            if (is_array($v)) {
                self::mysqltree($v, $childPath . ' ', $order);
            }

            if ($k[0] === 'c') {
                $this->database->safeupdate(
                    'categories',
                    [
                        'order' => $order,
                    ],
                    'WHERE `id`=?',
                    $cat,
                );
            } else {
                $this->database->safeupdate(
                    'forums',
                    [
                        'cat_id' => $cat,
                        'order' => $order,
                        'path' => preg_replace(
                            '@\s+@',
                            ' ',
                            $formattedPath,
                        ),
                    ],
                    'WHERE `id`=?',
                    $k,
                );
            }
        }
    }

    public function printtree(
        $tree,
        $data,
        $class = false,
        $highlight = 0,
    ): ?string {
        $html = '';
        if (count($tree) > 0) {
            foreach ($tree as $id => $children) {
                if (!isset($data[$id])) {
                    continue;
                }

                if (!is_array($data[$id])) {
                    continue;
                }

                $classes = [];
                $classes[] = is_string($id) && $id[0] === 'c'
                    ? 'parentlock'
                    : 'nofirstlevel';

                if ($highlight && $id === $highlight) {
                    $classes[] = 'highlight';
                }

                $classes = implode(' ', $classes);
                $trashcan = isset($data[$id]['trashcan']) && $data[$id]['trashcan'] ? $this->page->parseTemplate(
                    'forums/order-forums-tree-item-trashcan.html',
                ) : '';

                if (
                    isset($data[$id]['mods'])
                    && is_array($data[$id]['mods'])
                    && !empty($data[$id]['mods'])
                ) {
                    $modCount = count(explode(',', $data[$id]['mods']));
                    $mods = $this->page->parseTemplate(
                        'forums/order-forums-tree-item-mods.html',
                        [
                            'content' => 'moderator' . ($modCount === 1 ? '' : 's'),
                            'mod_count' => $modCount,
                        ],
                    );
                } else {
                    $mods = '';
                }

                $content = '';
                if (is_array($children)) {
                    $content = '' . $this->printtree(
                        $children,
                        $data,
                        '',
                        $highlight,
                    );
                }

                $title = $data[$id]['title'];
                $html .= $this->page->parseTemplate(
                    'forums/order-forums-tree-item.html',
                    [
                        'class' => $classes,
                        'content' => $content,
                        'id' => $id,
                        'mods' => $mods,
                        'title' => $title,
                        'trashcan' => $trashcan,
                    ],
                );
            }

            return $this->page->parseTemplate(
                'forums/order-forums-tree.html',
                [
                    'class' => $class ?: '',
                    'content' => $html,
                ],
            );
        }

        return '';
    }

    public function render(): void
    {
        $this->page->sidebar([
            'create' => 'Create Forum',
            'createc' => 'Create Category',
            'order' => 'Manage',
            'recountstats' => 'Recount Statistics',
        ]);

        if (isset($this->jax->b['delete']) && $this->jax->b['delete']) {
            if (is_numeric($this->jax->b['delete'])) {
                $this->deleteforum($this->jax->b['delete']);

                return;
            }

            if (preg_match('@c_(\d+)@', (string) $this->jax->b['delete'], $m)) {
                $this->deletecategory($m[1]);

                return;
            }
        } elseif (isset($this->jax->b['edit']) && $this->jax->b['edit']) {
            if (is_numeric($this->jax->b['edit'])) {
                $this->createforum($this->jax->b['edit']);

                return;
            }

            if (preg_match('@c_(\d+)@', (string) $this->jax->b['edit'], $m)) {
                $this->createcategory($m[1]);

                return;
            }
        }

        if (!isset($this->jax->g['do'])) {
            $this->jax->g['do'] = null;
        }

        match ($this->jax->g['do']) {
            'order' => $this->orderforums(),
            'create' => $this->createforum(),
            'createc' => $this->createcategory(),
            'recountstats' => $this->recountStats->showstats(),
            'recountstats2' => $this->recountStats->recountStatistics(),
            default => $this->orderforums(),
        };
    }

    public function orderforums($highlight = 0): void
    {
        $page = '';
        if ($highlight) {
            $page .= $this->page->success(
                'Forum Created. Now, just place it wherever you like!',
            );
        }

        if (isset($this->jax->p['tree']) && $this->jax->p['tree']) {
            $this->jax->p['tree'] = json_decode((string) $this->jax->p['tree'], true);
            $data = self::mysqltree($this->jax->p['tree']);
            if ($this->jax->g['do'] === 'create') {
                return;
            }

            $page .= $this->page->success('Data Saved');
        }

        $forums = [];
        $result = $this->database->safeselect(
            [
                'id',
                'title',
                '`order`',
            ],
            'categories',
            'ORDER BY `order`,`id` ASC',
        );
        while ($f = $this->database->arow($result)) {
            $forums['c_' . $f['id']] = ['title' => $f['title']];
            $cats[] = $f['id'];
        }

        $this->database->disposeresult($result);

        $result = $this->database->safeselect(
            [
                'id',
                'cat_id',
                'title',
                'subtitle',
                'lp_uid',
                'UNIX_TIMESTAMP(`lp_date`) AS `lp_date`',
                'lp_tid',
                'lp_topic',
                'path',
                'show_sub',
                'redirect',
                'topics',
                'posts',
                '`order`',
                'perms',
                'orderby',
                'nocount',
                'redirects',
                'trashcan',
                'mods',
                'show_ledby',
            ],
            'forums',
            'ORDER BY `order`,`title`',
        );
        $tree = [$result];
        while ($f = $this->database->arow($result)) {
            $forums[$f['id']] = [
                'mods' => $f['mods'],
                'title' => $f['title'],
                'trashcan' => $f['trashcan'],
            ];
            $treeparts = explode(' ', (string) $f['path']);
            array_unshift($treeparts, 'c_' . $f['cat_id']);
            $intree = &$tree;
            foreach ($treeparts as $v) {
                if (trim($v) === '') {
                    continue;
                }

                if (trim($v) === '0') {
                    continue;
                }

                if (!isset($intree[$v]) || !is_array($intree[$v])) {
                    $intree[$v] = [];
                }

                $intree = &$intree[$v];
            }

            if (isset($intree[$f['id']]) && $intree[$f['id']]) {
                continue;
            }

            $intree[$f['id']] = true;
        }

        foreach ($cats as $v) {
            $sortedtree['c_' . $v] = $tree['c_' . $v] ?? null;
        }

        $page .= $this->printtree(
            $sortedtree,
            $forums,
            'tree',
            $highlight,
        );
        $page .= $this->page->parseTemplate(
            'forums/order-forums.html',
        );
        $this->page->addContentBox('Forums', $page);
    }

    /**
     * Create & Edit forum.
     *
     * @param int $fid The forum ID. If set, this edits a forum,
     *                 otherwise it creates one.
     */
    public function createforum($fid = 0)
    {
        $page = '';
        $forumperms = '';
        $fdata = [];
        if ($fid) {
            $result = $this->database->safeselect(
                [
                    'id',
                    'cat_id',
                    'title',
                    'subtitle',
                    'lp_uid',
                    'UNIX_TIMESTAMP(`lp_date`) AS `lp_date`',
                    'lp_tid',
                    'lp_topic',
                    'path',
                    'show_sub',
                    'redirect',
                    'topics',
                    'posts',
                    '`order`',
                    'perms',
                    'orderby',
                    'nocount',
                    'redirects',
                    'trashcan',
                    'mods',
                    'show_ledby',
                ],
                'forums',
                'WHERE `id`=?',
                $this->database->basicvalue($fid),
            );
            $fdata = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        if (isset($this->jax->p['tree'])) {
            if ($this->jax->p['tree']) {
                $this->orderforums();
            }

            $page .= $this->page->success('Forum created.');
        }

        // Remove mod from forum.
        if (
            isset($this->jax->b['rmod'])
            && is_numeric($this->jax->b['rmod'])
            && $fdata['mods']
        ) {
            $exploded = explode(',', (string) $fdata['mods']);
            unset($exploded[array_search($this->jax->b['rmod'], $exploded, true)]);
            $fdata['mods'] = implode(',', $exploded);
            $this->database->safeupdate(
                'forums',
                [
                    'mods' => $fdata['mods'],
                ],
                'WHERE `id`=?',
                $this->database->basicvalue($fid),
            );
            $this->updateperforummodflag();
            $this->page->location('?act=forums&edit=' . $fid);
        }

        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            // Saves all of the data
            // really should be its own function, but I don't care.
            $grouppermsa = [];
            $groupperms = '';
            $result = $this->database->safeselect(
                ['id'],
                'member_groups',
            );
            while ($f = $this->database->arow($result)) {
                if (!isset($this->jax->p['groups'][$f['id']])) {
                    $this->jax->p['groups'][$f['id']] = [];
                }

                $options = ['read', 'start', 'reply', 'upload', 'view', 'poll'];
                $v = $this->jax->p['groups'][$f['id']];
                if (isset($v['global']) && $v['global']) {
                    continue;
                }

                foreach ($options as $option) {
                    if (isset($v[$option])) {
                        continue;
                    }

                    $v[$option] = false;
                }

                $grouppermsa[$f['id']]
                    = ($v['read'] ? 8 : 0)
                    + ($v['start'] ? 4 : 0)
                    + ($v['reply'] ? 2 : 0)
                    + ($v['upload'] ? 1 : 0)
                    + ($v['view'] ? 16 : 0)
                    + ($v['poll'] ? 32 : 0);
            }

            foreach ($grouppermsa as $k => $v) {
                $groupperms .= pack('n*', $k, $v);
            }

            $sub = (int) $this->jax->p['show_sub'];
            if (is_numeric($this->jax->p['orderby'])) {
                $orderby = (int) $this->jax->p['orderby'];
            }

            $result = $this->database->safeselect(
                ['id'],
                'categories',
            );
            $thisrow = $this->database->arow($result);
            $write = [
                'cat_id' => $this->jax->pick(
                    $fdata['cat_id'] ?? null,
                    array_pop($thisrow),
                ),
                'mods' => $fdata['mods'] ?? null,
                'nocount' => $this->jax->p['nocount'] ? 0 : 1,
                'orderby' => $orderby > 0 && $orderby <= 5 ? $orderby : 0,
                'perms' => $groupperms,
                'redirect' => $this->jax->p['redirect'],
                'show_ledby' => (int) (isset($this->jax->p['show_ledby']) && $this->jax->p['show_ledby']),
                'show_sub' => $sub === 1 || $sub === 2 ? $sub : 0,
                'subtitle' => $this->jax->p['description'],
                'title' => $this->jax->p['title'],
                'trashcan' => (int) (isset($this->jax->p['trashcan']) && $this->jax->p['trashcan']),
                // Handling done below.
            ];
            $this->database->disposeresult($result);

            $error = null;
            // Add per-forum moderator.
            if (is_numeric($this->jax->p['modid'])) {
                $result = $this->database->safeselect(
                    ['id'],
                    'members',
                    'WHERE `id`=?',
                    $this->database->basicvalue($this->jax->p['modid']),
                );
                if ($this->database->arow($result)) {
                    if (!in_array($this->jax->p['modid'], isset($fdata['mods']) ? explode(',', $fdata['mods']) : [])) {
                        $write['mods'] = isset($fdata['mods'])
                            && $fdata['mods']
                            ? $fdata['mods'] . ',' . $this->jax->p['modid']
                            : $this->jax->p['modid'];
                    }
                } else {
                    $error = "You tried to add a moderator that doesn't exist!";
                }

                $this->database->disposeresult($result);
            }

            if (!$write['title']) {
                $error = 'Forum title is required';
            }

            if ($error !== null) {
                // Clear trashcan on other forums.
                if (
                    $write['trashcan']
                    || (!$write['trashcan']
                    && isset($fdata['trashcan'])
                    && $fdata['trashcan'])
                ) {
                    $this->database->safeupdate(
                        'forums',
                        [
                            'trashcan' => 0,
                        ],
                    );
                }

                if (!$fdata) {
                    $this->database->safeinsert(
                        'forums',
                        $write,
                    );

                    return $this->orderforums($this->database->insertId());
                }

                $this->database->safeupdate(
                    'forums',
                    $write,
                    'WHERE `id`=?',
                    $fid,
                );
                if ($this->jax->p['modid']) {
                    $this->updateperforummodflag();
                }

                $page .= $this->page->success('Data saved.');
            }

            $fdata = $write;
        }

        $perms = [];
        if (isset($fdata['perms']) && $fdata['perms']) {
            $unpack = unpack('n*', (string) $fdata['perms']);
            $counter = count($unpack);
            for ($x = 1; $x < $counter; $x += 2) {
                $perms[$unpack[$x]] = $unpack[$x + 1];
            }
        }

        $result = $this->database->safeselect(
            [
                'can_access_acp',
                'can_add_comments',
                'can_attach',
                'can_delete_comments',
                'can_delete_own_posts',
                'can_delete_own_shouts',
                'can_delete_own_topics',
                'can_delete_shouts',
                'can_edit_posts',
                'can_edit_topics',
                'can_im',
                'can_karma',
                'can_lock_own_topics',
                'can_moderate',
                'can_override_locked_topics',
                'can_pm',
                'can_poll',
                'can_post_topics',
                'can_post',
                'can_shout',
                'can_use_sigs',
                'can_view_board',
                'can_view_fullprofile',
                'can_view_offline_board',
                'can_view_shoutbox',
                'can_view_stats',
                'flood_control',
                'icon',
                'id',
                'legend',
                'title',
            ],
            'member_groups',
        );

        $groupperms = '';
        while ($f = $this->database->arow($result)) {
            $global = !isset($perms[$f['id']]);
            if (!$global) {
                $p = isset($perms[$f['id']])
                    ? $this->user->parseForumPerms($perms[$f['id']])
                    : null;
            }

            $groupperms .= $this->page->parseTemplate(
                'forums/create-forum-permissions-row.html',
                [
                    'global' => $this->checkbox($f['id'], 'global', $global),
                    'poll' => $this->checkbox(
                        $f['id'],
                        'poll',
                        $global ? $f['can_poll'] : $p['poll'],
                    ),
                    'read' => $this->checkbox(
                        $f['id'],
                        'read',
                        $global ? 1 : $p['read'],
                    ),
                    'reply' => $this->checkbox(
                        $f['id'],
                        'reply',
                        $global ? $f['can_post']
                        : $p['reply'],
                    ),
                    'start' => $this->checkbox(
                        $f['id'],
                        'start',
                        $global ? $f['can_post_topics']
                        : $p['start'],
                    ),
                    'title' => $f['title'],
                    'upload' => $this->checkbox(
                        $f['id'],
                        'upload',
                        $global ? $f['can_attach'] : $p['upload'],
                    ),
                    'view' => $this->checkbox(
                        $f['id'],
                        'view',
                        $global ? 1 : $p['view'],
                    ),
                ],
            ) . PHP_EOL;
        }

        if ($error !== null) {
            $page .= $this->page->error($error);
        }

        $subforumOptionsArray = [
            0 => 'Not at all',
            1 => 'One level below',
            2 => 'All subforums',
        ];

        $subforumOptions = '';
        foreach ($subforumOptionsArray as $value => $label) {
            $subforumOptions .= $this->page->parseTemplate(
                'select-option.html',
                [
                    'label' => $label,
                    'selected' => isset($fdata['show_sub']) && $value === $fdata['show_sub'] ? 'selected="selected"' : '',
                    'value' => $value,
                ],
            ) . PHP_EOL;
        }

        $orderByOptionsArray = [
            0 => 'Last Post, Descending',
            1 => 'Last Post, Ascending',
            2 => 'Topic Creation Time, Descending',
            3 => 'Topic Creation Time, Ascending',
            4 => 'Topic Title, Descending',
            5 => 'Topic Title, Ascending',
        ];
        $orderByOptions = '';
        foreach ($orderByOptionsArray as $value => $label) {
            $orderByOptions .= $this->page->parseTemplate(
                'select-option.html',
                [
                    'label' => $label,
                    'selected' => isset($fdata['orderby']) && $value === $fdata['orderby']
                    ? 'selected="selected"' : '',
                    'value' => $value,
                ],
            ) . PHP_EOL;
        }


        $page .= $this->page->parseTemplate(
            'forums/create-forum.html',
            [
                'description' => isset($fdata['subtitle']) ? $this->textFormatting->blockhtml($fdata['subtitle']) : '',
                'no_count' => isset($fdata['nocount']) && $fdata['nocount']
                ? '' : ' checked="checked"',
                'order_by_options' => $orderByOptions,
                'redirect_url' => isset($fdata['redirect']) ? $this->textFormatting->blockhtml($fdata['redirect']) : '',
                'subforum_options' => $subforumOptions,
                'title' => isset($fdata['title']) ? $this->textFormatting->blockhtml($fdata['title']) : '',
                'trashcan' => isset($fdata['trashcan']) && $fdata['trashcan']
                ? ' checked="checked"' : '',
            ],
        ) . PHP_EOL;

        if (isset($fdata['mods']) && $fdata['mods']) {
            $result = $this->database->safeselect(
                ['display_name', 'id'],
                'members',
                'WHERE `id` IN ?',
                explode(',', (string) $fdata['mods']),
            );
            $modList = '';
            while ($member = $this->database->arow($result)) {
                $modList .= $this->page->parseTemplate(
                    'forums/create-forum-moderators-mod.html',
                    [
                        'delete_link' => '?act=forums&edit=' . $fid . '&rmod=' . $member['id'],
                        'username' => $member['display_name'],
                    ],
                ) . PHP_EOL;
            }
        } else {
            $modList = 'No forum-specific moderators added!';
        }

        $moderators = $this->page->parseTemplate(
            'forums/create-forum-moderators.html',
            [
                'mod_list' => $modList,
                'show_led_by' => isset($fdata['show_ledby']) && $fdata['show_ledby']
                     ? 'checked="checked"' : '',
            ],
        );

        $forumperms = $this->page->parseTemplate(
            'forums/create-forum-permissions.html',
            [
                'content' => $groupperms,
                'submit' => $fid ? 'Save' : 'Next',
            ],
        );

        $this->page->addContentBox(
            ($fid ? 'Edit' : 'Create') . ' Forum'
            . ($fid ? ' - ' . $this->textFormatting->blockhtml($fdata['title']) : ''),
            $page,
        );
        $this->page->addContentBox('Moderators', $moderators);
        $this->page->addContentBox('Forum Permissions', $forumperms);
    }

    public function deleteforum($id)
    {
        if (
            isset($this->jax->p['submit'])
            && $this->jax->p['submit'] === 'Cancel'
        ) {
            $this->page->location('?act=forums&do=order');
        } elseif (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            $this->database->safedelete(
                'forums',
                'WHERE `id`=?',
                $this->database->basicvalue($id),
            );
            if ($this->jax->p['moveto']) {
                $this->database->safeupdate(
                    'topics',
                    [
                        'fid' => $this->jax->p['moveto'],
                    ],
                    ' WHERE `fid`=?',
                    $this->database->basicvalue($id),
                );
                $topics = $this->database->affectedRows();
            } else {
                $result = $this->database->safespecial(
                    <<<'SQL'
                        DELETE
                        FROM %t
                        WHERE `tid` IN (
                            SELECT `id`
                            FROM %t
                            WHERE `fid`=?
                        )
                        SQL
                    ,
                    ['posts', 'topics'],
                    $this->database->basicvalue($id),
                );

                $posts = $this->database->affectedRows();
                $this->database->safedelete(
                    'topics',
                    'WHERE `fid`=?',
                    $this->database->basicvalue($id),
                );
                $topics = $this->database->affectedRows();
            }

            $page = '';
            if ($topics > 0) {
                $page .= ($this->jax->p['moveto'] ? 'Moved' : 'Deleted')
                    . " {$topics} topics" . (isset($posts) && $posts
                    ? " and {$posts} posts" : '');
            }

            return $this->page->addContentBox(
                'Forum Deletion',
                $this->page->success(
                    $this->page->parseTemplate(
                        'forums/delete-forum-deleted.html',
                        [
                            'content' => $page,
                        ],
                    ),
                ),
            );
        }

        $result = $this->database->safeselect(
            [
                'cat_id',
                'id',
                'lp_tid',
                'lp_topic',
                'lp_uid',
                'mods',
                'nocount',
                'order',
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
            $this->database->basicvalue($id),
        );
        $fdata = $this->database->arow($result);
        $this->database->disposeresult($result);

        if (!$fdata) {
            return $this->page->addContentBox(
                'Deleting Forum: ' . $id,
                $this->page->error("Forum doesn't exist."),
            );
        }

        $result = $this->database->safeselect(
            [
                'cat_id',
                'id',
                'lp_tid',
                'lp_topic',
                'lp_uid',
                'mods',
                'nocount',
                'order',
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
        );
        $forums = '';
        while ($forum = $this->database->arow($result)) {
            $forums .= $this->page->parseTemplate(
                'select-option.html',
                [
                    'label' => $forum['title'],
                    'selected' => '',
                    'value' => $forum['id'],
                ],
            ) . PHP_EOL;
        }

        $this->page->addContentBox(
            'Deleting Forum: ' . $fdata['title'],
            $this->page->parseTemplate(
                'forums/delete-forum.html',
                [
                    'forum_options' => $forums,
                ],
            ),
        );

        return null;
    }

    public function createcategory($cid = false): void
    {
        $page = '';
        $cdata = [];
        if (!$cid && isset($this->jax->p['cat_id'])) {
            $cid = (int) $this->jax->p['cat_id'];
        }

        if ($cid) {
            $result = $this->database->safeselect(
                ['id', 'title'],
                'categories',
                'WHERE `id`=?',
                $this->database->basicvalue($cid),
            );
            $cdata = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        if (isset($this->jax->p['submit']) && $this->jax->p['submit']) {
            if (
                trim((string) $this->jax->p['cat_name']) === ''
                || trim((string) $this->jax->p['cat_name']) === '0'
            ) {
                $page .= $this->page->error('All fields required');
            } else {
                $data = ['title' => $this->jax->p['cat_name']];
                if (!empty($cdata)) {
                    $this->database->safeupdate(
                        'categories',
                        $data,
                        'WHERE `id`=?',
                        $this->database->basicvalue($cid),
                    );
                    $page .= $this->page->success(
                        'Category edited.',
                    );
                } else {
                    $this->database->safeinsert(
                        'categories',
                        $data,
                    );
                    $page .= $this->page->success(
                        'Category created.',
                    );
                    $data['id'] = (int) $this->database->insertId();
                }

                $cdata = $data;
            }
        }

        $categoryTitle = '';
        if (isset($cdata['title'])) {
            $categoryTitle = $this->textFormatting->blockhtml($cdata['title']);
        }

        $this->page->addContentBox(
            ($cdata ? 'Edit' : 'Create') . ' Category',
            $page . PHP_EOL . $this->page->parseTemplate(
                'forums/create-category.html',
                [
                    'id' => $cdata && isset($cdata['id']) ? $cdata['id'] : 0,
                    'submit' => isset($cdata) && $cdata ? 'Edit' : 'Create',
                    'title' => $categoryTitle,
                ],
            ),
        );
    }

    public function deletecategory($id): void
    {
        $page = '';
        $error = null;
        $result = $this->database->safeselect(
            ['id', 'title'],
            'categories',
        );
        $categories = [];
        $cattitle = false;
        while ($f = $this->database->arow($result)) {
            if ($f['id'] !== $id) {
                $categories[$f['id']] = $f['title'];
            } else {
                $cattitle = $f['title'];
            }
        }

        if ($cattitle === false) {
            $error = "The category you're trying to delete does not exist.";
        }

        if (
            $error !== null
            && isset($this->jax->p['submit'])
            && $this->jax->p['submit']
        ) {
            if (!isset($categories[$this->jax->p['moveto']])) {
                $error = 'Invalid category to move forums to.';
            } else {
                $this->database->safeupdate(
                    'forums',
                    [
                        'cat_id' => $this->jax->p['moveto'],
                    ],
                    'WHERE `cat_id`=?',
                    $this->database->basicvalue($id),
                );
                $this->database->safedelete(
                    'categories',
                    'WHERE `id`=?',
                    $this->database->basicvalue($id),
                );
                $page .= $this->page->success('Category deleted!');
            }
        }

        if ($categories === []) {
            $error = 'You cannot delete the only category you have left.';
        }

        if ($error !== null) {
            $page .= $this->page->error($error);
        } else {
            $categoryOptions = '';
            foreach ($categories as $categoryId => $categoryName) {
                $categoryOptions .= $this->page->parseTemplate(
                    'select-option.html',
                    [
                        'label' => $categoryName,
                        'selected' => '',
                        'value' => '' . $categoryId,
                    ],
                ) . PHP_EOL;
            }

            $page .= $this->page->parseTemplate(
                'forums/delete-category.html',
                [
                    'category_options' => $categoryOptions,
                ],
            );
        }

        $this->page->addContentBox('Category Deletion', $page);
    }

    /**
     * This function updates all of the user->mod flags
     * that specify whether or not a user is a per-forum mod
     * based on the comma delimited list of mods for each forum.
     */
    public function updateperforummodflag(): void
    {
        $this->database->safeupdate(
            'members',
            [
                'mod' => 0,
            ],
        );
        $result = $this->database->safeselect(
            ['mods'],
            'forums',
        );
        // Build an array of mods.
        $mods = [];
        while ($f = $this->database->arow($result)) {
            foreach (explode(',', (string) $f['mods']) as $v) {
                if ($v === '') {
                    continue;
                }

                if ($v === '0') {
                    continue;
                }

                $mods[$v] = 1;
            }
        }

        // Update.
        $this->database->safeupdate(
            'members',
            [
                'mod' => 1,
            ],
            'WHERE `id` IN ?',
            array_keys($mods),
        );
    }

    public function checkbox($id, $name, $checked): ?string
    {
        return $this->page->parseTemplate(
            'forums/create-forum-permissions-row-checkbox.html',
            [
                'checked' => $checked ? 'checked="checked" ' : '',
                'global' => $name === 'global'
                    ? 'onchange="globaltoggle(this.parentNode.parentNode,this.checked);"'
                    : '',
                'id' => $id,
                'name' => $name,
            ],
        );
    }
}
