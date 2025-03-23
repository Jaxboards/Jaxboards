<?php

/**
    Chat box
    better color >_>
    <_<
    we need to be able to search "All" :>
 */
$PAGE->loadmeta('search');

new search();
class search
{
    public $page = '';

    public $pagenum = 0;

    public $fids = [];

    public $perpage;

    public function __construct()
    {
        global $PAGE,$JAX;

        $this->page = '';
        $this->pagenum = isset($JAX->b['page']) ? $JAX->b['page'] : 0;
        if (! is_numeric($this->pagenum) || $this->pagenum < 0) {
            $this->pagenum = 1;
        }

        $this->perpage = 10;

        if (
            (isset($JAX->b['searchterm']) && $JAX->b['searchterm'])
            || (isset($JAX->b['page']) && $JAX->b['page'])
        ) {
            $this->dosearch();
        } else {
            $this->form();
        }
    }

    public function form()
    {
        global $PAGE,$JAX,$SESS;
        if ($PAGE->jsupdate) {
            return;
        }

        $this->page = $PAGE->meta(
            'search-form',
            $JAX->blockhtml(isset($SESS->vars['searcht']) ? $SESS->vars['searcht'] : ''),
            $this->getForumSelection(),
            $this->page
        );
        $PAGE->JS('update', 'page', $this->page);
        $PAGE->append('page', $this->page);
    }

    public function getForumSelection()
    {
        global $DB;
        $this->getSearchableForums();
        $r = '';
        if (! $this->fids) {
            return '--No forums--';
        }
        $result = $DB->safeselect(
            '`id`,`title`,`path`',
            'forums',
            'WHERE `id` IN ? ORDER BY `order` ASC,`title` DESC',
            $this->fids
        );

        $tree = [];
        $titles = [];

        while ($f = $DB->arow($result)) {
            $titles[$f['id']] = $f['title'];
            $path = trim($f['path']) ? explode(' ', $f['path']) : [];
            $t = &$tree;
            foreach ($path as $v) {
                if (! isset($t[$v]) || ! is_array($t[$v])) {
                    $t[$v] = [];
                }
                $t = &$t[$v];
            }
            if (! isset($t[$f['id']])) {
                $t[$f['id']] = true;
            }
        }

        $r .= $this->rtreeselect($tree, $titles);

        return $r;
    }

    public function rtreeselect($tree, $titles, $level = 0)
    {
        $r = '';
        foreach ($tree as $k => $v) {
            if (isset($titles[$k])) {
                $r .= '<option value="'.$k.'">'.
                    str_repeat('+-', $level).$titles[$k].
                    '</option>';
            }
            if (is_array($v)) {
                $r .= $this->rtreeselect($v, $titles, $level + 1);
            }
        }
        if (! $level) {
            $r = '<select size="15" title="List of forums" multiple="multiple" name="fids">'.$r.'</select>';
        }

        return $r;
    }

    public function pdate($a)
    {
        $a = explode('/', $a);
        if (count($a) != 3) {
            return false;
        }
        for ($x = 0; $x < 3; $x++) {
            if (! is_numeric($a[$x])) {
                return false;
            }
        }
        if (
            ($a[0] % 2)
            && $a[1] == 31
            || $a[0] == 2
            && (! $a[2] % 4
            && $a[1] > 29
            || $a[2] % 4
            && $a[1] > 28)
        ) {
            return false;
        }

        return mktime(0, 0, 0, $a[0], $a[1], $a[2]);
    }

