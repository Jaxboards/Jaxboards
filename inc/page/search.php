<?php
/* Chat box
better color >_>

<_<
we need to be able to search "All" :>
 */

$PAGE->loadmeta('search');

new search();
class search
{
    public function __construct()
    {
        global $PAGE,$JAX;

        $this->page = '';
        $this->pagenum = $JAX->b['page'];
        if (!is_numeric($this->pagenum) || $this->pagenum < 0) {
            $this->pagenum = 1;
        } else {
            $this->pagenum = $JAX->b['page'];
        }

        $this->perpage = 10;

        if ($JAX->b['searchterm'] || $JAX->b['page']) {
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

        $this->page = $PAGE->meta('search-form', $JAX->blockhtml($SESS->vars['searcht']), $this->getForumSelection(), $this->page);
        $PAGE->JS('update', 'page', $this->page);
        $PAGE->append('page', $this->page);
    }

    public function getForumSelection()
    {
        global $DB;
        $this->getSearchableForums();
        if (!$this->fids) {
            return '--No forums--';
        }
        $result = $DB->safeselect('`id`,`title`,`path`','forums','WHERE id IN ? ORDER BY `order` ASC,`title` DESC',
    $this->fids);

        $tree = array();
        $titles = array();

        while ($f = $DB->row($result)) {
            $titles[$f['id']] = $f['title'];
            $path = trim($f['path']) ? explode(' ', $f['path']) : array();
            $t = &$tree;
            foreach ($path as $v) {
                if (!is_array($t[$v])) {
                    $t[$v] = array();
                }
                $t = &$t[$v];
            }
            if (!$t[$f['id']]) {
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
                $r .= '<option value="'.$k.'">'.str_repeat('+-', $level).$titles[$k].'</option>';
            }
            if (is_array($v)) {
                $r .= $this->rtreeselect($v, $titles, $level + 1);
            }
        }
        if (!$level) {
            $r = '<select size="15" multiple="multiple" name="fids">'.$r.'</select>';
        }

        return $r;
    }

    public function pdate($a)
    {
        $a = explode('/', $a);
        if (3 != count($a)) {
            return false;
        }
        for ($x = 0; $x < 3; ++$x) {
            if (!is_numeric($a[$x])) {
                return false;
            }
        }
        if (($a[0] % 2) && 31 == $data[1] ||
        2 == $data[0] && (!$data[2] % 4 && $data[1] > 29 || $data[2] % 4 && $data[1] > 28)
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

        if (!$termraw && $this->pagenum) {
            $termraw = $SESS->vars['searcht'];
        }

        if (empty($JAX->p) && !$JAX->b['searchterm']) {
            $ids = $SESS->vars['search'];
        } else {
            $this->getSearchableForums();
            if ($JAX->b['fids']) {
                $fids = array();
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

            // $fids=implode(",",$fids);

            /* Note bug: should be is_int, not is_numeric, because that DB field is an integer, not a float. */
            $arguments = array(
                'SELECT id,SUM(relevance) relevance FROM (
    (
     SELECT p.id,MATCH(p.post) AGAINST(?) relevance FROM %t p LEFT JOIN %t t ON p.tid=t.id WHERE MATCH(post) AGAINST(? IN BOOLEAN MODE) AND t.fid IN ? '.

                 (is_int($JAX->b['mid']) ? ' AND p.auth_id= ? ' : '').
                 ($datestart ? ' AND p.date>? ' : '').
                 ($dateend ? ' AND p.date<? ' : '').

                     ' ORDER BY relevance DESC LIMIT 100
    ) UNION (
     SELECT op,MATCH(title) AGAINST(?) relevance FROM %t p WHERE MATCH(title) AGAINST(? IN BOOLEAN MODE) AND fid IN ? '.

                 (is_int($JAX->b['mid']) ? ' AND p.auth_id= ? ' : '').
                 ($datestart ? ' AND p.date>? ' : '').
                 ($dateend ? ' AND p.date<? ' : '').

                 ' ORDER BY relevance DESC LIMIT 100
    )
    ) dt GROUP BY id ORDER BY relevance DESC',
                array('posts', 'topics', 'topics'),
                $DB->basicvalue($termraw),
                $DB->basicvalue($termraw),
                $fids, );

            if (is_int($JAX->b['mid'])) {
                array_push($arguments, (int) $JAX->b['mid']);
            }
            if ($datestart) {
                array_push($arguments, $datestart);
            }
            if ($dateend) {
                array_push($arguments, $dateend);
            }

            array_push($arguments, $DB->basicvalue($termraw));
            array_push($arguments, $DB->basicvalue($termraw));
            array_push($arguments, $fids);

            if (is_int($JAX->b['mid'])) {
                array_push($arguments, (int) $JAX->b['mid']);
            }
            if ($datestart) {
                array_push($arguments, $datestart);
            }
            if ($dateend) {
                array_push($arguments, $dateend);
            }

            // syslog(LOG_EMERG, "ARGS: ".print_r($arguments, true)."\n");
            $result = call_user_func_array(
    array($DB, 'safespecial'),
    $arguments
   );
            // syslog(LOG_EMERG, "RESULT: ".print_r($result, true)."\n");
            if (!$result) {
                syslog(LOG_EMERG, 'ERROR: '.$DB->error(1)."\n");
            }

            // $result = $DB->special(
            // 'SELECT id,SUM(relevance) relevance FROM (
            // (
            // SELECT p.id,MATCH(p.post) AGAINST(%4$s) relevance FROM %t p LEFT JOIN %t t ON p.tid=t.id WHERE MATCH(post) AGAINST(%4$s IN BOOLEAN MODE) AND t.fid IN (%5$s)%6$s%7$s%8$s ORDER BY relevance DESC LIMIT 100
            // ) UNION (
            // SELECT op,MATCH(title) AGAINST(%4$s) relevance FROM %t p WHERE MATCH(title) AGAINST(%4$s IN BOOLEAN MODE) AND fid IN (%5$s)%6$s%7$s%8$s ORDER BY relevance DESC LIMIT 100
            // )
            // ) dt GROUP BY id ORDER BY relevance DESC',"posts","topics","topics",
            // $DB->evalue($termraw),
            // $fids,
            // is_numeric($JAX->b['mid'])?" AND p.auth_id=".$DB->evalue($JAX->b['mid']):"",
            // $datestart?" AND p.date>".$datestart:'',
            // $dateend?" AND p.date<".$dateend:''
            // );

            while ($id = $DB->row($result)) {
                if ($id['id']) {
                    $ids .= $id['id'].',';
                }
            }
            $ids = substr($ids, 0, -1);
            $SESS->addvar('search', $ids);
            $SESS->addvar('searcht', $termraw);
            $this->pagenum = 1;
        }

        $result = null;
        if ($ids) {
            $numresults = count(explode(',', $ids));
            $idarray = array_slice(explode(',', $ids), ($this->pagenum - 1) * $this->perpage, $this->perpage);
            $ids = implode(',', $idarray);

            // $DB->special("SELECT p.*,t.title FROM %t p LEFT JOIN %t t ON p.tid=t.id WHERE p.id IN (".$ids.") ORDER BY FIELD(p.id,$ids)","posts","topics");
            $result = $DB->safespecial("SELECT p.*,t.title FROM %t p LEFT JOIN %t t ON p.tid=t.id WHERE p.id IN ? ORDER BY FIELD(p.id,${ids})",
    array('posts', 'topics'), $idarray);
        } else {
            $numresults = 0;
        }

        $page = '';

        $terms = array();

        foreach (preg_split('@\\W+@', $termraw) as $v) {
            if (trim($v)) {
                $terms[] = preg_quote($v);
            }
        }

        while ($f = $DB->row($result)) {
            $post = $f['post'];
            $post = $JAX->textonly($post);
            $post = $JAX->blockhtml($post);
            $post = nl2br($post);
            $post = preg_replace('@'.implode('|', $terms).'@i', $PAGE->meta('search-highlight', '$0'), $post);
            $title = preg_replace('@'.implode('|', $terms).'@i', $PAGE->meta('search-highlight', '$0'), $f['title']);

            $page .= $PAGE->meta('search-result', $f['tid'], $title, $f['id'], $post);
        }

        if (!$numresults) {
            $e = 'No results found. Try refining your search, or using longer terms.';

            $omitted = array();
            foreach ($terms as $v) {
                if (strlen($v) < 3) {
                    $omitted[] = $v;
                }
            }
            if (!empty($omitted)) {
                $e .= '<br /><br />The following terms were omitted due to length: '.implode(', ', $omitted);
            }
            $page = $PAGE->error($e);
        } else {
            foreach ($JAX->pages(ceil($numresults / $this->perpage), $this->pagenum, 10) as $x) {
                $pages .= '<a href="?act=search&page='.$x.'">'.$x.'</a> ';
            }
        }

        $page = $PAGE->meta('box', '', 'Search Results - '.$pages, $page);

        if ($PAGE->jsaccess && !$PAGE->jsdirectlink) {
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
        $this->fids = array();
        global $DB,$JAX,$USER;
        $result = $DB->safeselect('id,perms', 'forums');
        while ($f = $DB->row($result)) {
            $perms = $JAX->parseperms($f['perms'], $USER ? $USER['group_id'] : 3);
            if ($perms['read']) {
                $this->fids[] = $f['id'];
            }
        }

        return $this->fids;
    }
}
