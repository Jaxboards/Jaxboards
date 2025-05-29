<?php

declare(strict_types=1);

namespace Jax;

use Carbon\Carbon;
use Jax\Models\Session as ModelsSession;
use Jax\Models\Token;

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
use function unserialize;

final class Session
{
    private array $vars = [];

    /**
     * @var array<string,string>
     */
    private array $bots = [
        'AhrefsBot' => 'Ahrefs',
        'Amazonbot' => 'Amazon',
        'Applebot' => 'Applebot',
        'archive.org_bot' => 'Internet Archive',
        'AwarioBot' => 'Awario',
        'Baiduspider' => 'Baidu',
        'Barkrowler' => 'Babbar.tech',
        'Bingbot' => 'Bing',
        'Bytespider' => 'Bytespider',
        'CensysInspect' => 'CensysInspect',
        'Centurybot' => 'Century',
        'ChatGLM-Spider' => 'ChatGLM',
        'ChatGPT-User' => 'ChatGPT',
        'ClaudeBot' => 'ClaudeBot',
        'Discordbot' => 'Discord',
        'DotBot' => 'DotBot',
        'DuckDuckBot' => 'DuckDuckGo',
        'Expanse' => 'Expanse',
        'facebookexternalhit' => 'Facebook',
        'Friendly_Crawler' => 'FriendlyCrawler',
        'Googlebot' => 'Google',
        'GoogleOther' => 'GoogleOther',
        'Google-Read-Aloud' => 'Google-Read-Aloud',
        'GPTBot' => 'GPTBot',
        'ia_archiver' => 'Internet Archive Alexa',
        'ImagesiftBot' => 'Imagesift',
        'linkdexbot' => 'Linkdex',
        'Mail.RU_Bot' => 'Mail.RU',
        'meta-externalagent' => 'Meta',
        'mj12bot' => 'Majestic',
        'MojeekBot' => 'Mojeek',
        'OAI-SearchBot' => 'OpenAI',
        'ows.eu' => 'Owler',
        'PerplexityBot' => 'Perplexity',
        'PetalBot' => 'PetalBot',
        'Qwantbot' => 'Qwant',
        'SemrushBot' => 'Semrush',
        'SeznamBot' => 'Seznam',
        'Sogou web spider' => 'Sogou',
        'Teoma' => 'Ask.com',
        'TikTokSpider' => 'TikTok',
        'Turnitin' => 'Turnitin',
        'Twitterbot' => 'Twitter',
        'W3C_Validator' => 'W3C Validator',
        'WhatsApp' => 'WhatsApp',
        'Y!J-WSC' => 'Yahoo Japan',
        'yahoo! slurp' => 'Yahoo',
        'YandexBot' => 'Yandex',
    ];

    /**
     * @var array<string,mixed>
     */
    private array $changedData = [];

    private ?ModelsSession $session = null;

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
        $this->fetchSessionData($this->getPHPSessionValue('sid') ?? null);

        if ($this->get('is_bot')) {
            return null;
        }

        $userId = $this->getPHPSessionValue('uid');
        $uToken = $this->request->cookie('utoken');
        if ($userId === null && $uToken !== null) {
            $token = Token::selectOne($this->database, 'WHERE `token`=?', $uToken);

            if ($token !== null) {
                $this->setPHPSessionValue('uid', $token->uid);

                return $token->uid;
            }
        }

