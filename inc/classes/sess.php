<?php

declare(strict_types=1);

#[AllowDynamicProperties]
final class SESS
{
    public $data = [];

    public $userData = [];

    public $bots = [
        'AhrefsBot' => 'Ahrefs', // SEO crawler
        'Amazonbot' => 'Amazon',
        'Applebot' => 'Applebot',
        'archive.org_bot' => 'Internet Archive',
        'AwarioBot' => 'Awario', // Social media management company
        'Baiduspider' => 'Baidu', // Chinese search engine
        'Barkrowler' => 'Babbar.tech', // SEO graphing services
        'Bingbot' => 'Bing',
        'Bytespider' => 'Bytespider', // TikTok parent company
        'CensysInspect' => 'CensysInspect', // Security scanner
        'Centurybot' => 'Century', // a.k.a. RightDao, a search engine
        'ChatGLM-Spider' => 'ChatGLM', // SEO crawler
        'ChatGPT-User' => 'ChatGPT', // AI developer
        'ClaudeBot' => 'ClaudeBot', // Anthropic AI bot
        'Discordbot' => 'Discord',
        'DotBot' => 'DotBot', // Moz SEO crawler
        'DuckDuckBot' => 'DuckDuckGo',
        'Expanse' => 'Expanse', // Palo Alto Networks security scanning service
        'facebookexternalhit' => 'Facebook',
        'Friendly_Crawler' => 'FriendlyCrawler', // Machine learning researcher
        'Googlebot' => 'Google',
        'GoogleOther' => 'GoogleOther',
        'GPTBot' => 'GPTBot', // AI developer
        'ia_archiver' => 'Internet Archive Alexa',
        'ImagesiftBot' => 'Imagesift', // Hive image search; may be AI-related
        'linkdexbot' => 'Linkdex', // SEO crawler
        'Mail.RU_Bot' => 'Mail.RU', // Russian mail service
        'meta-externalagent' => 'Meta', // May be AI-related
        'mj12bot' => 'Majestic', // British SEO crawler
        'MojeekBot' => 'Mojeek', // British search engine
        'OAI-SearchBot' => 'OpenAI', // AI developer
        'PerplexityBot' => 'Perplexity', // AI answers site
        'PetalBot' => 'PetalBot', // Chinese search crawler (Huawei)
        'Qwantbot' => 'Qwant', // French search engine
        'SemrushBot' => 'Semrush', // Backlink tracking company
        'SeznamBot' => 'Seznam', // Czech search engine
        'Sogou web spider' => 'Sogou', // Chinese search engine
        'Teoma' => 'Ask.com',
        'TikTokSpider' => 'TikTok',
        'Turnitin' => 'Turnitin', // Plagiarism scanning software
        'Twitterbot' => 'Twitter',
        'W3C_Validator' => 'W3C Validator', // HTML syntax checker
	'WhatsApp' => 'WhatsApp',
        'Y!J-WSC' => 'Yahoo Japan',
        'yahoo! slurp' => 'Yahoo',
        'YandexBot' => 'Yandex', // Russian search engine
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

        $actionTime = gmdate('Y-m-d H:i:s');
        $sessData = [
            'forumsread' => '{}',
            'id' => $sid,
            'ip' => $JAX->ip2bin(),
            'is_bot' => $isbot,
            'last_action' => $actionTime,
            'last_update' => $actionTime,
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
                gmdate('Y-m-d H:i:s', $timeago),
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
            gmdate('Y-m-d H:i:s', $yesterday),
        );
        while ($f = $DB->arow($query)) {
            if (!$f['uid']) {
                continue;
            }

            $DB->safeupdate(
                'members',
                [
                    'last_visit' => gmdate('Y-m-d H:i:s', $f['last_action']),
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
            gmdate('Y-m-d H:i:s', $yesterday),
            gmdate('Y-m-d H:i:s', $timeago),
        );

        return true;
    }

    public function applyChanges(): void
    {
        global $DB,$PAGE;
        $sd = $this->changedData;
        $id = $this->data['id'];
        $sd['last_update'] = gmdate('Y-m-d H:i:s');
        $datetimes = ['last_action', 'read_date'];
        foreach ($datetimes as $datetime) {
            if (!isset($sd[$datetime])) {
                continue;
            }

            $sd[$datetime] = gmdate('Y-m-d H:i:s', $sd[$datetime]);
        }

        if ($this->data['is_bot']) {
            // Bots tend to read a lot of content.
            $sd['forumsread'] = '';
            $sd['topicsread'] = '';
        }

        if (!$this->data['last_action']) {
            $sd['last_action'] = gmdate('Y-m-d H:i:s');
        }

        if (isset($sd['user'])) {
            // This doesn't exist.
            unset($sd['user']);
        }

        if (empty($sd)) {
            return;
        }

        if (mb_strlen($sd['location_verbose'] ?? '') > 100) {
            $sd['location_verbose'] = mb_substr(
                $sd['location_verbose'],
                0,
                100,
            );
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
