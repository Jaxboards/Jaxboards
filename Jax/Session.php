<?php

declare(strict_types=1);

namespace Jax;

use function base64_encode;
use function ini_set;
use function is_numeric;
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
    private array $data = ['vars' => []];

    /**
     * @var array<string,string>
     */
    private array $bots = [
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

    /**
     * @var array<string,mixed>
     */
    private array $changedData = [];

    public function __construct(
        private readonly Config $config,
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

    public function loginWithToken(): ?int
    {
        $this->data = $this->fetchSessionData($this->getPHPSessionValue('sid') ?? null);

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

                return $token['uid'];
            }
        }

        return $userId ?? 0;
    }

    /**
     * @param int|?string $sid
     *
     * @return array<string,mixed>
     */
    public function fetchSessionData($sid = null): array
    {
        $session = null;
        $botName = $this->getBotName();
        $sid = $botName ?? $sid;

        if ($sid) {
            $params = $botName
                ? [Database::WHERE_ID_EQUALS, $sid]
                : ['WHERE `id`=? AND `ip`=?', $sid, $this->ipAddress->asBinary()];

            $result = $this->database->safeselect(
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
                ...$params,
            );
            $session = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        if ($session !== null) {
            $session['last_action'] = (int) $session['last_action'];
            $session['last_update'] = (int) $session['last_update'];
            $session['read_date'] = (int) $session['read_date'];
            $session['vars'] = unserialize($session['vars']) ?? [];

            return $session;
        }

        return $this->createSession();
    }

    public function get(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function getPHPSessionValue(string $field): mixed
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

    public function addVar(string $varName, mixed $value): void
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

    public function getVar(string $varName): mixed
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
            $this->set('read_date', $lastAction ?: 0);
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
                Database::WHERE_ID_EQUALS,
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
            Database::WHERE_ID_EQUALS,
            $this->database->basicvalue($this->data['id']),
        );
    }

    /**
     * For users disallowing cookies, attempt to prepend the session ID to all links.
     * This is similar to what PHP sessions do and it may be possible to leverage
     * that logic instead of handwriting it ourselves.
     */
    public function addSessID(string $html): string
    {
        if ($this->request->hasCookies()) {
            return $html;
        }

        return (string) preg_replace_callback(
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

    /**
     * @return array<string,mixed>
     */
    private function createSession(): array
    {
        $botName = $this->getBotName();
        $sid = $botName;

        if (!$sid) {
            $sid = base64_encode(openssl_random_pseudo_bytes(128));
            $this->setPHPSessionValue('sid', $sid);
        }

        $actionTime = $this->database->datetime();
        $sessData = [
            'forumsread' => '{}',
            'id' => $sid,
            'ip' => $this->ipAddress->asBinary(),
            'is_bot' => $botName !== null,
            'last_action' => $actionTime,
            'last_update' => $actionTime,
            'runonce' => '',
            'topicsread' => '{}',
            'useragent' => $this->request->getUserAgent(),
        ];

        $uid = $this->user->get('id');
        if ($uid) $sessData['uid'] = $uid;

        $this->database->safeinsert('session', $sessData);

        return $sessData;
    }

    private function getBotName(): ?string
    {
        $userAgent = mb_strtolower((string) $this->request->getUserAgent());
        foreach ($this->bots as $agentName => $friendlyName) {
            if (str_contains($userAgent, mb_strtolower($agentName))) {
                return $friendlyName;
            }
        }

        return null;
    }
}
