<?php

class SESS
{
    public $data = array();
    public $userData = array();
    public $bots = array(
        'Googlebot' => 'Google',
        'Bingbot' => 'Bing',
        'DuckDuckBot' => 'DuckDuckGo',
        'Teoma' => 'Ask.com',
        'archive.org_bot' => 'Internet Archive',
	'ia_archiver' => 'Internet Archive Alexa',
        'facebookexternalhit' => 'Facebook',
        'meta-externalagent' => 'Meta', // May be AI-related
        'WhatsApp' => 'WhatsApp',
        'Twitterbot' => 'Twitter',
        'Bytespider' => 'TikTok',
        'Discordbot' => 'Discord',
        'Amazonbot' => 'Amazon',
        'Applebot' => 'Applebot',
        'ClaudeBot' => 'ClaudeBot', // Anthropic AI bot
        'GPTBot' => 'GPTBot', // AI developer
        'OAI-SearchBot' => 'OpenAI', // AI developer
        'PerplexityBot' => 'Perplexity', // AI answers site
	'ImagesiftBot' => 'Imagesift', // Hive image search; may be AI-related
        'MJ12bot' => 'Majestic', // British SEO crawler
        'SemrushBot' => 'Semrush', // Backlink tracking company
        'DotBot' => 'DotBot', // Moz SEO crawler
        'AhrefsBot' => 'Ahrefs', // SEO crawler
        'ChatGLM-Spider' => 'ChatGLM', // SEO crawler
        'linkdexbot' => 'Linkdex', // SEO crawler
        'Barkrowler' => 'Babbar.tech', // SEO graphing services
        'AwarioBot' => 'Awario', // Social media management company
        'Friendly_Crawler' => 'FriendlyCrawler', // Machine learning researcher
        'Baiduspider' => 'Baidu', // Chinese search engine
        'YandexBot' => 'Yandex', // Russian search engine
        'PetalBot' => 'PetalBot', // Chinese search crawler (Huawei)
        'Y!J-WSC' => 'Yahoo Japan',
        'MojeekBot' => 'Mojeek', // British search engine
        'Qwantbot-prod' => 'Qwant', // French search engine
        'Sogou web spider' => 'Sogou', // Chinese search engine
        'SeznamBot' => 'Seznam', // Czech search engine
        'Mail.RU_Bot' => 'Mail.RU',
        'Expanse' => 'Expanse', // Palo Alto Networks security scanning service
	'CensysInspect' => 'CensysInspect', // Security scanner
    );
    public $changedData = array();

    public function __construct($sid = false)
    {
        $this->data = $this->getSess($sid);
        if (!isset($this->data['vars'])) {
            $this->data['vars'] = serialize(array());
        }
        $this->data['vars'] = unserialize($this->data['vars']);
        if (!$this->data['vars']) {
            $this->data['vars'] = array();
        }
    }

    public function getSess($sid = false)
    {
        global $DB,$JAX,$_SESSION;
        $isbot = 0;
        $r = array();
        foreach ($this->bots as $k => $v) {
            if (false != mb_stripos(
                mb_strtolower($_SERVER['HTTP_USER_AGENT']),
                $k
            )
            ) {
                $sid = $v;
                $isbot = 1;
            }
        }
        if ($sid) {
            $result = (!$isbot) ?
                $DB->safeselect(
                    <<<'EOT'
`id`,`uid`,INET6_NTOA(`ip`) as `ip`,`vars`,
UNIX_TIMESTAMP(`last_update`) AS `last_update`,
UNIX_TIMESTAMP(`last_action`) AS `last_action`,`runonce`,`location`,
`users_online_cache`,`is_bot`,`buddy_list_cache`,`location_verbose`,
`useragent`,`forumsread`,`topicsread`,
UNIX_TIMESTAMP(`read_date`) AS `read_date`,`hide`
EOT
                    ,
                    'session',
                    'WHERE `id`=? AND `ip`=INET6_ATON(?)',
                    $DB->basicvalue($sid),
                    $JAX->getIp()
                ) :
                    $DB->safeselect(
                        <<<'EOT'
`id`,`uid`,INET6_NTOA(`ip`) as `ip`,`vars`,
UNIX_TIMESTAMP(`last_update`) AS `last_update`,
UNIX_TIMESTAMP(`last_action`) AS `last_action`,`runonce`,`location`,
`users_online_cache`,`is_bot`,`buddy_list_cache`,`location_verbose`,
`useragent`,`forumsread`,`topicsread`,
UNIX_TIMESTAMP(`read_date`) AS `read_date`,`hide`
EOT
                        ,
                        'session',
                        'WHERE `id`=?',
                        $DB->basicvalue($sid)
                    );
            $r = $DB->arow($result);
            $DB->disposeresult($result);
        }
        if (!empty($r)) {
            return $r;
        }
        if (!$isbot) {
            $sid = base64_encode(openssl_random_pseudo_bytes(128));
        }
        $uid = 0;
        if (!empty($JAX->userData)
            && isset($JAX->userData['id'])
            && 0 < $JAX->userData['id']
        ) {
            $uid = (int) $JAX->userData['id'];
        }
        if (!$isbot) {
            $_SESSION['sid'] = $sid;
        }
        $time = time();
        $sessData = array(
            'id' => $sid,
            'uid' => $uid,
            'runonce' => '',
            'ip' => $JAX->ip2bin(),
            'useragent' => $_SERVER['HTTP_USER_AGENT'],
            'is_bot' => $isbot,
            'last_action' => date('Y-m-d H:i:s', $time),
            'last_update' => date('Y-m-d H:i:s', $time),
        );
        if (1 > $uid) {
            unset($sessData['uid']);
        }
        $DB->safeinsert(
            'session',
            $sessData
        );

        return $sessData;
    }

