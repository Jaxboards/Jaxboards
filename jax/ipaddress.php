<?php

declare(strict_types=1);

namespace Jax;

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

/**
 * @psalm-api
 */
final readonly class IPAddress
{
    public function __construct(
        private Config $config,
        private Database $database,
    ) {}

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

    public function isBanned(?string $ipAddress = null): bool
    {
        static $ipBanCache = null;

        if (!$ipAddress) {
            $ipAddress = self::getIp();
        }

        if (!$ipBanCache) {
            $ipBanCache = [];

            if (file_exists(BOARDPATH . '/bannedips.txt')) {
                foreach (file(BOARDPATH . '/bannedips.txt') as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }

                    $ipBanCache[] = $line;
                }
            }
        }

        foreach ($ipBanCache as $bannedIp) {
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
            <<<'EOT'
                SELECT COUNT(`ip`) as `banned`
                    FROM `banlist`
                    WHERE ip = ?
                EOT
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
