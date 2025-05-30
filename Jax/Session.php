<?php

declare(strict_types=1);

namespace Jax;

use Carbon\Carbon;
use Jax\Models\Session as ModelsSession;
use Jax\Models\Token;

use function array_key_exists;
use function base64_encode;
use function ini_set;
use function is_numeric;
use function mb_strlen;
use function mb_substr;
use function mktime;
use function openssl_random_pseudo_bytes;
use function preg_replace_callback;
use function serialize;
use function session_start;
use function unserialize;

final class Session
{
    /**
     * @var array<string,mixed>
     */
    private array $vars = [];

    /**
     * @var array<string,mixed>
     */
    private array $changedData = [];

    private ModelsSession $modelsSession;

    public function __construct(
        private readonly Config $config,
        private readonly BotDetector $botDetector,
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

        // This is only here so that the field is never null
        $this->modelsSession = new ModelsSession();
    }

    public function loginWithToken(): ?int
    {
        $this->fetchSessionData($this->getPHPSessionValue('sid') ?? null);

        if ($this->modelsSession->isBot !== 0) {
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

    public function fetchSessionData(null|int|string $sid = null): void
    {
        $botName = $this->botDetector->getBotName();
        $sid = $botName ?? $sid;

        $session = null;
        if ($sid) {
            $params = $botName
                ? [Database::WHERE_ID_EQUALS, $sid]
                : ['WHERE `id`=? AND `ip`=?', $sid, $this->ipAddress->asBinary()];

            $session = ModelsSession::selectOne($this->database, ...$params);
        }

        if ($session !== null) {
            $this->modelsSession = $session;
            $this->vars = unserialize($session->vars) ?: [];

            return;
        }

        $this->createSession();
    }

    public function get(): ModelsSession
    {
        return $this->modelsSession;
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
        $this->modelsSession->{$field} = $value;
        $this->changedData[$field] = $value;
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
        $this->set('vars', serialize($this->vars));
    }

    public function deleteVar(string $varName): void
    {
        if (!array_key_exists($varName, $this->vars)) {
            return;
        }

        unset($this->vars[$varName]);
        $this->set('vars', serialize($this->vars));
    }

    public function getVar(string $varName): mixed
    {
        return $this->vars[$varName] ?? null;
    }

    public function act(?string $location = null): void
    {
        $this->set('lastAction', $this->database->datetime(Carbon::now('UTC')->getTimestamp()));
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
        $timeago = Carbon::now('UTC')->subSeconds($this->config->getSetting('timetologout') ?? 900)->getTimestamp();
        if (!is_numeric($uid) || $uid < 1) {
            $uid = null;
        } else {
            $result = $this->database->select(
                'MAX(`lastAction`) AS `lastAction`',
                'session',
                'WHERE `uid`=?',
                $uid,
            );
            $lastAction = $this->database->arow($result);
            $this->database->disposeresult($result);

            $this->database->delete(
                'session',
                'WHERE `uid`=? AND `lastUpdate`<?',
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
                $this->set('readDate', $lastAction['lastAction']);
            }
        }

        $yesterday = mktime(0, 0, 0) ?: 0;
        $sessions = ModelsSession::selectMany(
            $this->database,
            'WHERE `lastUpdate`<?',
            $this->database->datetime($yesterday),
        );

        foreach ($sessions as $session) {
            if (!$session->uid) {
                continue;
            }

            $this->database->update(
                'members',
                [
                    'lastVisit' => $session->lastAction,
                ],
                Database::WHERE_ID_EQUALS,
                $session->uid,
            );
        }

        $this->database->delete(
            'session',
            <<<'SQL'
                WHERE `lastUpdate`<?
                    OR (`uid` IS NULL AND `lastUpdate`<?)
                SQL,
            $this->database->datetime($yesterday),
            $this->database->datetime($timeago),
        );

        return true;
    }

    public function applyChanges(): void
    {
        $this->set('lastUpdate', $this->database->datetime());

        $changedData = $this->changedData;

        if ($this->modelsSession->isBot !== 0) {
            // Bots tend to read a lot of content.
            $changedData['forumsread'] = '{}';
            $changedData['topicsread'] = '{}';
        }

        if (
            $this->modelsSession->lastAction === null
        ) {
            $changedData['lastAction'] = $this->database->datetime();
        }

        if (mb_strlen($this->modelsSession->locationVerbose) > 100) {
            $changedData['locationVerbose'] = mb_substr(
                $this->modelsSession->locationVerbose,
                0,
                100,
            );
        }

        // Only update if there's data to update.
        if ($changedData === []) {
            return;
        }

        $this->database->update(
            'session',
            $changedData,
            Database::WHERE_ID_EQUALS,
            $this->modelsSession->id,
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

    /**
     * @param array<string> $match
     */
    public function addSessIDCB(array $match): string
    {
        if ($match[1][0] === '?') {
            $match[1] .= '&amp;sessid=' . $this->modelsSession->id;
        }

        return 'href="' . $match[1] . '"';
    }

    private function createSession(): void
    {
        $botName = $this->botDetector->getBotName();
        $sid = $botName;

        if (!$sid) {
            $sid = base64_encode(openssl_random_pseudo_bytes(128));
            $this->setPHPSessionValue('sid', $sid);
        }

        $actionTime = $this->database->datetime();

        $session = new ModelsSession();
        $session->id = $sid;
        $session->ip = $this->ipAddress->asBinary() ?? '';
        $session->isBot = $botName !== null ? 1 : 0;
        $session->lastAction = $actionTime;
        $session->lastUpdate = $actionTime;
        $session->useragent = $this->request->getUserAgent() ?? '';

        $uid = $this->user->get()->id;
        if ($uid !== 0) {
            $session->uid = $uid;
        }

        $session->insert($this->database);

        $this->modelsSession = $session;
    }
}