    public function __get($a)
    {
        if (isset($this->data[$a])) {
            return $this->data[$a];
        }
    }

    public function __set($a, $b)
    {
        if (isset($this->data[$a]) && $this->data[$a] == $b) {
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
        if (!isset($this->data['vars'][$a])
            || $this->data['vars'][$a] !== $b
        ) {
            $this->data['vars'][$a] = $b;
            $this->changedData['vars'] = serialize($this->data['vars']);
        }
    }

    public function delvar($a)
    {
        if (isset($this->data['vars'][$a])) {
            unset($this->data['vars'][$a]);
            $this->changedData['vars'] = serialize($this->data['vars']);
        }
    }

    public function act($a = false)
    {
        global $JAX;
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
        if (!is_numeric($uid) || 1 > $uid) {
            $uid = null;
        } else {
            $result = $DB->safeselect(
                'UNIX_TIMESTAMP(MAX(`last_action`)) AS `last_action`',
                'session',
                'WHERE `uid`=? GROUP BY `uid`',
                $uid
            );
            $la = $DB->arow($result);
            $DB->disposeresult($result);
            if ($la) {
                $la = $la['last_action'];
            }
            $DB->safedelete(
                'session',
                'WHERE `uid`=? AND `last_update`<?',
                $DB->basicvalue($uid),
                date('Y-m-d H:i:s', $timeago)
            );
            // Delete all expired tokens as well while we're here...
            $DB->safedelete(
                'tokens',
                'WHERE `expires`<=?',
                $DB->basicvalue(date('Y-m-d H:i:s', time()))
            );
            $this->__set('read_date', $JAX->pick($la, 0));
        }
        $yesterday = mktime(0, 0, 0);
        $query = $DB->safeselect(
            '`uid`,UNIX_TIMESTAMP(MAX(`last_action`)) AS `last_action`',
            'session',
            'WHERE `last_update`<? GROUP BY uid',
            date('Y-m-d H:i:s', $yesterday)
        );
        while ($f = $DB->arow($query)) {
            if ($f['uid']) {
                $DB->safeupdate(
                    'members',
                    array(
                        'last_visit' => date('Y-m-d H:i:s', $f['last_action']),
                    ),
                    'WHERE `id`=?',
                    $f['uid']
                );
            }
        }
        $DB->safedelete(
            'session',
            <<<'EOT'
WHERE `last_update`<?
    OR (`uid` IS NULL AND `last_update`<?)
EOT
            ,
            date('Y-m-d H:i:s', $yesterday),
            date('Y-m-d H:i:s', $timeago)
        );

        return true;
    }

    public function applyChanges()
    {
        global $DB,$PAGE;
        $sd = $this->changedData;
        $id = $this->data['id'];
        $sd['last_update'] = date('Y-m-d H:i:s', time());
        $datetimes = array('last_action', 'read_date');
        foreach ($datetimes as $datetime) {
            if (isset($sd[$datetime])) {
                $sd[$datetime] = date('Y-m-d H:i:s', $sd[$datetime]);
            }
        }
        if ($this->data['is_bot']) {
            // Bots tend to read a lot of content.
            $sd['forumsread'] = '';
            $sd['topicsread'] = '';
        }
        if (!$this->data['last_action']) {
            $sd['last_action'] = date('Y-m-d H:i:s', time());
        }
        if (isset($sd['user'])) {
            // This doesn't exist.
            unset($sd['user']);
        }
        if (!empty($sd)) {
            // Only update if there's data to update.
            $DB->safeupdate(
                'session',
                $sd,
                'WHERE `id`=?',
                $DB->basicvalue($id)
            );
        }
    }

    public function addSessID($html)
    {
        global $JAX;
        if (!empty($JAX->c)) {
            return $html;
        }

        return preg_replace_callback(
            "@href=['\"]?([^'\"]+)['\"]?@",
            array($this, 'addSessIDCB'),
            $html
        );
    }

    public function addSessIDCB($m)
    {
        if ('?' == $m[1][0]) {
            $m[1] .= '&amp;sessid=' . $this->data['id'];
        }

        return 'href="' . $m[1] . '"';
    }
}
