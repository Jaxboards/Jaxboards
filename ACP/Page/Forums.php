<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use ACP\Page\Forums\RecountStats;
use Jax\Database;
use Jax\Jax;
use Jax\Request;
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

final readonly class Forums
{
    public function __construct(
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly RecountStats $recountStats,
        private readonly Request $request,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {}

    public function render(): void
    {
        $this->page->sidebar([
            'create' => 'Create Forum',
            'createc' => 'Create Category',
            'order' => 'Manage',
            'recountstats' => 'Recount Statistics',
        ]);

        if ($this->request->both('delete')) {
            if (is_numeric($this->request->both('delete'))) {
                $this->deleteforum($this->request->both('delete'));

                return;
            }

            if (preg_match('@c_(\d+)@', (string) $this->request->both('delete'), $match)) {
                $this->deletecategory($match[1]);

                return;
            }
        } elseif ($this->request->both('edit')) {
            if (is_numeric($this->request->both('edit'))) {
                $this->createforum($this->request->both('edit'));

                return;
            }

            if (preg_match('@c_(\d+)@', (string) $this->request->both('edit'), $match)) {
                $this->createcategory($match[1]);

                return;
            }
        }

        match ($this->request->get('do')) {
            'order' => $this->orderforums(),
            'create' => $this->createforum(),
            'createc' => $this->createcategory(),
            'recountstats' => $this->recountStats->showstats(),
            'recountstats2' => $this->recountStats->recountStatistics(),
            default => $this->orderforums(),
        };
    }

    /**
     * Saves the posted tree to mysql.
     *
     * @param array  $tree  The tree to save
     * @param string $path  The path in the tree
     * @param int    $order where the tree is place n the database
     */
    private function mysqltree(
        $tree,
        string $path = '',
        float|int $order = 0,
    ): void {
        if (!is_array($tree)) {
            return;
        }

        foreach ($tree as $key => $value) {
            $key = mb_substr($key, 1);
            ++$order;
            $childPath = $path . $key . ' ';
            sscanf($childPath, 'c_%d', $cat);
            $children = mb_strstr($path, ' ');
            $formattedPath = $children ? trim($children) : '';
            if (is_array($value)) {
                self::mysqltree($value, $childPath . ' ', $order);
            }

            if ($key[0] === 'c') {
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
                    $key,
                );
            }
        }
    }

    private function printtree(
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

    private function orderforums(int|string $highlight = 0): void
    {
        $page = '';
        if ($highlight) {
            $page .= $this->page->success(
                'Forum Created. Now, just place it wherever you like!',
            );
        }

        if ($this->request->post('tree') !== null) {
            self::mysqltree(json_decode((string) $this->request->post('tree'), true));
            if ($this->request->get('do') === 'create') {
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
        while ($category = $this->database->arow($result)) {
            $forums['c_' . $category['id']] = ['title' => $category['title']];
            $cats[] = $category['id'];
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
        while ($forum = $this->database->arow($result)) {
            $forums[$forum['id']] = [
                'mods' => $forum['mods'],
                'title' => $forum['title'],
                'trashcan' => $forum['trashcan'],
            ];
            $treeparts = explode(' ', (string) $forum['path']);
            array_unshift($treeparts, 'c_' . $forum['cat_id']);
            $intree = &$tree;
            foreach ($treeparts as $treePart) {
                if (trim($treePart) === '') {
                    continue;
                }

                if (trim($treePart) === '0') {
                    continue;
                }

                if (
                    !isset($intree[$treePart])
                    || !is_array($intree[$treePart])
                ) {
                    $intree[$treePart] = [];
                }

                $intree = &$intree[$treePart];
            }

            if (isset($intree[$forum['id']]) && $intree[$forum['id']]) {
                continue;
            }

            $intree[$forum['id']] = true;
        }

        foreach ($cats as $category) {
            $sortedtree['c_' . $category] = $tree['c_' . $category] ?? null;
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
    private function createforum($fid = 0): void
    {
        $page = '';
        $forumperms = '';
        $fdata = [];
        $error = null;

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

        if ($this->request->post('tree') !== null) {
            $this->orderforums();

            $page .= $this->page->success('Forum created.');
        }

        // Remove mod from forum.
        if (
            is_numeric($this->request->both('rmod'))
            && $fdata['mods']
        ) {
            $exploded = explode(',', (string) $fdata['mods']);
            unset($exploded[array_search($this->request->both('rmod'), $exploded, true)]);
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
            $this->page->location('?act=Forums&edit=' . $fid);
        }

        if ($this->request->post('submit') !== null) {
            // Saves all of the data
            // really should be its own function, but I don't care.
            $grouppermsa = [];
            $groupperms = '';
            $result = $this->database->safeselect(
                ['id'],
                'member_groups',
            );
            while ($group = $this->database->arow($result)) {
                $groups = $this->request->post('groups') ?? [];

                if (!$groups[$group['id']]) {
                    $groups[$group['id']] = [];
                }

                $options = ['read', 'start', 'reply', 'upload', 'view', 'poll'];
                $groupPermInput = $groups[$group['id']];
                if (
                    isset($groupPermInput['global'])
                    && $groupPermInput['global']
                ) {
                    continue;
                }

                foreach ($options as $option) {
                    if (isset($groupPermInput[$option])) {
                        continue;
                    }

                    $groupPermInput[$option] = false;
                }

                $grouppermsa[$group['id']]
                    = ($groupPermInput['read'] ? 8 : 0)
                    + ($groupPermInput['start'] ? 4 : 0)
                    + ($groupPermInput['reply'] ? 2 : 0)
                    + ($groupPermInput['upload'] ? 1 : 0)
                    + ($groupPermInput['view'] ? 16 : 0)
                    + ($groupPermInput['poll'] ? 32 : 0);
            }

            foreach ($grouppermsa as $permission => $flag) {
                $groupperms .= pack('n*', $permission, $flag);
            }

            $sub = (int) $this->request->post('show_sub');
            if (is_numeric($this->request->post('orderby'))) {
                $orderby = (int) $this->request->post('orderby');
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
                'nocount' => $this->request->post('nocount') ? 0 : 1,
                'orderby' => $orderby > 0 && $orderby <= 5 ? $orderby : 0,
                'perms' => $groupperms,
                'redirect' => $this->request->post('redirect'),
                'show_ledby' => (int) $this->request->post('show_ledby'),
                'show_sub' => $sub === 1 || $sub === 2 ? $sub : 0,
                'subtitle' => $this->request->post('description'),
                'title' => $this->request->post('title'),
                'trashcan' => (int) $this->request->post('trashcan'),
                // Handling done below.
            ];
            $this->database->disposeresult($result);

            // Add per-forum moderator.
            if (is_numeric($this->request->post('modid'))) {
                $result = $this->database->safeselect(
                    ['id'],
                    'members',
                    'WHERE `id`=?',
                    $this->database->basicvalue($this->request->post('modid')),
                );
                if ($this->database->arow($result)) {
                    if (!in_array($this->request->post('modid'), isset($fdata['mods']) ? explode(',', $fdata['mods']) : [])) {
                        $write['mods'] = isset($fdata['mods'])
                            && $fdata['mods']
                            ? $fdata['mods'] . ',' . $this->request->post('modid')
                            : $this->request->post('modid');
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

                    $this->orderforums($this->database->insertId());

                    return;
                }

                $this->database->safeupdate(
                    'forums',
                    $write,
                    'WHERE `id`=?',
                    $fid,
                );
                if ($this->request->post('modid')) {
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
            for ($index = 1; $index < $counter; $index += 2) {
                $perms[$unpack[$index]] = $unpack[$index + 1];
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
        while ($group = $this->database->arow($result)) {
            $global = !isset($perms[$group['id']]);
            if (!$global) {
                $perms = isset($perms[$group['id']])
                    ? $this->user->parseForumPerms($perms[$group['id']])
                    : null;
            }

            $groupperms .= $this->page->parseTemplate(
                'forums/create-forum-permissions-row.html',
                [
                    'global' => $this->checkbox($group['id'], 'global', $global),
                    'poll' => $this->checkbox(
                        $group['id'],
                        'poll',
                        $global ? $group['can_poll'] : $perms['poll'],
                    ),
                    'read' => $this->checkbox(
                        $group['id'],
                        'read',
                        $global ? 1 : $perms['read'],
                    ),
                    'reply' => $this->checkbox(
                        $group['id'],
                        'reply',
                        $global ? $group['can_post']
                        : $perms['reply'],
                    ),
                    'start' => $this->checkbox(
                        $group['id'],
                        'start',
                        $global ? $group['can_post_topics']
                        : $perms['start'],
                    ),
                    'title' => $group['title'],
                    'upload' => $this->checkbox(
                        $group['id'],
                        'upload',
                        $global ? $group['can_attach'] : $perms['upload'],
                    ),
                    'view' => $this->checkbox(
                        $group['id'],
                        'view',
                        $global ? 1 : $perms['view'],
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
                        'delete_link' => '?act=Forums&edit=' . $fid . '&rmod=' . $member['id'],
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

    private function deleteforum(string $forumId): void
    {
        if (
            $this->request->post('submit') === 'Cancel'
        ) {
            $this->page->location('?act=Forums&do=order');
        } elseif ($this->request->post('submit') !== null) {
            $this->database->safedelete(
                'forums',
                'WHERE `id`=?',
                $this->database->basicvalue($forumId),
            );
            if ($this->request->post('moveto') !== null) {
                $this->database->safeupdate(
                    'topics',
                    [
                        'fid' => $this->request->post('moveto'),
                    ],
                    ' WHERE `fid`=?',
                    $this->database->basicvalue($forumId),
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
                    $this->database->basicvalue($forumId),
                );

                $posts = $this->database->affectedRows();
                $this->database->safedelete(
                    'topics',
                    'WHERE `fid`=?',
                    $this->database->basicvalue($forumId),
                );
                $topics = $this->database->affectedRows();
            }

            $page = '';
            if ($topics > 0) {
                $page .= ($this->request->post('moveto') ? 'Moved' : 'Deleted')
                    . " {$topics} topics" . (isset($posts) && $posts
                    ? " and {$posts} posts" : '');
            }

            $this->page->addContentBox(
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

            return;
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
            $this->database->basicvalue($forumId),
        );
        $fdata = $this->database->arow($result);
        $this->database->disposeresult($result);

        if (!$fdata) {
            $this->page->addContentBox(
                'Deleting Forum: ' . $forumId,
                $this->page->error("Forum doesn't exist."),
            );

            return;
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
    }

    private function createcategory($cid = false): void
    {
        $page = '';
        $cdata = [];
        if (!$cid && $this->request->post('cat_id')) {
            $cid = (int) $this->request->post('cat_id');
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

        if ($this->request->post('submit') !== null) {
            if (
                trim($this->request->post('cat_name') ?? '') === ''
            ) {
                $page .= $this->page->error('All fields required');
            } else {
                $data = ['title' => $this->request->post('cat_name')];
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

    private function deletecategory(string $catId): void
    {
        $page = '';
        $error = null;
        $result = $this->database->safeselect(
            ['id', 'title'],
            'categories',
        );
        $categories = [];
        $cattitle = false;
        while ($category = $this->database->arow($result)) {
            if ($category['id'] !== $catId) {
                $categories[$category['id']] = $category['title'];
            } else {
                $cattitle = $category['title'];
            }
        }

        if ($cattitle === false) {
            $error = "The category you're trying to delete does not exist.";
        }

        if (
            $error !== null
            && $this->request->post('submit') !== null
        ) {
            if (!isset($categories[$this->request->post('moveto')])) {
                $error = 'Invalid category to move forums to.';
            } else {
                $this->database->safeupdate(
                    'forums',
                    [
                        'cat_id' => $this->request->post('moveto'),
                    ],
                    'WHERE `cat_id`=?',
                    $this->database->basicvalue($catId),
                );
                $this->database->safedelete(
                    'categories',
                    'WHERE `id`=?',
                    $this->database->basicvalue($catId),
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

    /*
     * This function updates all of the user->mod flags
     * that specify whether or not a user is a per-forum mod
     * based on the comma delimited list of mods for each forum.
     */
    private function updateperforummodflag(): void
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
        while ($forum = $this->database->arow($result)) {
            foreach (explode(',', (string) $forum['mods']) as $modId) {
                if ($modId === '') {
                    continue;
                }

                $mods[$modId] = 1;
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

    private function checkbox($checkId, string $name, $checked): ?string
    {
        return $this->page->parseTemplate(
            'forums/create-forum-permissions-row-checkbox.html',
            [
                'checked' => $checked ? 'checked="checked" ' : '',
                'global' => $name === 'global'
                    ? 'onchange="globaltoggle(this.parentNode.parentNode,this.checked);"'
                    : '',
                'id' => $checkId,
                'name' => $name,
            ],
        );
    }
}
