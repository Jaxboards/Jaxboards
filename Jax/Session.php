<?php

declare(strict_types=1);

namespace Jax;

use function base64_encode;
use function ini_set;
use function is_numeric;
use function is_string;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function mktime;
use function openssl_random_pseudo_bytes;
use function preg_replace_callback;
use function serialize;
use function session_start;
use function str_contains;
use function time;
use function unserialize;

final class Session
{
    /**
     * @var array<mixed>
     */
    private $data = [];

    /**
     * @var array<string,string>
     */
    private $bots = [
        // SEO crawler
        'AhrefsBot' => 'Ahrefs',
        'Amazonbot' => 'Amazon',
        'Applebot' => 'Applebot',
        'archive.org_bot' => 'Internet Archive',
        // Social media management company
        'AwarioBot' => 'Awario',
        // Chinese search engine
        'Baiduspider' => 'Baidu',
        // SEO graphing services
        'Barkrowler' => 'Babbar.tech',
        'Bingbot' => 'Bing',
        // TikTok parent company
        'Bytespider' => 'Bytespider',
        // Security scanner
        'CensysInspect' => 'CensysInspect',
        // a.k.a. RightDao, a search engine
        'Centurybot' => 'Century',
        // SEO crawler
        'ChatGLM-Spider' => 'ChatGLM',
        // AI developer
        'ChatGPT-User' => 'ChatGPT',
        // Anthropic AI bot
        'ClaudeBot' => 'ClaudeBot',
        'Discordbot' => 'Discord',
        // Moz SEO crawler
        'DotBot' => 'DotBot',
        'DuckDuckBot' => 'DuckDuckGo',
        // Palo Alto Networks security scanning service
        'Expanse' => 'Expanse',
        'facebookexternalhit' => 'Facebook',
        // Machine learning researcher
        'Friendly_Crawler' => 'FriendlyCrawler',
        'Googlebot' => 'Google',
        'GoogleOther' => 'GoogleOther',
        // AI developer
        'GPTBot' => 'GPTBot',
        'ia_archiver' => 'Internet Archive Alexa',
        // Hive image search; may be AI-related
        'ImagesiftBot' => 'Imagesift',
        // SEO crawler
        'linkdexbot' => 'Linkdex',
        // Russian mail service
        'Mail.RU_Bot' => 'Mail.RU',
        // May be AI-related
        'meta-externalagent' => 'Meta',
        // British SEO crawler
        'mj12bot' => 'Majestic',
        // British search engine
        'MojeekBot' => 'Mojeek',
        // AI developer
        'OAI-SearchBot' => 'OpenAI',
        // AI answers site
        'PerplexityBot' => 'Perplexity',
        // Chinese search crawler (Huawei)
        'PetalBot' => 'PetalBot',
        // French search engine
        'Qwantbot' => 'Qwant',
        // Backlink tracking company
        'SemrushBot' => 'Semrush',
        // Czech search engine
        'SeznamBot' => 'Seznam',
        // Chinese search engine
        'Sogou web spider' => 'Sogou',
        'Teoma' => 'Ask.com',
        'TikTokSpider' => 'TikTok',
        // Plagiarism scanning software
        'Turnitin' => 'Turnitin',
        'Twitterbot' => 'Twitter',
        // HTML syntax checker
        'W3C_Validator' => 'W3C Validator',
        'WhatsApp' => 'WhatsApp',
        'Y!J-WSC' => 'Yahoo Japan',
        'yahoo! slurp' => 'Yahoo',
        // Russian search engine
        'YandexBot' => 'Yandex',
    ];

    private $changedData = [];

    public function __construct(
        private readonly Config $config,
        private readonly Jax $jax,
        private readonly IPAddress $ipAddress,
        private readonly Database $database,
        private readonly Request $request,
        private readonly User $user,
    ) {
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }

