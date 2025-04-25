<?php

declare(strict_types=1);

namespace Jax;

#[AllowDynamicProperties]
final class Session
{
    /**
     * @var mixed[]
     */
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

    public function __construct(
        private readonly Config $config,
        private readonly Jax $jax,
        private readonly IPAddress $ipAddress,
        private readonly Database $database,
        private readonly User $user,
    ) {}

    public function fetchSessionData(): void
    {
        $this->data = $this->getSess($_SESSION['sid'] ?? null);
        if (!isset($this->data['vars'])) {
            $this->data['vars'] = serialize([]);
        }

        $this->data['vars'] = unserialize($this->data['vars']);
        if ($this->data['vars']) {
            return;
        }

        $this->data['vars'] = [];
    }

    public function loginWithToken(): ?int
    {
        $this->fetchSessionData();

        if ($this->is_bot) {
            return null;
        }

        if (!isset($_SESSION['uid']) && isset($this->jax->c['utoken'])) {
            $result = $this->database->safeselect(
                ['uid'],
                'tokens',
                'WHERE `token`=?',
                $this->jax->c['utoken'],
            );
            $token = $this->database->arow($result);
            if ($token) {
                $_SESSION['uid'] = $token['uid'];
            }
        }

        return $_SESSION['uid'];
    }

    public function __get($a)
    {
        return $this->data[$a] ?? null;
    }

    public function __set($property, $value): void
    {
        if (isset($this->data[$property]) && $this->data[$property] === $value) {
            return;
        }

        $this->changedData[$property] = $value;
        $this->data[$property] = $value;
    }

    public function getSess($sid = null): array
    {
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
                ? $this->database->safeselect(
                    [
                        'buddy_list_cache',
                        'forumsread',
                        'hide',
                        'id',
                        'ip',
                        'is_bot',
                        'location_verbose',
                        'location',
                        'runonce',
                        'topicsread',
                        'uid',
                        'useragent',
                        'users_online_cache',
                        'vars',
                        'UNIX_TIMESTAMP(`last_action`) AS `last_action`',
                        'UNIX_TIMESTAMP(`last_update`) AS `last_update`',
                        'UNIX_TIMESTAMP(`read_date`) AS `read_date`',
                    ],
                    'session',
                    'WHERE `id`=? AND `ip`=?',
                    $this->database->basicvalue($sid),
                    $this->ipAddress->asBinary(),
                )
                    : $this->database->safeselect(
                        [
                            'buddy_list_cache',
                            'forumsread',
                            'hide',
                            'id',
                            'ip',
                            'is_bot',
                            'location_verbose',
                            'location',
                            'runonce',
                            'topicsread',
                            'uid',
                            'useragent',
                            'users_online_cache',
                            'vars',
                            'UNIX_TIMESTAMP(`last_action`) AS `last_action`',
                            'UNIX_TIMESTAMP(`last_update`) AS `last_update`',
                            'UNIX_TIMESTAMP(`read_date`) AS `read_date`',
                        ],
                        'session',
                        'WHERE `id`=?',
                        $this->database->basicvalue($sid),
                    );
            $r = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        if (!empty($r)) {
            $r['last_action'] = (int) $r['last_action'];
            $r['last_update'] = (int) $r['last_update'];
            $r['read_date'] = (int) $r['read_date'];

            return $r;
        }

        if ($isbot === 0) {
            $sid = base64_encode(openssl_random_pseudo_bytes(128));
        }


        if ($isbot === 0) {
            $_SESSION['sid'] = $sid;
        }

        $actionTime = gmdate('Y-m-d H:i:s');
        $sessData = [
            'forumsread' => '{}',
            'id' => $sid,
            'ip' => $this->ipAddress->asBinary(),
            'is_bot' => $isbot,
            'last_action' => $actionTime,
            'last_update' => $actionTime,
            'runonce' => '',
            'topicsread' => '{}',
            'useragent' => $_SERVER['HTTP_USER_AGENT'],
        ];

        $uid = $this->user->get('id');
        if ($uid) {
            $sessData['uid'] = $uid;
        }

        $this->database->safeinsert(
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
        $timeago = time() - $this->config->getSetting('timetologout');
        if (!is_numeric($uid) || $uid < 1) {
            $uid = null;
        } else {
            $result = $this->database->safeselect(
                'UNIX_TIMESTAMP(MAX(`last_action`)) AS `last_action`',
                'session',
                'WHERE `uid`=? GROUP BY `uid`',
                $uid,
            );
            $lastAction = $this->database->arow($result);
            $this->database->disposeresult($result);
            if ($lastAction) {
                $lastAction = (int) $lastAction['last_action'];
            }

            $this->database->safedelete(
                'session',
                'WHERE `uid`=? AND `last_update`<?',
                $this->database->basicvalue($uid),
                gmdate('Y-m-d H:i:s', $timeago),
            );
            // Delete all expired tokens as well while we're here...
            $this->database->safedelete(
                'tokens',
                'WHERE `expires`<=?',
                $this->database->basicvalue(date('Y-m-d H:i:s', time())),
            );
            $this->__set('read_date', $this->jax->pick($lastAction, 0));
        }

        $yesterday = mktime(0, 0, 0);
        $query = $this->database->safeselect(
            [
                'uid',
                'UNIX_TIMESTAMP(MAX(`last_action`)) AS `last_action`',
            ],
            'session',
            'WHERE `last_update`<? GROUP BY uid',
            gmdate('Y-m-d H:i:s', $yesterday),
        );
        while ($f = $this->database->arow($query)) {
            if (!$f['uid']) {
                continue;
            }

            $this->database->safeupdate(
                'members',
                [
                    'last_visit' => gmdate('Y-m-d H:i:s', $f['last_action']),
                ],
                'WHERE `id`=?',
                $f['uid'],
            );
        }

        $this->database->safedelete(
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
            $sd['forumsread'] = '{}';
            $sd['topicsread'] = '{}';
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
                (string) $sd['location_verbose'],
                0,
                100,
            );
        }

        // Only update if there's data to update.
        $this->database->safeupdate(
            'session',
            $sd,
            'WHERE `id`=?',
            $this->database->basicvalue($id),
        );
    }

    public function addSessID($html)
    {
        if ($this->jax->c !== [] || !is_string($html)) {
            return $html;
        }

        return preg_replace_callback(
            "@href=['\"]?([^'\"]+)['\"]?@",
            $this->addSessIDCB(...),
            $html,
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
