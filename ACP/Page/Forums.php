<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use ACP\Page\Forums\RecountStats;
use Jax\Database;
use Jax\Jax;
use Jax\Request;
use Jax\TextFormatting;

use function array_key_exists;
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
use function preg_replace;
use function sscanf;
use function str_starts_with;
use function trim;

final readonly class Forums
{
    public function __construct(
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly RecountStats $recountStats,
        private readonly Request $request,
        private readonly TextFormatting $textFormatting,
    ) {}

    public function render(): void
    {
        $this->page->sidebar([
            'create' => 'Create Forum',
            'createc' => 'Create Category',
            'order' => 'Manage',
            'recountstats' => 'Recount Statistics',
        ]);

        $edit = $this->request->both('edit');
        $categoryEdit = match (true) {
            is_string($edit) && str_starts_with($edit, 'c_') => (int) mb_substr($edit, 2),
            default => null,
        };
        $delete = $this->request->both('delete');
        $categoryDelete = match (true) {
            is_string($delete) && str_starts_with($delete, 'c_') => (int) mb_substr($delete, 2),
            default => null,
        };

        match ($this->request->get('do')) {
            'edit' => match (true) {
                is_numeric($edit) => $this->createForum((int) $edit),
                $categoryEdit !== null => $this->createCategory($categoryEdit),
            },
            'delete' => match (true) {
                is_numeric($delete) => $this->deleteForum((int) $delete),
                $categoryDelete !== null => $this->deleteCategory($categoryDelete),
            },
            'order' => $this->orderForums(),
            'create' => $this->createForum(),
            'createc' => $this->createCategory(),
            'recountstats' => $this->recountStats->render(),
            default => $this->orderForums(),
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
                    Database::WHERE_ID_EQUALS,
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
                    Database::WHERE_ID_EQUALS,
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
    ): string {
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

    private function orderForums(int|string $highlight = 0): void
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
                'UNIX_TIMESTAMP(`lp_date`) AS `lp_date`',
                'path',
                'show_sub',
                'redirect',
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

    private function fetchForum($forumId): ?array
    {
        $result = $this->database->safeselect(
            [
                'id',
                'cat_id',
                'title',
                'subtitle',
                'UNIX_TIMESTAMP(`lp_date`) AS `lp_date`',
                'path',
                'show_sub',
                'redirect',
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
            Database::WHERE_ID_EQUALS,
            $this->database->basicvalue($forumId),
        );
        $forum = $this->database->arow($result);
        $this->database->disposeresult($result);

        return $forum;
    }

    /**
     * Create & Edit forum.
     *
     * @param int $fid The forum ID. If set, this edits a forum,
     *                 otherwise it creates one.
     */
    private function createForum(int $fid = 0): void
    {
        $page = '';
        $forumperms = '';
        $forum = $this->fetchForum($fid);
        $error = null;

        if ($this->request->post('tree') !== null) {
            $this->orderForums();

            $page .= $this->page->success('Forum created.');
        }

        // Remove mod from forum.
        if (
            is_numeric($this->request->both('rmod'))
            && $forum['mods']
        ) {
            $exploded = explode(',', (string) $forum['mods']);
            unset($exploded[array_search($this->request->both('rmod'), $exploded, true)]);
            $forum['mods'] = implode(',', $exploded);
            $this->database->safeupdate(
                'forums',
                [
                    'mods' => $forum['mods'],
                ],
                Database::WHERE_ID_EQUALS,
                $this->database->basicvalue($fid),
            );
            $this->updatePerForumModFlag();
            $this->page->location('?act=Forums&edit=' . $fid);
        }

        if ($this->request->post('submit') !== null) {
            $write = $this->getFormData($forum);
            $error = $this->upsertForum($forum, $write);
            if ($error !== null) {
                $page .= $this->page->error($error);
            } else {
                $page .= $this->page->success('Forum saved.');
                $forum = $write;
            }
        }

        $perms = $forum ? $this->jax->parseForumPerms($forum['perms']) : null;

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

        $permsTable = '';
        while ($group = $this->database->arow($result)) {
            $groupPerms = $perms[$group['id']] ?? null;

            $permsTable .= $this->page->parseTemplate(
                'forums/create-forum-permissions-row.html',
                [
                    'global' => $this->checkbox($group['id'], 'global', $groupPerms === null),
                    'poll' => $this->checkbox(
                        $group['id'],
                        'poll',
                        $groupPerms['poll'] ?? $group['can_poll'],
                    ),
                    'read' => $this->checkbox(
                        $group['id'],
                        'read',
                        $groupPerms['read'] ?? 1,
                    ),
                    'reply' => $this->checkbox(
                        $group['id'],
                        'reply',
                        $groupPerms['reply'] ?? $group['can_post'],
                    ),
                    'start' => $this->checkbox(
                        $group['id'],
                        'start',
                        $groupPerms['start'] ?? $group['can_post_topics'],
                    ),
                    'title' => $group['title'],
                    'upload' => $this->checkbox(
                        $group['id'],
                        'upload',
                        $groupPerms['upload'] ?? $group['can_attach'],
                    ),
                    'view' => $this->checkbox(
                        $group['id'],
                        'view',
                        $groupPerms['view'] ?? 1,
                    ),
                ],
            );
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
                    'selected' => isset($forum['show_sub']) && $value === $forum['show_sub'] ? 'selected="selected"' : '',
                    'value' => $value,
                ],
            );
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
                    'selected' => isset($forum['orderby']) && $value === $forum['orderby']
                    ? 'selected="selected"' : '',
                    'value' => $value,
                ],
            );
        }


        $page .= $this->page->parseTemplate(
            'forums/create-forum.html',
            [
                'description' => isset($forum['subtitle']) ? $this->textFormatting->blockhtml($forum['subtitle']) : '',
                'no_count' => isset($forum['nocount']) && $forum['nocount']
                ? '' : ' checked="checked"',
                'order_by_options' => $orderByOptions,
                'redirect_url' => isset($forum['redirect']) ? $this->textFormatting->blockhtml($forum['redirect']) : '',
                'subforum_options' => $subforumOptions,
                'title' => isset($forum['title']) ? $this->textFormatting->blockhtml($forum['title']) : '',
                'trashcan' => isset($forum['trashcan']) && $forum['trashcan']
                ? ' checked="checked"' : '',
            ],
        );

        if (isset($forum['mods']) && $forum['mods']) {
            $result = $this->database->safeselect(
                ['display_name', 'id'],
                'members',
                Database::WHERE_ID_IN,
                explode(',', (string) $forum['mods']),
            );
            $modList = '';
            while ($member = $this->database->arow($result)) {
                $modList .= $this->page->parseTemplate(
                    'forums/create-forum-moderators-mod.html',
                    [
                        'delete_link' => '?act=Forums&edit=' . $fid . '&rmod=' . $member['id'],
                        'username' => $member['display_name'],
                    ],
                );
            }
        } else {
            $modList = 'No forum-specific moderators added!';
        }

        $moderators = $this->page->parseTemplate(
            'forums/create-forum-moderators.html',
            [
                'mod_list' => $modList,
                'show_led_by' => isset($forum['show_ledby']) && $forum['show_ledby']
                     ? 'checked="checked"' : '',
            ],
        );

        $forumperms = $this->page->parseTemplate(
            'forums/create-forum-permissions.html',
            [
                'content' => $permsTable,
                'submit' => $fid ? 'Save' : 'Next',
            ],
        );

        $this->page->addContentBox(
            ($fid ? 'Edit' : 'Create') . ' Forum'
            . ($fid ? ' - ' . $this->textFormatting->blockhtml($forum['title']) : ''),
            $page,
        );
        $this->page->addContentBox('Moderators', $moderators);
        $this->page->addContentBox('Forum Permissions', $forumperms);
    }

    /**
     * @returns string Error on failure, null on success
     */
    private function upsertForum(?array $oldForumData, array $write): ?string
    {
        $error = null;

        // Add per-forum moderator.
        if (is_numeric($this->request->post('modid'))) {
            $result = $this->database->safeselect(
                ['id'],
                'members',
                Database::WHERE_ID_EQUALS,
                $this->database->basicvalue($this->request->post('modid')),
            );
            if ($this->database->arow($result)) {
                if (!in_array($this->request->post('modid'), isset($oldForumData['mods']) ? explode(',', $oldForumData['mods']) : [])) {
                    $write['mods'] = isset($oldForumData['mods'])
                        && $oldForumData['mods']
                        ? $oldForumData['mods'] . ',' . $this->request->post('modid')
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

        if ($error === null) {
            // Clear trashcan on other forums.
            if (
                $write['trashcan']
            ) {
                $this->database->safeupdate(
                    'forums',
                    [
                        'trashcan' => 0,
                    ],
                );
            }

            if (!$oldForumData) {
                $this->database->safeinsert(
                    'forums',
                    $write,
                );

                $this->orderForums($this->database->insertId());

                return null;
            }

            $this->database->safeupdate(
                'forums',
                $write,
                Database::WHERE_ID_EQUALS,
                $oldForumData['id'],
            );
            if ($this->request->post('modid')) {
                $this->updatePerForumModFlag();
            }
        }

        return $error;
    }

    private function serializePermsFromInput()
    {
        $result = $this->database->safeselect(
            ['id'],
            'member_groups',
        );

        // First fetch all group IDs
        $groupIds = array_map(
            fn($group) => $group['id'],
            $this->database->arows($result)
        );
        $this->database->disposeresult($result);

        $groupPerms = [];
        $groupsInput = $this->request->post('groups');
        foreach ($groupIds as $groupId) {
            $perms = $groupsInput[$groupId] ?? [];
            // If the user chose to use global permissions, we don't need to include them
            if (array_key_exists('global', $perms)) {
                continue;
            }

            // This is needed to fully populate every permission
            foreach (Jax::FORUM_PERMS_ORDER as $option) {
                $perms[$option] = array_key_exists($option, $perms);
            }

            $groupPerms[$groupId] = $perms;
        }

        return $this->jax->serializeForumPerms($groupPerms);
    }

    private function getFormData($forum)
    {
        $sub = (int) $this->request->post('show_sub');
        if (is_numeric($this->request->post('orderby'))) {
            $orderby = (int) $this->request->post('orderby');
        }
        $result = $this->database->safeselect(
            ['id'],
            'categories',
        );
        $thisrow = $this->database->arow($result);

        return [
            'cat_id' => $forum['cat_id'] ?? null ?: array_pop($thisrow),
            'mods' => $forum['mods'] ?? null,
            'nocount' => (int) $this->request->post('nocount'),
            'orderby' => $orderby > 0 && $orderby <= 5 ? $orderby : 0,
            'perms' => $this->serializePermsFromInput(),
            'redirect' => $this->request->post('redirect'),
            'show_ledby' => (int) $this->request->post('show_ledby'),
            'show_sub' => $sub === 1 || $sub === 2 ? $sub : 0,
            'subtitle' => $this->request->post('description'),
            'title' => $this->request->post('title'),
            'trashcan' => (int) $this->request->post('trashcan'),
        ];
    }

    private function deleteForum(int $forumId): void
    {
        if (
            $this->request->post('submit') === 'Cancel'
        ) {
            $this->page->location('?act=Forums&do=order');

            return;
        }

        if ($this->request->post('submit') !== null) {
            $this->database->safedelete(
                'forums',
                Database::WHERE_ID_EQUALS,
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

            $page = match (true) {
                $topics > 0 => (
                    $this->request->post('moveto') ? 'Moved' : 'Deleted'
                )
                    . " {$topics} topics"
                    . (
                        isset($posts) && $posts ? " and {$posts} posts" : ''
                    ),
                default => 'This forum was empty, so no topics were moved.',
            };

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
                'id',
                'title',
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
            );
        }

        $forum = $this->fetchForum($forumId);

        if (!$forum) {
            $this->page->addContentBox(
                'Deleting Forum: ' . $forumId,
                $this->page->error("Forum doesn't exist."),
            );

            return;
        }

        $this->page->addContentBox(
            'Deleting Forum: ' . $forum['title'],
            $this->page->parseTemplate(
                'forums/delete-forum.html',
                [
                    'forum_options' => $forums,
                ],
            ),
        );
    }

    private function createCategory($cid = false): void
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
                Database::WHERE_ID_EQUALS,
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
                        Database::WHERE_ID_EQUALS,
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
            $page . $this->page->parseTemplate(
                'forums/create-category.html',
                [
                    'id' => $cdata && isset($cdata['id']) ? $cdata['id'] : 0,
                    'submit' => isset($cdata) && $cdata ? 'Edit' : 'Create',
                    'title' => $categoryTitle,
                ],
            ),
        );
    }

    private function deleteCategory(int $catId): void
    {
        $page = '';
        $error = null;
        $result = $this->database->safeselect(
            ['id', 'title'],
            'categories',
        );
        $categories = [];
        while ($category = $this->database->arow($result)) {
            $categories[$category['id']] = $category['title'];
        }
        $this->database->disposeresult($result);

        if (!array_key_exists($catId, $categories)) {
            $error = "The category you're trying to delete does not exist.";
        }

        if (
            $error === null
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
                    Database::WHERE_ID_EQUALS,
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
                );
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
    private function updatePerForumModFlag(): void
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
            Database::WHERE_ID_IN,
            array_keys($mods),
        );
    }

    private function checkbox($checkId, string $name, $checked): string
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