        return $userId ?? 0;
    }

    /**
     * @return array<string,mixed>
     */
    public function fetchSessionData(null|int|string $sid = null): void
    {
        $session = null;
        $botName = $this->getBotName();
        $sid = $botName ?? $sid;

        if ($sid) {
            $params = $botName
                ? [Database::WHERE_ID_EQUALS, $sid]
                : ['WHERE `id`=? AND `ip`=?', $sid, $this->ipAddress->asBinary()];

            $session = ModelsSession::selectOne($this->database, ...$params);
        }

        if ($session !== null) {
            $this->session = $session;
            $this->vars = $session->vars !== '' ? unserialize($session->vars) : [];
            return;
        }

        $this->createSession();
    }

    public function get(string $field): mixed
    {
        $value = $this->session->{$field};

        return $value ?? null;
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
        if (!$this->session) {
            return;
        }

        $this->session->{$field} = $value;
    }

    public function addVar(string $varName, mixed $value): void
    {
        if (
            isset($this->vars[$varName])
            && $this->vars[$varName] === $value
        ) {
            return;
        }


        $this->vars[$varName] = $value;
        $this->session->vars = serialize($this->vars);
    }

    public function deleteVar(string $varName): void
    {
        if (!isset($this->vars[$varName])) {
            return;
        }

        unset($this->vars[$varName]);
        $this->session->vars = serialize($this->vars);
    }

    public function getVar(string $varName): mixed
    {
        return $this->vars[$varName] ?? null;
    }

    public function act(?string $location = null): void
    {
        $this->set('last_action', $this->database->datetime(Carbon::now('UTC')->getTimestamp()));
        if (!$location) {
            return;
        }

        $this->set('location', $location);
    }

    public function erase(string $fieldName): void
    {
        unset($this->changedData[$fieldName]);
    }

    public function clean(?int $uid): bool
    {
        $timeago = Carbon::now('UTC')->getTimestamp() - $this->config->getSetting('timetologout');
        if (!is_numeric($uid) || $uid < 1) {
            $uid = null;
        } else {
            $result = $this->database->select(
                'UNIX_TIMESTAMP(MAX(`last_action`)) AS `last_action`',
                'session',
                'WHERE `uid`=? GROUP BY `uid`',
                $uid,
            );
            $lastAction = $this->database->arow($result);
            $this->database->disposeresult($result);

            $this->database->delete(
                'session',
                'WHERE `uid`=? AND `last_update`<?',
                $uid,
                $this->database->datetime($timeago),
            );
            // Delete all expired tokens as well while we're here...
            $this->database->delete(
                'tokens',
                'WHERE `expires`<=?',
                $this->database->datetime(),
            );
            if ($lastAction) {
                $this->set('read_date', $lastAction['last_action']);
            }
        }

        $yesterday = mktime(0, 0, 0) ?: 0;
        $sessions = ModelsSession::selectMany(
            $this->database,
            'WHERE `last_update`<? GROUP BY uid',
            $this->database->datetime($yesterday),
        );

        foreach ($sessions as $session) {
            if (!$session->uid) {
                continue;
            }

            $this->database->update(
                'members',
                [
                    'last_visit' => $session->last_action,
                ],
                Database::WHERE_ID_EQUALS,
                $session->uid,
            );
        }

        $this->database->delete(
            'session',
            <<<'SQL'
                WHERE `last_update`<?
                    OR (`uid` IS NULL AND `last_update`<?)
                SQL,
            $this->database->datetime($yesterday),
            $this->database->datetime($timeago),
        );

        return true;
    }

    public function applyChanges(): void
    {
        $session = $this->session;
        $session->last_update = $this->database->datetime();

        if ($this->session->is_bot) {
            // Bots tend to read a lot of content.
            $session->forumsread = '{}';
            $session->topicsread = '{}';
        }

        if (!$this->session->last_action) {
            $session->last_action = $this->database->datetime();
        }

        if (isset($session->user)) {
            // This doesn't exist.
            unset($session->user);
        }

        if (mb_strlen($session->location_verbose ?? '') > 100) {
            $session->location_verbose = mb_substr(
                (string) $session->location_verbose,
                0,
                100,
            );
        }

        // Only update if there's data to update.
        $this->session->update($this->database);
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

    /**
     * @param array<string> $match
     */
    public function addSessIDCB(array $match): string
    {
        if ($match[1][0] === '?') {
            $match[1] .= '&amp;sessid=' . $this->session->id;
        }

        return 'href="' . $match[1] . '"';
    }

    private function createSession()
    {
        $botName = $this->getBotName();
        $sid = $botName;

        if (!$sid) {
            $sid = base64_encode(openssl_random_pseudo_bytes(128));
            $this->setPHPSessionValue('sid', $sid);
        }

        $actionTime = $this->database->datetime();

        $session = new ModelsSession();
        $session->id = $sid;
        $session->ip = $this->ipAddress->asBinary();
        $session->is_bot = $botName !== null ? 1 : 0;
        $session->last_action = $actionTime;
        $session->last_update = $actionTime;
        $session->useragent = $this->request->getUserAgent();
        $session->insert($this->database);
        $uid = $this->user->get('id');
        if ($uid) {
            $session->uid = $uid;
        }
        $session->insert($this->database);

        $this->session = $session;
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
