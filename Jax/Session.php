<?php

declare(strict_types=1);

namespace Jax;

use Carbon\Carbon;
use DOMDocument;
use Jax\Database\Database;
use Jax\Models\Session as ModelsSession;
use Jax\Models\Token;

use function array_key_exists;
use function ini_set;
use function is_numeric;
use function json_decode;
use function json_encode;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function mb_strlen;
use function mb_substr;
use function mktime;
use function session_id;
use function session_start;
use function str_contains;
use function str_starts_with;
use function unserialize;

use const JSON_THROW_ON_ERROR;

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

    /**
     * @param array<mixed> $session
     */
    public function __construct(
        private readonly Config $config,
        private readonly BotDetector $botDetector,
        private readonly IPAddress $ipAddress,
        private readonly Database $database,
        private readonly Request $request,
        private readonly User $user,
        private ?array $session = null,
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
        $this->fetchSessionData(session_id() ?? $this->request->get('sessid') ?? null);

        if ($this->modelsSession->isBot !== 0) {
            return null;
        }

        $userId = $this->getPHPSessionValue('uid');
        $uToken = $this->request->cookie('utoken');
        if ($userId === null && $uToken !== null) {
            $token = Token::selectOne('WHERE `token`=?', $uToken);

            if ($token !== null) {
                $this->setPHPSessionValue('uid', $token->uid);

                return $token->uid;
            }
        }

        return $userId ?? 0;
    }

    public function fetchSessionData(int|string|null $sid = null): void
    {
        $botName = $this->botDetector->getBotName();
        $sid = $botName ?? $sid;

        $session = null;
        if ($sid) {
            $params = $botName
                ? [Database::WHERE_ID_EQUALS, $sid]
                : ['WHERE `id`=? AND `ip`=?', $sid, $this->ipAddress->asBinary()];

            $session = ModelsSession::selectOne(...$params);
        }

        if ($session !== null) {
            $this->modelsSession = $session;
            // This str_starts_with can be removed after a time.
            // Only exists to replace serialize with json_encode
            $this->vars = (
                str_starts_with($session->vars, '{')
                ? json_decode($session->vars, true, flags: JSON_THROW_ON_ERROR)
                : unserialize($session->vars)
            ) ?: [];

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
        return $this->session[$field] ?? $_SESSION[$field] ?? null;
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
            array_key_exists($varName, $this->vars)
            && $this->vars[$varName] === $value
        ) {
            return;
        }

        $this->vars[$varName] = $value;
        $this->set('vars', json_encode($this->vars, JSON_THROW_ON_ERROR));
    }

    public function deleteVar(string $varName): void
    {
        if (!array_key_exists($varName, $this->vars)) {
            return;
        }

        unset($this->vars[$varName]);
        $this->set('vars', json_encode($this->vars, JSON_THROW_ON_ERROR));
    }

    public function getVar(string $varName): mixed
    {
        return $this->vars[$varName] ?? $this->getPHPSessionValue($varName) ?? null;
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

        libxml_use_internal_errors(true);
        $domDocument = new DOMDocument();
        $domDocument->loadHTML($html);

        $domNodeList = $domDocument->getElementsByTagName('a');

        foreach ($domNodeList as $link) {
            $href = $link->getAttribute('href');
            $separator = str_contains($href, '?') ? '&' : '?';
            if (str_starts_with($href, '/')) {
                $href .= "{$separator}sessid={$this->modelsSession->id}";
            }

            if ($href === '') {
                continue;
            }

            $link->setAttribute('href', $href);
        }

        libxml_clear_errors();

        return $domDocument->saveHTML();
    }

    private function createSession(): void
    {
        $botName = $this->botDetector->getBotName();
        $sid = $botName;

        if (!$sid) {
            $sid = session_id();
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

        $session->insert();

        $this->modelsSession = $session;
    }
}
