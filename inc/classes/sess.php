<?php

#[AllowDynamicProperties]
final class SESS
{
    public $data = [];

    public $userData = [];

    public $bots = [
        // Moz SEO crawler
        'AhrefsBot' => 'Ahrefs',
        'Amazonbot' => 'Amazon',
        'Applebot' => 'Applebot',
        'archive.org_bot' => 'Internet Archive',
        // SEO graphing services
        'AwarioBot' => 'Awario',
        // Machine learning researcher
        'Baiduspider' => 'Baidu',
        // SEO crawler
        'Barkrowler' => 'Babbar.tech',
        'Bingbot' => 'Bing',
        'Bytespider' => 'TikTok',
        // Palo Alto Networks security scanning service
        'CensysInspect' => 'CensysInspect',
        // Czech search engine
        'Centurybot' => 'Century',
        // SEO crawler
        'ChatGLM-Spider' => 'ChatGLM',
        // Anthropic AI bot
        'ChatGPT-User' => 'ChatGPT',
        'ClaudeBot' => 'ClaudeBot',
        'Discordbot' => 'Discord',
        // Backlink tracking company
        'DotBot' => 'DotBot',
        'DuckDuckBot' => 'DuckDuckGo',
        // Plagiarism scanning software
        'Expanse' => 'Expanse',
        'facebookexternalhit' => 'Facebook',
        // Social media management company
        'Friendly_Crawler' => 'FriendlyCrawler',
        'Googlebot' => 'Google',
        'GoogleOther' => 'GoogleOther',
        // AI developer
        'GPTBot' => 'GPTBot',
        'ia_archiver' => 'Internet Archive Alexa',
        // AI answers site
        'ImagesiftBot' => 'Imagesift',
        // SEO crawler
        'linkdexbot' => 'Linkdex',
        // a.k.a. RightDao, a search engine
        'Mail.RU_Bot' => 'Mail.RU',
        'meta-externalagent' => 'Meta',
        // Hive image search; may be AI-related
        'mj12bot' => 'Majestic',
        'MojeekBot' => 'Mojeek',
        // AI developer
        'OAI-SearchBot' => 'OpenAI',
        // AI developer
        'PerplexityBot' => 'Perplexity',
        // Russian search engine
        'PetalBot' => 'PetalBot',
        // British search engine
        'Qwantbot' => 'Qwant',
        // British SEO crawler
        'SemrushBot' => 'Semrush',
        // Chinese search engine
        'SeznamBot' => 'Seznam',
        // French search engine
        'Sogou web spider' => 'Sogou',
        'Teoma' => 'Ask.com',
        'Turnitin' => 'Turnitin',
        'Twitterbot' => 'Twitter',
        // May be AI-related
        'WhatsApp' => 'WhatsApp',
        // Chinese search crawler (Huawei)
        'Y!J-WSC' => 'Yahoo Japan',
        'yahoo! slurp' => 'Yahoo',
        // Chinese search engine
        'YandexBot' => 'Yandex',
        // Security scanner
    ];

    public $changedData = [];

    public function __construct($sid = false)
    {
        $this->data = $this->getSess($sid);
        if (!isset($this->data['vars'])) {
            $this->data['vars'] = serialize([]);
        }

        $this->data['vars'] = unserialize($this->data['vars']);
        if ($this->data['vars']) {
            return;
        }

        $this->data['vars'] = [];
    }

    public function __get($a)
    {
        return $this->data[$a] ?? null;
    }

    public function __set($a, $b): void
    {
        if (isset($this->data[$a]) && $this->data[$a] === $b) {
            return;
        }

        $this->changedData[$a] = $b;
        $this->data[$a] = $b;
    }

    public function getSess($sid = false)
    {
        global $DB,$JAX,$_SESSION;
        $isbot = 0;
        $r = [];
        foreach ($this->bots as $k => $v) {
            if (mb_stripos(mb_strtolower((string) $_SERVER['HTTP_USER_AGENT']), (string) $k) === false) {
                continue;
            }

            $sid = $v;
            $isbot = 1;
        }

        if ($sid) {
            $result = $isbot === 0
                ? $DB->safeselect(
                    [
                        'buddy_list_cache',
                        'forumsread',
                        'hide',
                        'id',
                        'is_bot',
                        'location_verbose',
                        'location',
                        'runonce',
                        'topicsread',
                        'uid',
                        'useragent',
                        'users_online_cache',
                        'vars',
                        'INET6_NTOA(`ip`) as `ip`',
                        'UNIX_TIMESTAMP(`last_action`) AS `last_action`',
                        'UNIX_TIMESTAMP(`last_update`) AS `last_update`',
                        'UNIX_TIMESTAMP(`read_date`) AS `read_date`',
                    ],
                    'session',
                    'WHERE `id`=? AND `ip`=INET6_ATON(?)',
                    $DB->basicvalue($sid),
                    $JAX->getIp(),
                )
                    : $DB->safeselect(
                        [
                            'buddy_list_cache',
                            'forumsread',
                            'hide',
                            'id',
                            'is_bot',
                            'location_verbose',
                            'location',
                            'runonce',
                            'topicsread',
                            'uid',
                            'useragent',
                            'users_online_cache',
                            'vars',
                            'INET6_NTOA(`ip`) as `ip`',
                            'UNIX_TIMESTAMP(`last_action`) AS `last_action`',
                            'UNIX_TIMESTAMP(`last_update`) AS `last_update`',
                            'UNIX_TIMESTAMP(`read_date`) AS `read_date`',
                        ],
                        'session',
                        'WHERE `id`=?',
                        $DB->basicvalue($sid),
                    );
            $r = $DB->arow($result);
            $DB->disposeresult($result);
        }

        if (!empty($r)) {
            return $r;
        }

        if ($isbot === 0) {
            $sid = base64_encode(openssl_random_pseudo_bytes(128));
        }

        $uid = 0;
        if (
            !empty($JAX->userData)
            && isset($JAX->userData['id'])
            && $JAX->userData['id'] > 0
        ) {
            $uid = (int) $JAX->userData['id'];
        }

        if ($isbot === 0) {
            $_SESSION['sid'] = $sid;
        }

        $time = time();
        $sessData = [
            'forumsread' => '{}',
            'id' => $sid,
            'ip' => $JAX->ip2bin(),
            'is_bot' => $isbot,
            'last_action' => date('Y-m-d H:i:s', $time),
            'last_update' => date('Y-m-d H:i:s', $time),
            'runonce' => '',
            'topicsread' => '{}',
            'uid' => $uid,
            'useragent' => $_SERVER['HTTP_USER_AGENT'],
        ];
        if ($uid < 1) {
            unset($sessData['uid']);
        }

        $DB->safeinsert(
            'session',
            $sessData,
        );

        return $sessData;
    }

