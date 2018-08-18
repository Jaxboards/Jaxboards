<?php

class SESS
{
    public $data = array();
    public $bots = array('google' => 'Googlebot', 'bingbot' => 'Bing', 'yahoo! slurp' => 'Yahoo', 'mj12bot' => 'MJ12bot', 'baidu' => 'Baidu', 'discobot' => 'DiscoBot');
    public $changedData = array();

    public function __construct($sid = false)
    {
        $this->data = $this->getSess($sid);
        $this->data['vars'] = unserialize($this->data['vars']);
        if (!$this->data['vars']) {
            $this->data['vars'] = array();
        }
    }

    public function getSess($sid = false)
    {
        global $DB,$JAX,$_SESSION;
        $isbot = 0;
        foreach ($this->bots as $k => $v) {
            if (false !== strpos(strtolower($_SERVER['HTTP_USER_AGENT']), $k)) {
                $sid = $v;
                $isbot = 1;
            }
        }
        if ($sid) {
            $result = (!$isbot) ?
                $DB->safeselect('*','session','WHERE id=? AND ip=?;',
                    $DB->basicvalue($sid),
                    $JAX->ip2int()) :
                    $DB->safeselect('*','session','WHERE id=?',
                        $DB->basicvalue($sid));
            $r = $DB->arow($result);
            $DB->disposeresult($result);
        }
        if ($r) {
            return $r;
        }
        if (!$isbot) {
            $sid = base64_encode(openssl_random_pseudo_bytes(128));
        }
        $uid = 0;
        if ($JAX->userData && isset($JAX->userData['id']) && 0 < $JAX->userData['id']) {
            $uid = (int) $JAX->userData['id'];
        }
        if (!$isbot) {
            $_SESSION['sid'] = $sid;
        }
        $sessData = array('id' => $sid, 'uid' => $uid, 'runonce' => '', 'ip' => $JAX->ip2int(), 'useragent' => $_SERVER['HTTP_USER_AGENT'], 'is_bot' => $isbot, 'last_action' => time(), 'last_update' => time());
        if (1 > $uid) {
            unset($sessData['uid']);
        }
        $DB->safeinsert('session', $sessData);

        return $sessData;
    }

    public function __get($a)
    {
        return $this->data[$a];
    }

    public function __set($a, $b)
    {
        if ($this->data[$a] == $b) {
            return;
        }
        $this->changedData[$a] = $b;
        $this->data[$a] = $b;
    }

    public function set($a)
    {
        foreach ($a as $k => $v) {
            $this->__set($k, $v);
        }
    }

    public function addvar($a, $b)
    {
        if ($this->data['vars'][$a] != $b) {
            $this->data['vars'][$a] = $b;
            $this->changedData['vars'] = serialize($this->data['vars']);
        }
    }

    public function delvar($a)
    {
        if ($this->data['vars'][$a]) {
            unset($this->data['vars'][$a]);
            $this->changedData['vars'] = serialize($this->data['vars']);
        }
    }

    public function act($a = false)
    {
        global $JAX;
        //$JAX->setCookie("la",time(),time()+60*60*24*30);
        $this->__set('last_action', time());
        if ($a) {
            $this->__set('location', $a);
        }
    }

    public function erase($a)
    {
        unset($this->changedData[$a]);
    }

    public function clean($uid)
    {
        global $DB,$CFG,$PAGE,$JAX;
        $timeago = time() - $CFG['timetologout'];
        if (!is_int($uid) || 1 > $uid) {
            $uid = null;
        } else {
            $result = $DB->safeselect('max(last_action)','session','WHERE uid=? GROUP BY uid',
    $uid);
            $la = $DB->row($result);
            $DB->disposeresult($result);
            if ($la) {
                $la = $la[0];
            }
            $DB->safedelete('session', 'WHERE uid=? AND last_update<?', $DB->basicvalue($uid), $timeago);
            // delete all expired tokens as well while we're here...
            $DB->safedelete(
                'tokens',
                'WHERE expires<=?',
                $DB->basicvalue(date('Y-m-d H:i:s', time()))
            );
            $this->__set('readtime', $JAX->pick($la, 0));
        }
        $yesterday = mktime(0, 0, 0);
        $query = $DB->safeselect('uid,max(last_action) last_action','session','WHERE last_update<? GROUP BY uid',
    $yesterday);
        while ($f = $DB->row($query)) {
            if ($f['uid']) {
                $DB->safeupdate('members', array('last_visit' => $f['last_action']), 'WHERE id=?', $f['uid']);
            }
        }
        $DB->safespecial('DELETE FROM %t WHERE last_update<? OR (uid IS NULL AND last_update< ?)',
    array('session'),
    $yesterday,
    $timeago);

        return true;
    }

    public function applyChanges()
    {
        global $DB,$PAGE;
        $sd = $this->changedData;
        $id = $this->data['id'];
        $sd['last_update'] = time();
        if ($this->data['is_bot']) {
            $sd['forumsread'] = $sd['topicsread'] = ''; //bots tend to read a lot of shit
        }
        if (!$this->data['last_action']) {
            $sd['last_action'] = time();
        }
        $DB->safeupdate('session', $sd, 'WHERE id=?', $DB->basicvalue($id));
    }

    public function addSessID($html)
    {
        global $JAX;
        if (!empty($JAX->c)) {
            return $html;
        }

        return preg_replace_callback("@href=['\"]?([^'\"]+)['\"]?@", array($this, 'addSessIDCB'), $html);
    }

    public function addSessIDCB($m)
    {
        if ('?' == $m[1][0]) {
            $m[1] .= '&amp;sessid='.$this->data['id'];
        }

        return 'href="'.$m[1].'"';
    }
}