    public function fetchSessionData(): void
    {
        $this->data = $this->getSess($this->getPHPSessionValue('sid') ?? null);
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

        if ($this->get('is_bot')) {
            return null;
        }

        $userId = $this->getPHPSessionValue('uid');
        if (
            $userId === null
            && $this->request->cookie('utoken') !== null
        ) {
            $result = $this->database->safeselect(
                ['uid'],
                'tokens',
                'WHERE `token`=?',
                $this->request->cookie('utoken'),
            );
            $token = $this->database->arow($result);
            if ($token) {
                $this->setPHPSessionValue('uid', $token['uid']);
            }
        }

        return $userId ?? 0;
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     *
     * @param null|mixed $sid
     */
    public function getSess($sid = null): array
    {
        $session = [];
        $botName = $this->getBotName();

        if ($botName) {
            $sid = $botName;
        }

        if ($sid) {
            $result = $botName === null
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
            $session = $this->database->arow($result);
            $this->database->disposeresult($result);
        }


        if ($session !== []) {
            $session['last_action'] = (int) $session['last_action'];
            $session['last_update'] = (int) $session['last_update'];
            $session['read_date'] = (int) $session['read_date'];

            return $session;
        }

        if ($botName === null) {
            $sid = base64_encode(openssl_random_pseudo_bytes(128));
            $this->setPHPSessionValue('sid', $sid);
        }

        $actionTime = $this->database->datetime();
        $sessData = [
            'forumsread' => '{}',
            'id' => $sid,
            'ip' => $this->ipAddress->asBinary(),
            'is_bot' => $botName === null ? 0 : 1,
            'last_action' => $actionTime,
            'last_update' => $actionTime,
            'runonce' => '',
            'topicsread' => '{}',
            'useragent' => $this->getUserAgent(),
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

    public function get(string $field)
    {
        return $this->data[$field] ?? null;
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function getPHPSessionValue(string $field)
    {
        return $_SESSION[$field] ?? null;
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function setPHPSessionValue(
        string $field,
        int|string $value,
    ): int|string {
        return $_SESSION[$field] = $value;
    }

    public function set(string $field, mixed $value): void
    {
        if (isset($this->data[$field]) && $this->data[$field] === $value) {
            return;
        }

        $this->changedData[$field] = $value;
        $this->data[$field] = $value;
    }

    public function addvar(string $varName, mixed $value): void
    {
        if (
            isset($this->data['vars'][$varName])
            && $this->data['vars'][$varName] === $value
        ) {
            return;
        }

        $this->data['vars'][$varName] = $value;
        $this->changedData['vars'] = serialize($this->data['vars']);
    }

    public function deleteVar(string $varName): void
    {
        if (!isset($this->data['vars'][$varName])) {
            return;
        }

        unset($this->data['vars'][$varName]);
        $this->changedData['vars'] = serialize($this->data['vars']);
    }

    public function getVar(string $varName)
    {
        return $this->data['vars'][$varName] ?? null;
    }

    public function act(?string $location = null): void
    {
        $this->set('last_action', time());
        if (!$location) {
            return;
        }

        $this->set('location', $location);
    }

    public function erase(string $fieldName): void
    {
        unset($this->changedData[$fieldName]);
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
                $this->database->datetime($timeago),
            );
            // Delete all expired tokens as well while we're here...
            $this->database->safedelete(
                'tokens',
                'WHERE `expires`<=?',
                $this->database->basicvalue($this->database->datetime()),
            );
            $this->set('read_date', $this->jax->pick($lastAction, 0));
        }

        $yesterday = mktime(0, 0, 0);
        $query = $this->database->safeselect(
            [
                'uid',
                'UNIX_TIMESTAMP(MAX(`last_action`)) AS `last_action`',
            ],
            'session',
            'WHERE `last_update`<? GROUP BY uid',
            $this->database->datetime($yesterday),
        );
        while ($session = $this->database->arow($query)) {
            if (!$session['uid']) {
                continue;
            }

            $this->database->safeupdate(
                'members',
                [
                    'last_visit' => $this->database->datetime($session['last_action']),
                ],
                'WHERE `id`=?',
                $session['uid'],
            );
        }

        $this->database->safedelete(
            'session',
            <<<'SQL'
                WHERE `last_update`<?
                    OR (`uid` IS NULL AND `last_update`<?)
                SQL
            ,
            $this->database->datetime($yesterday),
            $this->database->datetime($timeago),
        );

        return true;
    }

    public function applyChanges(): void
    {
        $session = $this->changedData;
        $session['last_update'] = $this->database->datetime();
        $datetimes = ['last_action', 'read_date'];
        foreach ($datetimes as $datetime) {
            if (!isset($session[$datetime])) {
                continue;
            }

            $session[$datetime] = $this->database->datetime($session[$datetime]);
        }

        if ($this->data['is_bot']) {
            // Bots tend to read a lot of content.
            $session['forumsread'] = '{}';
            $session['topicsread'] = '{}';
        }

        if (!$this->data['last_action']) {
            $session['last_action'] = $this->database->datetime();
        }

        if (isset($session['user'])) {
            // This doesn't exist.
            unset($session['user']);
        }

        if ($session === []) {
            return;
        }

        if (mb_strlen($session['location_verbose'] ?? '') > 100) {
            $session['location_verbose'] = mb_substr(
                (string) $session['location_verbose'],
                0,
                100,
            );
        }

        // Only update if there's data to update.
        $this->database->safeupdate(
            'session',
            $session,
            'WHERE `id`=?',
            $this->database->basicvalue($this->data['id']),
        );
    }

    public function addSessID($html)
    {
        if ($this->request->hasCookies() || !is_string($html)) {
            return $html;
        }

        return preg_replace_callback(
            "@href=['\"]?([^'\"]+)['\"]?@",
            $this->addSessIDCB(...),
            $html,
        );
    }

    public function addSessIDCB(array $match): string
    {
        if ($match[1][0] === '?') {
            $match[1] .= '&amp;sessid=' . $this->data['id'];
        }

        return 'href="' . $match[1] . '"';
    }

    private function getBotName(): ?string
    {
        foreach ($this->bots as $agentName => $friendlyName) {
            if (str_contains(mb_strtolower((string) $this->getUserAgent()), mb_strtolower((string) $agentName))) {
                return $friendlyName;
            }
        }

        return null;
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    private function getUserAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?: null;
    }
}