    public function set($a): void
    {
        foreach ($a as $k => $v) {
            $this->__set($k, $v);
        }
    }

    public function addvar($a, $b): void
    {
        if (
            isset($this->data['vars'][$a])
            && $this->data['vars'][$a] === $b
        ) {
            return;
        }

        $this->data['vars'][$a] = $b;
        $this->changedData['vars'] = serialize($this->data['vars']);
    }

    public function delvar($a): void
    {
        if (!isset($this->data['vars'][$a])) {
            return;
        }

        unset($this->data['vars'][$a]);
        $this->changedData['vars'] = serialize($this->data['vars']);
    }

    public function act($a = false): void
    {
        global $JAX;
        $this->__set('last_action', time());
        if (!$a) {
            return;
        }

        $this->__set('location', $a);
    }

    public function erase($a): void
    {
        unset($this->changedData[$a]);
    }

    public function clean($uid): bool
    {
        global $DB,$CFG,$PAGE,$JAX;
        $timeago = time() - $CFG['timetologout'];
        if (!is_numeric($uid) || $uid < 1) {
            $uid = null;
        } else {
            $result = $DB->safeselect(
                'UNIX_TIMESTAMP(MAX(`last_action`)) AS `last_action`',
                'session',
                'WHERE `uid`=? GROUP BY `uid`',
                $uid,
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
                date('Y-m-d H:i:s', $timeago),
            );
            // Delete all expired tokens as well while we're here...
            $DB->safedelete(
                'tokens',
                'WHERE `expires`<=?',
                $DB->basicvalue(date('Y-m-d H:i:s', time())),
            );
            $this->__set('read_date', $JAX->pick($la, 0));
        }

        $yesterday = mktime(0, 0, 0);
        $query = $DB->safeselect(
            [
                'uid',
                'UNIX_TIMESTAMP(MAX(`last_action`)) AS `last_action`',
            ],
            'session',
            'WHERE `last_update`<? GROUP BY uid',
            date('Y-m-d H:i:s', $yesterday),
        );
        while ($f = $DB->arow($query)) {
            if (!$f['uid']) {
                continue;
            }

            $DB->safeupdate(
                'members',
                [
                    'last_visit' => date('Y-m-d H:i:s', $f['last_action']),
                ],
                'WHERE `id`=?',
                $f['uid'],
            );
        }

        $DB->safedelete(
            'session',
            <<<'EOT'
                WHERE `last_update`<?
                    OR (`uid` IS NULL AND `last_update`<?)
                EOT
            ,
            date('Y-m-d H:i:s', $yesterday),
            date('Y-m-d H:i:s', $timeago),
        );

        return true;
    }

    public function applyChanges(): void
    {
        global $DB,$PAGE;
        $sd = $this->changedData;
        $id = $this->data['id'];
        $sd['last_update'] = date('Y-m-d H:i:s', time());
        $datetimes = ['last_action', 'read_date'];
        foreach ($datetimes as $datetime) {
            if (!isset($sd[$datetime])) {
                continue;
            }

            $sd[$datetime] = date('Y-m-d H:i:s', $sd[$datetime]);
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

        if (empty($sd)) {
            return;
        }

        // Only update if there's data to update.
        $DB->safeupdate(
            'session',
            $sd,
            'WHERE `id`=?',
            $DB->basicvalue($id),
        );
    }

    public function addSessID($html)
    {
        global $JAX;
        if (!empty($JAX->c)) {
            return $html;
        }

        return preg_replace_callback(
            "@href=['\"]?([^'\"]+)['\"]?@",
            $this->addSessIDCB(...),
            (string) $html,
        );
    }

    public function addSessIDCB($m): string
    {
        if ($m[1][0] === '?') {
            $m[1] .= '&amp;sessid=' . $this->data['id'];
        }

        return 'href="' . $m[1] . '"';
    }
}
