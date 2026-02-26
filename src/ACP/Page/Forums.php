<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Nav;
use ACP\Page;
use ACP\Page\Forums\RecountStats;
use Jax\Database\Database;
use Jax\ForumTree;
use Jax\Jax;
use Jax\Lodash;
use Jax\Models\Category;
use Jax\Models\Forum;
use Jax\Models\Group;
use Jax\Models\Member;
use Jax\Request;
use Jax\TextFormatting;

use function array_first;
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
        private Database $database,
        private Jax $jax,
        private Nav $nav,
        private Page $page,
        private RecountStats $recountStats,
        private Request $request,
        private TextFormatting $textFormatting,
    ) {}

    public function render(): void
    {
        $this->page->sidebar($this->nav->getMenu('Forums'));

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
     * @param array<string,array<string,array<string,int>|int>|int> $tree  The tree to save
     * @param string                                                $path  The path in the tree
     * @param int                                                   $order where the tree is place n the database
     */
    private function mysqltree(array $tree, string $path = '', int $order = 0): void
    {
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
                $this->database->update(
                    'categories',
                    [
                        'order' => $order,
                    ],
                    Database::WHERE_ID_EQUALS,
                    $cat,
                );

                continue;
            }

            $this->database->update(
                'forums',
                [
                    'category' => $cat,
                    'order' => $order,
                    'path' => preg_replace('/\s+/', ' ', $formattedPath),
                ],
                Database::WHERE_ID_EQUALS,
                $key,
            );
        }
    }

    private function printForumPermsTable(?Forum $forum): string
    {
        $perms = $forum !== null ? $this->jax->parseForumPerms($forum->perms) : null;

        $permsTable = '';
        foreach ($this->fetchAllGroups() as $group) {
            $groupPerms = $perms[$group->id] ?? null;

            $permsTable .= $this->page->render('forums/create-forum-permissions-row.html', [
                'global' => $this->checkbox($group->id, 'global', $groupPerms === null),
                'poll' => $this->checkbox($group->id, 'poll', $groupPerms['poll'] ?? $group->canPoll),
                'read' => $this->checkbox($group->id, 'read', $groupPerms['read'] ?? 1),
                'reply' => $this->checkbox($group->id, 'reply', $groupPerms['reply'] ?? $group->canPost),
                'start' => $this->checkbox($group->id, 'start', $groupPerms['start'] ?? $group->canCreateTopics),
                'title' => $group->title,
                'upload' => $this->checkbox($group->id, 'upload', $groupPerms['upload'] ?? $group->canAttach),
                'view' => $this->checkbox($group->id, 'view', $groupPerms['view'] ?? 1),
            ]);
        }

        return $permsTable;
    }

    /**
     * The typing is getting ridiculous here
     * but it's just a potentially infinitely nested tree with
     * all of the keys being the forum ID.
     *
     * @param array<int,array<int,int>|int> $tree
     * @param array<int,Forum>              $forums
     */
    private function printForumTree(array $tree, array $forums, int $highlight = 0): string
    {
        $html = '';

        foreach ($tree as $forumId => $subforums) {
            $forum = $forums[$forumId];

            if ($forum->mods) {
                $modCount = count(explode(',', $forum->mods));
                $mods = $this->page->render('forums/order-forums-tree-item-mods.html', [
                    'content' => 'moderator' . ($modCount === 1 ? '' : 's'),
                    'mod_count' => $modCount,
                ]);
            } else {
                $mods = '';
            }

            $html .= $this->page->render('forums/order-forums-tree-item.html', [
                'class' => implode(' ', [
                    'nofirstlevel',
                    $highlight && $forumId === $highlight ? 'highlight' : '',
                ]),
                'content' => $subforums !== [] ? $this->printForumTree($subforums, $forums, $highlight) : '',
                'id' => $forumId,
                'mods' => $mods,
                'title' => $forum->title,
                'trashcan' => $forum->trashcan
                    ? $this->page->render('forums/order-forums-tree-item-trashcan.html')
                    : '',
            ]);
        }

        return $this->page->render('forums/order-forums-tree.html', [
            'class' => '',
            'content' => $html,
        ]);
    }

    private function orderForums(int $highlight = 0): void
    {
        $page = '';
        if ($highlight !== 0) {
            $page .= $this->page->success('Forum Created. Now, just place it wherever you like!');
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
            static fn(array $forums): ForumTree => new ForumTree($forums),
            Lodash::groupBy($forums, static fn($forum): int => $forum->category ?? 0),
        );

        $categories = $this->fetchAllCategories();

        $treeHTML = '';
        foreach ($categories as $categoryId => $category) {
            $treeHTML .= $this->page->render('forums/order-forums-tree-item.html', [
                'class' => 'parentlock',
                'content' => array_key_exists($categoryId, $forumsByCategory)
                    ? $this->printForumTree($forumsByCategory[$categoryId]->getTree(), $forums, $highlight)
                    : '',
                'id' => "c_{$categoryId}",
                'title' => $category->title,
                'trashcan' => '',
                'mods' => '',
            ]);
        }

        $page .= $this->page->render('forums/order-forums-tree.html', [
            'class' => 'tree',
            'content' => $treeHTML,
        ]);

        $page .= $this->page->render('forums/order-forums.html');
        $this->page->addContentBox('Forums', $page);
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
        $forum = $fid !== 0 ? $this->fetchAllForums()[$fid] : null;

        $error = null;

        if ($this->request->post('tree') !== null) {
            $this->orderForums();

            $page .= $this->page->success('Forum created.');
        }

        // Remove mod from forum.
        $rmod = (int) $this->request->asString->both('rmod');
        if ($rmod && $forum) {
            $exploded = explode(',', $forum->mods);
            unset($exploded[array_search($rmod, $exploded, true)]);

            $forum->mods = implode(',', $exploded);
            $forum->update();

            $this->updatePerForumModFlag();
            $this->page->location('?act=Forums&do=edit&edit=' . $fid);

            return;
        }

        if ($this->request->post('submit') !== null) {
            $forum = $this->applyFormData($forum);

            // Add per-forum moderator.
            $modId = (int) $this->request->asString->post('modid');
            if ($modId !== 0) {
                $member = Member::selectOne($modId);
                if ($member !== null) {
                    $mods = $forum->mods !== '' ? explode(',', $forum->mods) : [];
                    if (!in_array($modId, $mods)) {
                        $forum->mods = $forum->mods !== '' ? $forum->mods . ',' . $modId : (string) $modId;
                    }
                } else {
                    $error = "You tried to add a moderator that doesn't exist!";
                }
            }

            $error ??= $this->upsertForum($forum);
            if ($error !== null) {
                $page .= $this->page->error($error);
            } else {
                if ($modId !== 0) {
                    $this->updatePerForumModFlag();
                }

                $page .= $this->page->success('Forum saved.');
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
            $subforumOptions .= $this->page->render('select-option.html', [
                'label' => $label,
                'selected' => $value === $forum?->showSubForums ? 'selected="selected"' : '',
                'value' => $value,
            ]);
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
            $orderByOptions .= $this->page->render('select-option.html', [
                'label' => $label,
                'selected' => $forum && $value === $forum->orderby ? 'selected="selected"' : '',
                'value' => $value,
            ]);
        }

        $page .= $this->page->render('forums/create-forum.html', [
            'description' => $forum ? $this->textFormatting->blockhtml($forum->subtitle) : '',
            'count' => $this->page->checked(!$forum?->nocount),
            'order_by_options' => $orderByOptions,
            'redirect_url' => $forum ? $this->textFormatting->blockhtml($forum->redirect) : '',
            'subforum_options' => $subforumOptions,
            'title' => $forum ? $this->textFormatting->blockhtml($forum->title) : '',
            'trashcan' => $this->page->checked((bool) $forum?->trashcan),
        ]);

        if ($forum?->mods) {
            $members = Member::selectMany(Database::WHERE_ID_IN, explode(',', $forum->mods));
            $modList = '';
            foreach ($members as $member) {
                $modList .= $this->page->render('forums/create-forum-moderators-mod.html', [
                    'delete_link' => '?act=Forums&do=edit&edit=' . $fid . '&rmod=' . $member->id,
                    'username' => $member->displayName,
                ]);
            }
        } else {
            $modList = 'No forum-specific moderators added!';
        }

        $moderators = $this->page->render('forums/create-forum-moderators.html', [
            'mod_list' => $modList,
            'showLedBy' => $this->page->checked((bool) $forum?->showLedBy),
        ]);

        $forumperms = $this->page->render('forums/create-forum-permissions.html', [
            'content' => $this->printForumPermsTable($forum),
            'submit' => $forum ? 'Save' : 'Next',
        ]);

        $this->page->addContentBox(
            ($forum ? 'Edit' : 'Create')
            . ' Forum'
            . ($forum ? ' - ' . $this->textFormatting->blockhtml($forum->title) : ''),
            $page,
        );
        $this->page->addContentBox('Moderators', $moderators);
        $this->page->addContentBox('Forum Permissions', $forumperms);
    }

    /**
     * @return string Error on failure, null on success
     */
    private function upsertForum(Forum $forum): ?string
    {
        if ($forum->title === '') {
            return 'Forum title is required';
        }

        // Clear trashcan on other forums.
        if ($forum->trashcan !== 0) {
            $this->database->update('forums', [
                'trashcan' => 0,
            ]);
        }

        $isNewForum = $forum->id === 0;
        $forum->upsert();
        if ($isNewForum) {
            $this->orderForums($forum->id);
        }

        return null;
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

    private function applyFormData(?Forum $forum): Forum
    {
        $sub = (int) $this->request->post('showSubForums');
        $orderby = (int) $this->request->post('orderby');

        $categories = $this->fetchAllCategories();

        if ($forum === null) {
            $forum = new Forum();
            $forum->category = array_first($categories)?->id;
        }

        $forum->nocount = $this->request->asString->post('count') ? 0 : 1;
        $forum->orderby = $orderby > 0 && $orderby <= 5 ? $orderby : 0;
        $forum->perms = $this->serializePermsFromInput();
        $forum->redirect = $this->request->asString->post('redirect') ?? '';
        $forum->showLedBy = $this->request->asString->post('showLedBy') ? 1 : 0;
        $forum->showSubForums = $sub === 1 || $sub === 2 ? $sub : 0;
        $forum->subtitle = $this->request->asString->post('description') ?? '';
        $forum->title = $this->request->asString->post('title') ?? '';
        $forum->trashcan = $this->request->asString->post('trashcan') ? 1 : 0;

        return $forum;
    }

    private function deleteForum(int $forumId): void
    {
        if ($this->request->post('submit') === 'Cancel') {
            $this->page->location('?act=Forums&do=order');

            return;
        }

        if ($this->request->post('submit') !== null) {
            $moveTo = (int) $this->request->asString->post('moveto');
            $this->database->delete('forums', Database::WHERE_ID_EQUALS, $forumId);

            $posts = 0;
            $topics = 0;

            if ($moveTo !== 0) {
                $updateStatement = $this->database->update(
                    'topics',
                    [
                        'fid' => $moveTo,
                    ],
                    ' WHERE `fid`=?',
                    $forumId,
                );
                $topics = $this->database->affectedRows($updateStatement);
            } else {
                $result = $this->database->special(<<<'SQL'
                    DELETE
                    FROM %t
                    WHERE `tid` IN (
                        SELECT `id`
                        FROM %t
                        WHERE `fid`=?
                    )
                    SQL, ['posts', 'topics'], $forumId);

                $posts = $this->database->affectedRows($result);
                $deleteStatement = $this->database->delete('topics', 'WHERE `fid`=?', $forumId);
                $topics = $this->database->affectedRows($deleteStatement);
            }

            $page = match (true) {
                $topics > 0 => ($moveTo !== 0 ? 'Moved' : 'Deleted')
                    . " {$topics} topics"
                    . ($posts !== 0 ? " and {$posts} posts" : ''),
                default => 'This forum was empty, so no topics were moved.',
            };

            $this->page->addContentBox(
                'Forum Deletion',
                $this->page->success($this->page->render('forums/delete-forum-deleted.html', [
                    'content' => $page,
                ])),
            );

            return;
        }

        $forumOptions = '';
        $forums = $this->fetchAllForums();
        foreach ($forums as $forum) {
            $forumOptions .= $this->page->render('select-option.html', [
                'label' => $forum->title,
                'selected' => '',
                'value' => $forum->id,
            ]);
        }

        if (!array_key_exists($forumId, $forums)) {
            $this->page->addContentBox('Deleting Forum: ' . $forumId, $this->page->error("Forum doesn't exist."));

            return;
        }

        $this->page->addContentBox(
            'Deleting Forum: ' . $forums[$forumId]->title,
            $this->page->render('forums/delete-forum.html', [
                'forum_options' => $forumOptions,
            ]),
        );
    }

    private function upsertCategory(Category $category): ?string
    {
        $categoryName = $this->request->asString->post('cat_name');

        if ($categoryName === null || trim($categoryName) === '') {
            return 'All fields required';
        }

        $category->title = $categoryName;
        $category->upsert();

        return null;
    }

    private function createCategory(?int $cid = null): void
    {
        $page = '';
        $cid = $cid ?: (int) $this->request->asString->post('category');

        $category = $cid !== 0 ? Category::selectOne($cid) : null;
        $category ??= new Category();

        if ($this->request->post('submit') !== null) {
            $error = $this->upsertCategory($category);
            $page .= $error ? $this->page->error($error) : $this->page->success('Category saved');
        }

        $this->page->addContentBox(
            ($category->id !== 0 ? 'Edit' : 'Create') . ' Category',
            $page
                . $this->page->render('forums/create-category.html', [
                    'id' => (string) $category->id,
                    'submit' => $category->id !== 0 ? 'Edit' : 'Create',
                    'title' => $this->textFormatting->blockhtml($category->title),
                ]),
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

        $moveTo = (int) $this->request->asString->post('moveto');
        if ($error === null && $this->request->post('submit') !== null) {
            if (!array_key_exists($moveTo, $categories)) {
                $error = 'Invalid category to move forums to.';
            } else {
                $this->database->update(
                    'forums',
                    [
                        'category' => $moveTo,
                    ],
                    'WHERE `category`=?',
                    $catId,
                );

                $categories[$catId]->delete();

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
                $categoryOptions .= $this->page->render('select-option.html', [
                    'label' => $category->title,
                    'selected' => '',
                    'value' => '' . $categoryId,
                ]);
            }

            $page .= $this->page->render('forums/delete-category.html', [
                'category_options' => $categoryOptions,
            ]);
        }

        $this->page->addContentBox('Category Deletion', $page);
    }

    /**
     * @return array<Category>
     */
    private function fetchAllCategories(): array
    {
        $categories = Category::selectMany('ORDER BY `order`,`id` ASC');

        return Lodash::keyBy($categories, static fn($category): int => $category->id);
    }

    /**
     * @return array<Forum>
     */
    private function fetchAllForums(): array
    {
        $forums = Forum::selectMany('ORDER BY `order`,`title`');

        return Lodash::keyBy($forums, static fn($forum): int => $forum->id);
    }

    /**
     * @return array<Group>
     */
    private function fetchAllGroups(): array
    {
        return Lodash::keyBy(Group::selectMany(), static fn($group): int => $group->id);
    }

    /*
     * This function updates all of the user->mod flags
     * that specify whether or not a user is a per-forum mod
     * based on the comma delimited list of mods for each forum.
     */
    private function updatePerForumModFlag(): void
    {
        $this->database->update('members', [
            'mod' => 0,
        ]);

        $mods = [];
        foreach ($this->fetchAllForums() as $forum) {
            foreach (explode(',', $forum->mods) as $modId) {
                if ($modId === '') {
                    continue;
                }

                $mods[$modId] = 1;
            }
        }

        if ($mods === []) {
            return;
        }

        $this->database->update(
            'members',
            [
                'mod' => 1,
            ],
            Database::WHERE_ID_IN,
            array_keys($mods),
        );
    }

    private function checkbox(int|string $checkId, string $name, bool|int $checked): string
    {
        return $this->page->render('forums/create-forum-permissions-row-checkbox.html', [
            'checked' => $this->page->checked((bool) $checked),
            'global' => $name === 'global' ? 'onchange="globaltoggle(this.parentNode.parentNode,this.checked);"' : '',
            'id' => $checkId,
            'name' => $name,
        ]);
    }
}
