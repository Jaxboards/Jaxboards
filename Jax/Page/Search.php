<?php

declare(strict_types=1);

namespace Jax\Page;

use Carbon\Carbon;
use Jax\Database;
use Jax\ForumTree;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
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
use function mb_substr;
use function nl2br;
use function preg_quote;
use function preg_replace;
use function preg_split;
use function str_repeat;
use function trim;

final class Search
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
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {
        $this->template->loadMeta('search');
    }

    public function render(): void
    {
        $this->pageNum = (int) $this->request->asString->both('page') - 1;
        if ($this->pageNum < 0) {
            $this->pageNum = 0;
        }

        $this->perpage = 10;

        if (
            $this->request->both('searchterm') !== null
            || $this->request->asString->both('page') !== null
        ) {
            $this->dosearch();
        } else {
            $this->form();
        }
    }

    private function form(string $pageContents = ''): void
    {
        if ($this->request->isJSUpdate()) {
            return;
        }

        $page = $this->template->meta(
            'search-form',
            $this->textFormatting->blockhtml(
                $this->session->getVar('searcht') ?? '',
            ),
            $this->getForumSelection(),
            $pageContents,
        );
        $this->page->command('update', 'page', $page);
        $this->page->append('PAGE', $page);
    }

    private function getForumSelection(): string
    {
        if (!$this->getSearchableForums()) {
            return '--No forums--';
        }

        $result = $this->database->safeselect(
            ['id', 'title', 'path'],
            'forums',
            'WHERE `id` IN ? ORDER BY `order` ASC,`title` DESC',
            $this->fids,
        );

        $forums = $this->database->arows($result);
        $titles = array_reduce($forums, static function (array $titles, array $forum) {
            $titles[$forum['id']] = $forum['title'];

            return $titles;
        });
        $forumTree = new ForumTree($forums);

        return $this->getForumSelect($forumTree, $titles);
    }

    /**
     * @param array<string> $titles
     */
    private function getForumSelect(
        ForumTree $forumTree,
        array $titles,
    ): string {
        $options = '';
        $forumIterator = $forumTree->getIterator();
        foreach ($forumIterator as $forumId) {
            $text = str_repeat('├─', $forumIterator->getDepth()) . $titles[$forumId];
            $options .= "<option value='{$forumId}'>{$text}</option>";
        }

        return '<select size="15" title="List of forums" multiple="multiple" name="fids">' . $options . '</select>';
    }

    private function dosearch(): void
    {

        if ($this->request->isJSUpdate()) {
            return;
        }

        $searchTerm = $this->request->both('searchterm') ?: $this->session->getVar('searcht');

        if (
            $this->request->both('searchterm') === null
        ) {
            $ids = $this->session->getVar('search');
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
                $postParams[] = 'p.`auth_id`=?';
                $postValues[] = $authorId;
                $topicParams[] = 't.`auth_id`=?';
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

            $sanitizedSearchTerm = $this->database->basicvalue($searchTerm);

            $result = $this->database->safespecial(
                <<<"SQL"
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
            );


            $ids = '';
            while ($id = $this->database->arow($result)) {
                if (!$id['id']) {
                    continue;
                }

                $ids .= $id['id'] . ',';
            }

            $ids = mb_substr($ids, 0, -1);
            $this->session->addVar('search', $ids);
            $this->session->addVar('searcht', $searchTerm);
            $this->pageNum = 0;
        }

        $result = null;
        if ($ids) {
            $numresults = count(explode(',', (string) $ids));
            $idarray = array_slice(
                explode(',', (string) $ids),
                $this->pageNum * $this->perpage,
                $this->perpage,
            );
            $ids = implode(',', $idarray);

            $result = $this->database->safespecial(
                <<<SQL
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
                    SQL,
                ['posts', 'topics'],
                $idarray,
            );
        } else {
            $numresults = 0;
        }

        $page = '';
        $pages = '';

        $terms = [];

        foreach (preg_split('@\W+@', (string) $searchTerm) ?: [] as $v) {
            if (trim($v) === '') {
                continue;
            }

            if (trim($v) === '0') {
                continue;
            }

            $terms[] = preg_quote($v);
        }

        while ($postRow = $this->database->arow($result)) {
            $post = $this->textFormatting->textOnly($postRow['post']);
            $post = $this->textFormatting->blockHtml($post);
            $post = nl2br($post);
            $post = preg_replace(
                '@' . implode('|', $terms) . '@i',
                $this->template->meta('search-highlight', '$0'),
                $post,
            );
            $title = preg_replace(
                '@' . implode('|', $terms) . '@i',
                $this->template->meta('search-highlight', '$0'),
                (string) $postRow['title'],
            );

            $page .= $this->template->meta(
                'search-result',
                $postRow['tid'],
                $title,
                $postRow['id'],
                $post,
            );
        }

        if ($numresults === 0) {
            $error = 'No results found. '
                . 'Try refining your search, or using longer terms.';

            $omitted = [];
            foreach ($terms as $term) {
                if (mb_strlen($term) >= 3) {
                    continue;
                }

                $omitted[] = $term;
            }

            if ($omitted !== []) {
                $error .= '<br /><br />'
                    . 'The following terms were omitted due to length: '
                    . implode(', ', $omitted);
            }

            $page = $this->page->error($error);
        } else {
            $resultsArray = $this->jax->pages(
                (int) ceil($numresults / $this->perpage),
                $this->pageNum,
                10,
            );
            foreach ($resultsArray as $resultArray) {
                $pages .= '<a href="?act=search&page=' . $resultArray . '">' . $resultArray . '</a> ';
            }
        }

        $page = $this->template->meta('box', '', 'Search Results - ' . $pages, $page);

        if (
            $this->request->isJSAccess()
            && !$this->request->isJSDirectLink()
        ) {
            $this->page->command('update', 'searchresults', $page);
        } else {
            $this->form($page);
        }
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
        $result = $this->database->safeselect(
            ['id', 'perms'],
            'forums',
        );
        foreach ($this->database->arows($result) as $forum) {
            $perms = $this->user->getForumPerms($forum['perms']);
            if (!$perms['read']) {
                continue;
            }

            $this->fids[] = $forum['id'];
        }

        return $this->fids;
    }
}
