<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\Jax;
use Jax\Page;
use Jax\Session;
use Jax\TextFormatting;

use function array_key_exists;
use function array_map;
use function array_slice;
use function ceil;
use function count;
use function ctype_digit;
use function explode;
use function gmdate;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function mb_strlen;
use function mb_substr;
use function mktime;
use function nl2br;
use function preg_quote;
use function preg_replace;
use function preg_split;
use function str_repeat;
use function syslog;
use function trim;

use const LOG_EMERG;
use const PHP_EOL;

final class Search
{
    private $pagenum = 0;

    private $fids = [];

    /**
     * @var int
     */
    private $perpage;

    public function __construct(
        private readonly Database $database,
        private readonly Page $page,
        private readonly Jax $jax,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
    ) {
        $this->page->loadmeta('search');
    }

    public function route(): void
    {
        $this->pagenum = $this->jax->b['page'] ?? 0;
        if (!is_numeric($this->pagenum) || $this->pagenum < 0) {
            $this->pagenum = 1;
        }

        $this->perpage = 10;

        if (
            (isset($this->jax->b['searchterm']) && $this->jax->b['searchterm'])
            || (isset($this->jax->b['page']) && $this->jax->b['page'])
        ) {
            $this->dosearch();
        } else {
            $this->form();
        }
    }

    public function form($pageContents = ''): void
    {
        if ($this->page->jsupdate) {
            return;
        }

        $page = $this->page->meta(
            'search-form',
            $this->textFormatting->blockhtml(
                $this->session->vars['searcht'] ?? '',
            ),
            $this->getForumSelection(),
            $pageContents,
        );
        $this->page->JS('update', 'page', $page);
        $this->page->append('page', $page);
    }

    public function getForumSelection(): string
    {
        $this->getSearchableForums();
        $r = '';
        if (!$this->fids) {
            return '--No forums--';
        }

        $result = $this->database->safeselect(
            ['id', 'title', 'path'],
            'forums',
            'WHERE `id` IN ? ORDER BY `order` ASC,`title` DESC',
            $this->fids,
        );

        $tree = [];
        $titles = [];

        while ($f = $this->database->arow($result)) {
            $titles[$f['id']] = $f['title'];
            $path = trim((string) $f['path']) !== ''
                && trim((string) $f['path']) !== '0'
                ? explode(' ', (string) $f['path'])
                : [];
            $t = &$tree;
            foreach ($path as $v) {
                if (!isset($t[$v]) || !is_array($t[$v])) {
                    $t[$v] = [];
                }

                $t = &$t[$v];
            }

            if (isset($t[$f['id']])) {
                continue;
            }

            $t[$f['id']] = true;
        }

        return $r . $this->rtreeselect(
            $tree,
            $titles,
        );
    }

    public function rtreeselect($tree, $titles, $level = 0): string
    {
        $r = '';
        foreach ($tree as $k => $v) {
            if (isset($titles[$k])) {
                $r .= '<option value="' . $k . '">'
                    . str_repeat('+-', $level) . $titles[$k]
                    . '</option>';
            }

            if (!is_array($v)) {
                continue;
            }

            $r .= $this->rtreeselect($v, $titles, $level + 1);
        }

        if (!$level) {
            return '<select size="15" title="List of forums" multiple="multiple" name="fids">' . $r . '</select>';
        }

        return $r;
    }

    public function pdate($a): false|int
    {
        $dayMonthYear = explode('/', (string) $a);
        if (count($dayMonthYear) !== 3) {
            return false;
        }

        for ($x = 0; $x < 3; ++$x) {
            if (!is_numeric($dayMonthYear[$x])) {
                return false;
            }
        }

        $dayMonthYear = array_map('intval', $dayMonthYear);

        if (
            ($dayMonthYear[0] % 2)
            && $dayMonthYear[1] === 31
            || $dayMonthYear[0] === 2
            && (!$dayMonthYear[2] % 4
            && $dayMonthYear[1] > 29
            || $dayMonthYear[2] % 4
            && $dayMonthYear[1] > 28)
        ) {
            return false;
        }

        return mktime(0, 0, 0, $dayMonthYear[0], $dayMonthYear[1], $dayMonthYear[2]);
    }

