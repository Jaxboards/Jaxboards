<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use ACP\Page\Forums\RecountStats;
use Jax\Database;
use Jax\ForumTree;
use Jax\Jax;
use Jax\Request;
use Jax\TextFormatting;

use function _\groupBy;
use function _\keyBy;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_search;
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
                default => null,
            },
            'delete' => match (true) {
                is_numeric($delete) => $this->deleteForum((int) $delete),
                $categoryDelete !== null => $this->deleteCategory($categoryDelete),
                default => null,
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
        array $tree,
        string $path = '',
        int $order = 0,
    ): void {
        foreach ($tree as $key => $value) {
            $key = mb_substr($key, 1);
            ++$order;
            $childPath = $path . $key . ' ';
            sscanf($childPath, 'c_%d', $cat);
            $children = mb_strstr($path, ' ');
            $formattedPath = $children ? trim($children) : '';
            if (is_array($value)) {
                $this->mysqltree($value, $childPath . ' ', $order);
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

                continue;
            }

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

    /**
     * @param array<string,mixed> $forum
     */
    private function printForumPermsTable(?array $forum): string
    {
        $perms = $forum ? $this->jax->parseForumPerms($forum['perms']) : null;

        $permsTable = '';
        foreach ($this->fetchAllGroups() as $group) {
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

        return $permsTable;
    }

    /**
     * @param array<int,array<int>|int>      $tree
     * @param array<int,array<string,mixed>> $forums
     */
    private function printForumTree(
        array $tree,
        array $forums,
        int $highlight = 0,
    ): string {
        $html = '';

        foreach ($tree as $forumId => $subforums) {
            $forum = $forums[$forumId];

            if ($forum['mods']) {
                $modCount = count(explode(',', (string) $forum['mods']));
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

            $html .= $this->page->parseTemplate(
                'forums/order-forums-tree-item.html',
                [
                    'class' => implode(' ', [
                        'nofirstlevel',
                        $highlight && $forumId === $highlight ? 'highlight' : '',
                    ]),
                    'content' => $subforums !== []
                        ? $this->printForumTree($subforums, $forums, $highlight)
                        : '',
                    'id' => $forumId,
                    'mods' => $mods,
                    'title' => $forum['title'],
                    'trashcan' => $forum['trashcan']
                        ? $this->page->parseTemplate('forums/order-forums-tree-item-trashcan.html')
                        : '',
                ],
            );
        }

        return $this->page->parseTemplate(
            'forums/order-forums-tree.html',
            [
                'class' => '',
                'content' => $html,
            ],
        );
    }

    private function orderForums(int $highlight = 0): void
    {
        $page = '';
        if ($highlight !== 0) {
            $page .= $this->page->success(
                'Forum Created. Now, just place it wherever you like!',
            );
        }

        $tree = $this->request->asString->post('tree');
        if ($tree !== null) {
            $decoded = json_decode($tree, true);
            if ($decoded) {
                $this->mysqltree($decoded);
            }

            if ($this->request->asString->get('do') === 'create') {
                return;
            }

            $page .= $this->page->success('Data Saved');
        }

        $forums = $this->fetchAllForums();
        $forumsByCategory = array_map(
            static fn($forums): ForumTree => new ForumTree($forums),
            groupBy($forums, static fn($forum) => $forum['cat_id']),
        );

        $categories = $this->fetchAllCategories();

        $treeHTML = '';
        foreach ($categories as $categoryId => $category) {
            $treeHTML .= $this->page->parseTemplate(
                'forums/order-forums-tree-item.html',
                [
                    'class' => 'parentlock',
                    'content' => array_key_exists($categoryId, $forumsByCategory)
                        ? $this->printForumTree(
                            $forumsByCategory[$categoryId]->getTree(),
                            $forums,
                            $highlight,
                        )
                        : '',
                    'id' => "c_{$categoryId}",
                    'title' => $category['title'],
                    'trashcan' => '',
                    'mods' => '',
                ],
            );
        }

        $page .= $this->page->parseTemplate(
            'forums/order-forums-tree.html',
            [
                'class' => 'tree',
                'content' => $treeHTML,
            ],
        );

        $page .= $this->page->parseTemplate(
            'forums/order-forums.html',
        );
        $this->page->addContentBox('Forums', $page);
    }

    private function fetchForum(int $forumId): ?array
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
            && $forum && $forum['mods']
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
                'show_ledby' => $forum && $forum['show_ledby']
                    ? 'checked="checked"' : '',
            ],
        );

        $forumperms = $this->page->parseTemplate(
            'forums/create-forum-permissions.html',
            [
                'content' => $this->printForumPermsTable($forum),
                'submit' => $fid !== 0 ? 'Save' : 'Next',
            ],
        );

        $this->page->addContentBox(
            ($fid !== 0 ? 'Edit' : 'Create') . ' Forum'
                . ($fid !== 0 ? ' - ' . $this->textFormatting->blockhtml($forum['title']) : ''),
            $page,
        );
        $this->page->addContentBox('Moderators', $moderators);
        $this->page->addContentBox('Forum Permissions', $forumperms);
    }

    /**
     * @param array<string,mixed> $oldForumData
     * @param array<string,mixed> $write
     *
     * @return string Error on failure, null on success
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
                if (!in_array($this->request->post('modid'), isset($oldForumData['mods']) ? explode(',', (string) $oldForumData['mods']) : [])) {
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

                $this->orderForums((int) $this->database->insertId());

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

    private function serializePermsFromInput(): string
    {
        // First fetch all group IDs
        $groupIds = array_keys($this->fetchAllGroups());

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

    private function getFormData(?array $forum): array
    {
        $sub = (int) $this->request->post('show_sub');
        $orderby = (int) $this->request->post('orderby');

        $categories = $this->fetchAllCategories();

        // This is a weird state where they're trying to add a forum
        // with no categories defined. It needs better handling than this.
        // But is clearly an edge case.
        if ($categories === []) {
            return [];
        }

        return [
            'cat_id' => $forum['cat_id'] ?? null ?: $categories[0]['id'],
            'mods' => $forum['mods'] ?? null,
            'nocount' => $this->request->post('nocount') ? 1 : 0,
            'orderby' => $orderby > 0 && $orderby <= 5 ? $orderby : 0,
            'perms' => $this->serializePermsFromInput(),
            'redirect' => $this->request->post('redirect'),
            'show_ledby' => $this->request->post('show_ledby') ? 1 : 0,
            'show_sub' => $sub === 1 || $sub === 2 ? $sub : 0,
            'subtitle' => $this->request->post('description'),
            'title' => $this->request->post('title'),
            'trashcan' => $this->request->post('trashcan') ? 1 : 0,
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
                $updateStatement = $this->database->safeupdate(
                    'topics',
                    [
                        'fid' => $this->request->post('moveto'),
                    ],
                    ' WHERE `fid`=?',
                    $this->database->basicvalue($forumId),
                );
                $topics = $this->database->affectedRows($updateStatement);
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
                        SQL,
                    ['posts', 'topics'],
                    $this->database->basicvalue($forumId),
                );

                $posts = $this->database->affectedRows($result);
                $deleteStatement = $this->database->safedelete(
                    'topics',
                    'WHERE `fid`=?',
                    $this->database->basicvalue($forumId),
                );
                $topics = $this->database->affectedRows($deleteStatement);
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

        $forumOptions = '';
        $forums = $this->fetchAllForums();
        foreach ($forums as $forum) {
            $forumOptions .= $this->page->parseTemplate(
                'select-option.html',
                [
                    'label' => $forum['title'],
                    'selected' => '',
                    'value' => $forum['id'],
                ],
            );
        }

        $forum = $forums[$forumId];

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
                    'forum_options' => $forumOptions,
                ],
            ),
        );
    }

    private function createCategory(?int $cid = null): void
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

        $categoryName = $this->request->asString->post('cat_name');
        if ($this->request->post('submit') !== null) {
            if (
                $categoryName === null || trim($categoryName) === ''
            ) {
                $page .= $this->page->error('All fields required');
            } else {
                $data = ['title' => $categoryName];
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

        $categories = $this->fetchAllCategories();

        if (!array_key_exists($catId, $categories)) {
            $error = "The category you're trying to delete does not exist.";
        }

        $moveTo = (int) $this->request->post('moveto');
        if (
            $error === null
            && $this->request->post('submit') !== null
        ) {
            if (!isset($categories[$moveTo])) {
                $error = 'Invalid category to move forums to.';
            } else {
                $this->database->safeupdate(
                    'forums',
                    [
                        'cat_id' => $moveTo,
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
            foreach ($categories as $categoryId => $category) {
                $categoryOptions .= $this->page->parseTemplate(
                    'select-option.html',
                    [
                        'label' => $category['title'],
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

    /**
     * @return array<array<string,mixed>>
     */
    private function fetchAllCategories(): array
    {
        $result = $this->database->safeselect(
            [
                'id',
                'title',
                '`order`',
            ],
            'categories',
            'ORDER BY `order`,`id` ASC',
        );

        return keyBy($this->database->arows($result), static fn($category) => $category['id']);
    }

    /**
     * @return array<array<string,mixed>>
     */
    private function fetchAllForums(): array
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
            'ORDER BY `order`,`title`',
        );

        return keyBy($this->database->arows($result), static fn($forum) => $forum['id']);
    }

    /**
     * @return array<array<string,mixed>>
     */
    private function fetchAllGroups(): array
    {
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

        return keyBy($this->database->arows($result), static fn($group) => $group['id']);
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

        $mods = [];
        foreach ($this->fetchAllForums() as $forum) {
            foreach (explode(',', (string) $forum['mods']) as $modId) {
                if ($modId === '') {
                    continue;
                }

                $mods[$modId] = 1;
            }
        }

        $this->database->safeupdate(
            'members',
            [
                'mod' => 1,
            ],
            Database::WHERE_ID_IN,
            array_keys($mods),
        );
    }

    private function checkbox(
        int|string $checkId,
        string $name,
        bool|int $checked,
    ): string {
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
