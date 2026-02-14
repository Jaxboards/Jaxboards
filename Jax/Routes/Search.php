<?php

declare(strict_types=1);

namespace Jax\Routes;

use Carbon\Carbon;
use Jax\Database\Database;
use Jax\ForumTree;
use Jax\Interfaces\Route;
use Jax\Jax;
use Jax\Models\Forum;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_filter;
use function array_map;
use function array_reduce;
use function array_slice;
use function ceil;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function mb_strlen;
use function nl2br;
use function preg_quote;
use function preg_replace;
use function preg_split;
use function str_repeat;
use function trim;

final class Search implements Route
{
    private int $pageNum = 0;

    /**
     * @var array<int>
     */
    private array $fids = [];

    private int $perpage;

    public function __construct(
        private readonly Database $database,
        private readonly Page $page,
        private readonly Template $template,
        private readonly Jax $jax,
        private readonly Request $request,
        private readonly Router $router,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {}

    public function route($params): void
    {
        $this->page->setBreadCrumbs([
            $this->router->url('search') => 'Search',
        ]);

        $this->pageNum = (int) $this->request->asString->both('page') - 1;
        if ($this->pageNum < 0) {
            $this->pageNum = 0;
        }

        $this->perpage = 10;

        if ($this->request->both('searchterm') !== null || $this->request->asString->both('page') !== null) {
            $this->doSearch();
        } else {
            $this->form();
        }
    }

    private function form(string $pageContents = ''): void
    {
        if ($this->request->isJSUpdate()) {
            return;
        }

        $page = $this->template->render('search/form', [
            'searchTerm' => $this->session->getVar('searcht') ?? '',
            'forumSelect' => $this->getForumSelection(),
            'searchResults' => $pageContents,
        ]);
        $this->page->command('update', 'page', $page);
        $this->page->append('PAGE', $page);
    }

    private function getForumSelection(): string
    {
        if (!$this->getSearchableForums()) {
            return '--No forums--';
        }

        $forums = Forum::selectMany('WHERE `id` IN ? ORDER BY `order` ASC,`title` DESC', $this->fids);

        $titles = array_reduce(
            $forums,
            static function (array $titles, Forum $forum): array {
                $titles[$forum->id] = $forum->title;

                return $titles;
            },
            [],
        );
        $forumTree = new ForumTree($forums);

        return $this->getForumSelect($forumTree, $titles);
    }

    /**
     * @param array<string> $titles
     */
    private function getForumSelect(ForumTree $forumTree, array $titles): string
    {
        $options = '';
        $generator = $forumTree->getIterator();
        foreach ($generator as $depth => $forumId) {
            $text = str_repeat('├─', $depth) . $titles[$forumId];
            $options .= "<option value='{$forumId}'>{$text}</option>";
        }

        return '<select size="15" title="List of forums" multiple="multiple" name="fids[]">' . $options . '</select>';
    }

    private function doSearch(): void
    {
        if ($this->request->isJSUpdate()) {
            return;
        }

        $searchTerm = $this->request->asString->both('searchterm') ?: (string) $this->session->getVar('searcht');

        $postIDs = '';

        if ($this->request->both('searchterm') === null) {
            $postIDs = (string) $this->session->getVar('search');
        } else {
            $this->getSearchableForums();
            $fidsInput = $this->request->both('fids');
            $fids = is_array($fidsInput)
                ? array_filter($fidsInput, fn($fid): bool => in_array((int) $fid, $this->fids, true))
                : $this->fids;

            $datestart = null;
            if ($this->request->asString->both('datestart')) {
                $datestart = Carbon::parse($this->request->asString->both('datestart'))->getTimestamp();
            }

            $dateend = null;
            if ($this->request->asString->both('dateend')) {
                $dateend = Carbon::parse($this->request->asString->both('dateend'))->getTimestamp();
            }

            $authorId = (int) $this->request->asString->both('mid');

            $postParams = [];
            $postValues = [];

            $topicParams = [];
            $topicValues = [];

            if ($fids !== []) {
                $postParams[] = 't.`fid` IN ?';
                $postValues[] = $fids;
                $topicParams[] = 't.`fid` IN ?';
                $topicValues[] = $fids;
            }

            if ($authorId !== 0) {
                $postParams[] = 'p.`author`=?';
                $postValues[] = $authorId;
                $topicParams[] = 't.`author`=?';
                $topicValues[] = $authorId;
            }

            if ($datestart) {
                $postParams[] = 'p.`date`>?';
                $postValues[] = $this->database->datetime($datestart);
                $topicParams[] = 't.`date`>?';
                $topicValues[] = $this->database->datetime($datestart);
            }

            if ($dateend) {
                $postParams[] = 'p.`date`<?';
                $postValues[] = $this->database->datetime($dateend);
                $topicParams[] = 't.`date`<?';
                $topicValues[] = $this->database->datetime($datestart);
            }

            $postWhere = implode(' ', array_map(static fn($q): string => "AND {$q}", $postParams));
            $topicWhere = implode(' ', array_map(static fn($q): string => "AND {$q}", $topicParams));

            $sanitizedSearchTerm = $searchTerm;

            $searchResults = $this->database->arows($this->database->special(
                <<<SQL
                        SELECT
                            `id`,
                            SUM(`relevance`) AS `relevance`
                        FROM (
                            (
                                SELECT
                                    p.`id` AS `id`,
                                    MATCH(p.`post`) AGAINST(?) AS `relevance`
                                FROM %t p
                                LEFT JOIN %t t
                                    ON p.`tid`=t.`id`
                                WHERE MATCH(p.`post`) AGAINST(? IN BOOLEAN MODE)
                                    {$postWhere}
                                ORDER BY `relevance` DESC LIMIT 100
                            ) UNION (
                                SELECT t.`op` AS `op`,MATCH(t.`title`) AGAINST(?) AS `relevance`
                                FROM %t t
                                WHERE MATCH(`title`) AGAINST(? IN BOOLEAN MODE)
                                    {$topicWhere}
                                ORDER BY `relevance` DESC LIMIT 100
                            )
                        ) dt
                        GROUP BY `id` ORDER BY `relevance` DESC
                    SQL,
                ['posts', 'topics', 'topics'],
                // Posts
                ...[$sanitizedSearchTerm, $sanitizedSearchTerm],
                ...$postValues,
                // Topics
                ...[$sanitizedSearchTerm, $sanitizedSearchTerm],
                ...$topicValues,
            ));

            $postIDs = implode(',', array_filter(
                array_map(static fn(array $searchResult): mixed => $searchResult['id'], $searchResults),
                static fn($id): bool => !empty($id),
            ));

            $this->session->addVar('search', $postIDs);
            $this->session->addVar('searcht', $searchTerm);
            $this->pageNum = 0;
        }

        $terms = [];
        foreach (preg_split('/\W+/', $searchTerm) ?: [] as $v) {
            if (trim($v) === '') {
                continue;
            }

            $terms[] = preg_quote($v);
        }

        $page = $postIDs === ''
            ? $this->noResultsFound($terms)
            : $this->renderSearchResults($terms, explode(',', $postIDs));

        if ($this->request->isJSAccess() && !$this->request->isJSDirectLink()) {
            $this->page->command('update', 'searchresults', $page);
        } else {
            $this->form($page);
        }
    }

    /**
     * @param array<string> $terms
     * @param array<string> $postIDs
     */
    private function renderSearchResults(array $terms, array $postIDs): string
    {
        $numresults = count($postIDs);
        $idarray = array_slice($postIDs, $this->pageNum * $this->perpage, $this->perpage);
        $ids = implode(',', $idarray);

        $result = $this->database->special(<<<SQL
            SELECT
                p.`id` AS `id`,
                p.`tid` AS `tid`,
                p.`post` AS `post`,
                t.`title` AS `title`
            FROM %t p
            LEFT JOIN %t t
                ON p.`tid`=t.`id`
            WHERE p.`id` IN ?
            ORDER BY FIELD(p.`id`,{$ids})
            SQL, ['posts', 'topics'], $idarray);

        $page = '';
        $pages = '';

        foreach ($this->database->arows($result) as $postRow) {
            $title = $this->textFormatting->textOnly($postRow['title']);
            $post = $this->textFormatting->textOnly($postRow['post']);

            $post = nl2br($post, false);
            $termsOr = implode('|', $terms);

            $page .= $this->template->render('search/result', [
                'post' => $postRow,
                'titleHighlighted' => preg_replace(
                    "/{$termsOr}/i",
                    $this->template->render('search/highlight', ['searchTerm' => '$0']),
                    $title,
                ),
                'postHighlighted' => preg_replace(
                    "/{$termsOr}/i",
                    $this->template->render('search/highlight', ['searchTerm' => '$0']),
                    $post,
                ),
            ]);
        }

        $resultsArray = $this->jax->pages((int) ceil($numresults / $this->perpage), $this->pageNum, 10);
        foreach ($resultsArray as $resultArray) {
            $searchURL = $this->router->url('search', ['page' => $resultArray]);
            $pages .= "<a href='{$searchURL}'>{$resultArray}</a> ";
        }

        return $this->template->render('global/box', [
            'title' => 'Search Results - ' . $pages,
            'content' => $page,
        ]);
    }

    /**
     * @param array<string> $terms
     */
    private function noResultsFound(array $terms): string
    {
        $error = 'No results found. Try refining your search, or using longer terms.';

        $omitted = [];
        foreach ($terms as $term) {
            if (mb_strlen($term) >= 3) {
                continue;
            }

            $omitted[] = $term;
        }

        if ($omitted !== []) {
            $error .= 'The following terms were omitted due to length: ' . implode(', ', $omitted);
        }

        return $this->template->render('global/box', [
            'title' => 'Search Results',
            'content' => $this->page->error($error),
        ]);
    }

    /**
     * @return array<int>
     */
    private function getSearchableForums(): array
    {
        if ($this->fids) {
            return $this->fids;
        }

        $this->fids = [];
        $forums = Forum::selectMany();
        foreach ($forums as $forum) {
            $perms = $this->user->getForumPerms($forum->perms);
            if (!$perms['read']) {
                continue;
            }

            $this->fids[] = $forum->id;
        }

        return $this->fids;
    }
}