    public function dosearch(): void
    {

        if ($this->page->jsupdate && empty($this->jax->p)) {
            return;
        }

        $termraw = $this->jax->b['searchterm'] ?? '';

        if (!$termraw && $this->pagenum) {
            $termraw = $this->session->vars['searcht'];
        }

        if (
            empty($this->jax->p)
            && !array_key_exists('searchterm', $this->jax->b)
        ) {
            $ids = $this->session->vars['search'];
        } else {
            $this->getSearchableForums();
            if (isset($this->jax->b['fids']) && $this->jax->b['fids']) {
                $fids = [];
                foreach ($this->jax->b['fids'] as $v) {
                    if (!in_array($v, $this->fids)) {
                        continue;
                    }

                    $fids[] = $v;
                }
            } else {
                $fids = $this->fids;
            }

            $datestart = null;
            if ($this->jax->b['datestart'] ?? 0) {
                $datestart = $this->pdate($this->jax->b['datestart']);
            }

            $dateend = null;
            if ($this->jax->b['dateend'] ?? 0) {
                $dateend = $this->pdate($this->jax->b['dateend']);
            }

            $authorId = null;
            if (
                ($this->jax->b['mid'] ?? 0)
                && ctype_digit((string) $this->jax->b['mid'])
            ) {
                $authorId = (int) $this->jax->b['mid'];
            }

            $postParams = [];
            $postValues = [];

            $topicParams = [];
            $topicValues = [];

            if (!empty($fids)) {
                $postParams[] = 't.`fid` IN ?';
                $postValues[] = $fids;
                $topicParams[] = 't.`fid` IN ?';
                $topicValues[] = $fids;
            }

            if ($authorId) {
                $postParams[] = 'p.`auth_id`=?';
                $postValues[] = $authorId;
                $topicParams[] = 't.`auth_id`=?';
                $topicValues[] = $authorId;
            }

            if ($datestart) {
                $postParams[] = 'p.`date`>?';
                $postValues[] = gmdate('Y-m-d H:i:s', $datestart);
                $topicParams[] = 't.`date`>?';
                $topicValues[] = gmdate('Y-m-d H:i:s', $datestart);
            }

            if ($dateend) {
                $postParams[] = 'p.`date`<?';
                $postValues[] = gmdate('Y-m-d H:i:s', $dateend);
                $topicParams[] = 't.`date`<?';
                $topicValues[] = gmdate('Y-m-d H:i:s', $datestart);
            }

            $postWhere = implode(' ', array_map(static fn($q): string => "AND {$q}", $postParams));
            $topicWhere = implode(' ', array_map(static fn($q): string => "AND {$q}", $topicParams));

            $sanitizedSearchTerm = $this->database->basicvalue($termraw);

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

            if (!$result) {
                syslog(LOG_EMERG, 'ERROR: ' . $this->database->error() . PHP_EOL);
            }

            $ids = '';
            while ($id = $this->database->arow($result)) {
                if (!$id['id']) {
                    continue;
                }

                $ids .= $id['id'] . ',';
            }

            $ids = mb_substr($ids, 0, -1);
            $this->session->addvar('search', $ids);
            $this->session->addvar('searcht', $termraw);
            $this->pagenum = 1;
        }

        $result = null;
        if ($ids) {
            $numresults = count(explode(',', (string) $ids));
            $idarray = array_slice(
                explode(',', (string) $ids),
                ($this->pagenum - 1) * $this->perpage,
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
                    SQL
                ,
                ['posts', 'topics'],
                $idarray,
            );
        } else {
            $numresults = 0;
        }

        $page = '';
        $pages = '';

        $terms = [];

        foreach (preg_split('@\W+@', (string) $termraw) as $v) {
            if (trim($v) === '') {
                continue;
            }

            if (trim($v) === '0') {
                continue;
            }

            $terms[] = preg_quote($v);
        }

        while ($postRow = $this->database->arow($result)) {
            $post = $this->textFormatting->textonly($postRow['post']);
            $post = $this->textFormatting->blockHtml($post);
            $post = nl2br($post);
            $post = preg_replace(
                '@' . implode('|', $terms) . '@i',
                $this->page->meta('search-highlight', '$0'),
                $post,
            );
            $title = preg_replace(
                '@' . implode('|', $terms) . '@i',
                $this->page->meta('search-highlight', '$0'),
                (string) $postRow['title'],
            );

            $page .= $this->page->meta(
                'search-result',
                $postRow['tid'],
                $title,
                $postRow['id'],
                $post,
            );
        }

        if ($numresults === 0) {
            $e = 'No results found. '
                . 'Try refining your search, or using longer terms.';

            $omitted = [];
            foreach ($terms as $v) {
                if (mb_strlen($v) >= 3) {
                    continue;
                }

                $omitted[] = $v;
            }

            if ($omitted !== []) {
                $e .= '<br /><br />'
                    . 'The following terms were omitted due to length: '
                    . implode(', ', $omitted);
            }

            $page = $this->page->error($e);
        } else {
            $resultsArray = $this->jax->pages(
                ceil($numresults / $this->perpage),
                $this->pagenum,
                10,
            );
            foreach ($resultsArray as $x) {
                $pages .= '<a href="?act=search&page=' . $x . '">' . $x . '</a> ';
            }
        }

        $page = $this->page->meta('box', '', 'Search Results - ' . $pages, $page);

        if ($this->page->jsaccess && !$this->page->jsdirectlink) {
            $this->page->JS('update', 'searchresults', $page);
        } else {
            $this->form($page);
        }
    }

    public function getSearchableForums()
    {
        global $USER;

        if ($this->fids) {
            return $this->fids;
        }

        $this->fids = [];
        $result = $this->database->safeselect(
            ['id', 'perms'],
            'forums',
        );
        while ($f = $this->database->arow($result)) {
            $perms = $this->jax->parseperms($f['perms'], $USER ? $USER['group_id'] : 3);
            if (!$perms['read']) {
                continue;
            }

            $this->fids[] = $f['id'];
        }

        return $this->fids;
    }
}
