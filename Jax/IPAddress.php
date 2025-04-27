<?php

declare(strict_types=1);

namespace Jax;

use function array_search;
use function file;
use function file_exists;
use function filter_var;
use function inet_ntop;
use function inet_pton;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function pack;
use function trim;

use const FILTER_VALIDATE_IP;

final class IPAddress
{
    public $jax;

    private ?array $ipBanCache = null;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly DomainDefinitions $domainDefinitions,
    ) {
        $this->getBannedIps();
    }

    public function asBinary(?string $ipAddress = null): false|string
    {
        if (!$ipAddress) {
            $ipAddress = self::getIp();
        }

        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            // Not an IP, so don't need to send anything back.
            return '';
        }

        return inet_pton($ipAddress);
    }

    // This comment suggests MySQL's aton is different from php's pton, so
    // we need to do something for mysql IP addresses:
    // https://secure.php.net/manual/en/function.inet-ntop.php#117398
    // .
    public function asHumanReadable(?string $ipAddress = null): string
    {
        if (!$ipAddress) {
            return self::getIp();
        }

        $length = mb_strlen($ipAddress);

        return (inet_ntop($ipAddress) ?: inet_ntop(pack('A' . $length, $ipAddress))) ?: '';
    }

    public function ban(string $ipAddress): void
    {
        $this->ipBanCache[] = $ipAddress;
    }

    public function unBan(string $ipAddress): void
    {
        unset($this->ipBanCache[array_search($ipAddress, $this->ipBanCache, true)]);
    }

    public function isBanned(?string $ipAddress = null): bool
    {
        if (!$ipAddress) {
            $ipAddress = self::getIp();
        }

        foreach ($this->ipBanCache as $bannedIp) {
            if (
                (mb_substr((string) $bannedIp, -1) === ':' || mb_substr((string) $bannedIp, -1) === '.')
                && mb_strtolower(mb_substr($ipAddress, 0, mb_strlen((string) $bannedIp))) === $bannedIp
            ) {
                return true;
            }

            if ($bannedIp === $ipAddress) {
                return true;
            }
        }


        return false;
    }

    public function getBannedIps()
    {
        if ($this->ipBanCache !== null) {
            return $this->ipBanCache;
        }

        $this->ipBanCache = [];

        $boardPath = $this->domainDefinitions->getBoardPath();
        if (file_exists($boardPath . '/bannedips.txt')) {
            foreach (file($boardPath . '/bannedips.txt') as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $this->ipBanCache[] = $line;
            }
        }

        return $this->ipBanCache;
    }

    /**
     * Check if an IP is banned from the service.
     * Will use the $this->getIp() ipAddress field is left empty.
     *
     * @param string $ipAddress the IP Address to check
     *
     * @return bool if the IP is banned form the service or not
     */
    public function isServiceBanned(?string $ipAddress = null): bool
    {
        if (!$this->config->getSetting('service')) {
            // Can't be service banned if there's no service.
            return false;
        }

        if (!$ipAddress) {
            $ipAddress = self::getIp();
        }

        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT COUNT(`ip`) as `banned`
                    FROM `banlist`
                    WHERE ip = ?
                SQL
            ,
            [],
            $this->database->basicvalue(self::asBinary($ipAddress)),
        );
        $row = $this->database->arow($result);
        $this->database->disposeresult($result);

        return !isset($row['banned']) || $row['banned'] > 0;
    }

    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private function getIp(): string
    {
        return $_SERVER['REMOTE_ADDR'];
    }
}