    public function dosearch()
    {
        global $JAX,$PAGE,$DB,$SESS;

        if ($PAGE->jsupdate && empty($JAX->p)) {
            return;
        }

        $termraw = $JAX->b['searchterm'];

        if (! $termraw && $this->pagenum) {
            $termraw = $SESS->vars['searcht'];
        }

        if (empty($JAX->p) && ! $JAX->b['searchterm']) {
            $ids = $SESS->vars['search'];
        } else {
            $this->getSearchableForums();
            if (isset($JAX->b['fids']) && $JAX->b['fids']) {
                $fids = [];
                foreach ($JAX->b['fids'] as $v) {
                    if (in_array($v, $this->fids)) {
                        $fids[] = $v;
                    }
                }
            } else {
                $fids = $this->fids;
            }
            if ($JAX->b['datestart']) {
                $datestart = $this->pdate($JAX->b['datestart']);
            }
            if ($JAX->b['dateend']) {
                $dateend = $this->pdate($JAX->b['dateend']);
            }

            /*
                Note bug: should be is_int, not is_numeric,
                because that DB field is an integer, not a float.
            */

            $arguments = [
                <<<'EOT'
SELECT `id`,SUM(`relevance`) AS `relevance`
FROM (
    (
        SELECT p.`id` AS `id`,MATCH(p.`post`) AGAINST(?) AS `relevance`
        FROM %t p
        LEFT JOIN %t t
            ON p.`tid`=t.`id`
        WHERE MATCH(p.`post`) AGAINST(? IN BOOLEAN MODE)
            AND t.`fid` IN ?
EOT
            .(is_int($JAX->b['mid']) ? ' AND p.`auth_id =? ' : '').
                 ((isset($datestart) && $datestart) ? ' AND p.`date`>? ' : '').
                 ((isset($dateend) && $dateend) ? ' AND p.`date`<? ' : '').
            <<<'EOT'
    ORDER BY `relevance` DESC LIMIT 100
    ) UNION (
        SELECT t.`op` AS `op`,MATCH(t.`title`) AGAINST(?) AS `relevance`
        FROM %t t
        WHERE MATCH(`title`) AGAINST(? IN BOOLEAN MODE)
            AND t.`fid` IN ?
EOT
            .(is_int($JAX->b['mid']) ? ' AND t.`auth_id`= ? ' : '').
                 ((isset($datestart) && $datestart) ? ' AND t.`date`>? ' : '').
                 ((isset($dateend) && $dateend) ? ' AND t.`date`<? ' : '').
            <<<'EOT'
    ORDER BY `relevance` DESC LIMIT 100
    )
) dt GROUP BY `id` ORDER BY `relevance` DESC
EOT
                ,
                ['posts', 'topics', 'topics'],
                $DB->basicvalue($termraw),
                $DB->basicvalue($termraw),
                $fids,
            ];

            if (is_int($JAX->b['mid'])) {
                array_push($arguments, (int) $JAX->b['mid']);
            }
            if (isset($datestart) && $datestart) {
                array_push($arguments, date('Y-m-d H:i:s', $datestart));
            }
            if (isset($dateend) && $dateend) {
                array_push($arguments, date('Y-m-d H:i:s', $dateend));
            }

            array_push($arguments, $DB->basicvalue($termraw));
            array_push($arguments, $DB->basicvalue($termraw));
            array_push($arguments, $fids);

            if (is_int($JAX->b['mid'])) {
                array_push($arguments, (int) $JAX->b['mid']);
            }
            if (isset($datestart) && $datestart) {
                array_push($arguments, date('Y-m-d H:i:s', $datestart));
            }
            if (isset($dateend) && $dateend) {
                array_push($arguments, date('Y-m-d H:i:s', $dateend));
            }

            $result = call_user_func_array([$DB, 'safespecial'], $arguments);
            if (! $result) {
                syslog(LOG_EMERG, 'ERROR: '.$DB->error(1).PHP_EOL);
            }

            $ids = '';
            while ($id = $DB->arow($result)) {
                if ($id['id']) {
                    $ids .= $id['id'].',';
                }
            }
            $ids = mb_substr($ids, 0, -1);
            $SESS->addvar('search', $ids);
            $SESS->addvar('searcht', $termraw);
            $this->pagenum = 1;
        }

        $result = null;
        if ($ids) {
            $numresults = count(explode(',', $ids));
            $idarray = array_slice(explode(',', $ids), ($this->pagenum - 1) * $this->perpage, $this->perpage);
            $ids = implode(',', $idarray);

            $result = $DB->safespecial(
                <<<EOT
SELECT p.`id` AS `id`,p.`auth_id` AS `auth_id`,p.`post` AS `post`,
UNIX_TIMESTAMP(p.`date`) AS `date`,p.`showsig` AS `showsig`,
p.`showemotes` AS `showemotes`,p.`tid` AS `tid`,p.`newtopic` AS `newtopic`,
INET6_NTOA(p.`ip`) AS `ip`,UNIX_TIMESTAMP(p.`edit_date`) AS `edit_date`,
p.`editby` AS `editby`,p.`rating` AS `rating`,t.`title` AS `title`
FROM %t p
LEFT JOIN %t t
    ON p.`tid`=t.`id`
WHERE p.`id` IN ?
ORDER BY FIELD(p.`id`,{$ids})
EOT
                ,
                ['posts', 'topics'],
                $idarray
            );
        } else {
            $numresults = 0;
        }

        $page = '';
        $pages = '';

        $terms = [];

        foreach (preg_split('@\\W+@', $termraw) as $v) {
            if (trim($v)) {
                $terms[] = preg_quote($v);
            }
        }

        while ($f = $DB->arow($result)) {
            $post = $f['post'];
            $post = $JAX->textonly($post);
            $post = $JAX->blockhtml($post);
            $post = nl2br($post);
            $post = preg_replace('@'.implode('|', $terms).'@i', $PAGE->meta('search-highlight', '$0'), $post);
            $title = preg_replace(
                '@'.implode('|', $terms).'@i',
                $PAGE->meta('search-highlight', '$0'),
                $f['title']
            );

            $page .= $PAGE->meta('search-result', $f['tid'], $title, $f['id'], $post);
        }

        if (! $numresults) {
            $e = 'No results found. '.
                'Try refining your search, or using longer terms.';

            $omitted = [];
            foreach ($terms as $v) {
                if (mb_strlen($v) < 3) {
                    $omitted[] = $v;
                }
            }
            if (! empty($omitted)) {
                $e .= '<br /><br />'.
                    'The following terms were omitted due to length: '.
                    implode(', ', $omitted);
            }
            $page = $PAGE->error($e);
        } else {
            $resultsArray = $JAX->pages(ceil($numresults / $this->perpage), $this->pagenum, 10);
            foreach ($resultsArray as $x) {
                $pages .= '<a href="?act=search&page='.$x.'">'.$x.'</a> ';
            }
        }

        $page = $PAGE->meta('box', '', 'Search Results - '.$pages, $page);

        if ($PAGE->jsaccess && ! $PAGE->jsdirectlink) {
            $PAGE->JS('update', 'searchresults', $page);
        } else {
            $this->page .= $page;
            $this->form();
        }
    }

    public function getSearchableForums()
    {
        if ($this->fids) {
            return $this->fids;
        }
        $this->fids = [];
        global $DB,$JAX,$USER;
        $result = $DB->safeselect('`id`,`perms`', 'forums');
        while ($f = $DB->arow($result)) {
            $perms = $JAX->parseperms($f['perms'], $USER ? $USER['group_id'] : 3);
            if ($perms['read']) {
                $this->fids[] = $f['id'];
            }
        }

        return $this->fids;
    }
}
