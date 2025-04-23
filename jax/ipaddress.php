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

final readonly class IPAddress
{
    public function __construct(private Config $config) {}

    public function asBinary($ip = null): false|string
    {
        if (!$ip) {
            $ip = self::getIp();
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            // Not an IP, so don't need to send anything back.
            return '';
        }

        return inet_pton($ip);
    }

    // This comment suggests MySQL's aton is different from php's pton, so
    // we need to do something for mysql IP addresses:
    // https://secure.php.net/manual/en/function.inet-ntop.php#117398
    // .
    public function asHumanReadable(?string $ip = null): string
    {
        if (!$ip) {
            return self::getIp();
        }

        $length = mb_strlen($ip);

        return (inet_ntop($ip) ?: inet_ntop(pack('A' . $length, $ip))) ?: '';
    }

    public function isBanned($ip = false): false|string
    {
        $ipbancache = null;

        if (!$ip) {
            $ip = self::getIp();
        }

        $ipbancache = [];
        if (file_exists(BOARDPATH . '/bannedips.txt')) {
            foreach (file(BOARDPATH . '/bannedips.txt') as $v) {
                $v = trim($v);
                if ($v === '') {
                    continue;
                }

                $ipbancache[] = $v;
            }
        }

        foreach ($ipbancache as $v) {
            if (
                (mb_substr($v, -1) === ':' || mb_substr($v, -1) === '.')
                && mb_strtolower(mb_substr((string) $ip, 0, mb_strlen($v))) === $v
            ) {
                return $v;
            }

            if ($v === $ip) {
                return $v;
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
        global $DB,$JAX;

        if (!$this->config->getSetting('service')) {
            // Can't be service banned if there's no service.
            return false;
        }

        if (!$ipAddress) {
            $ipAddress = self::getIp();
        }

        $result = $DB->safespecial(
            <<<'EOT'
                SELECT COUNT(`ip`) as `banned`
                    FROM `banlist`
                    WHERE ip = ?
                EOT
            ,
            [],
            $DB->basicvalue(self::asBinary($ipAddress)),
        );
        $row = $DB->arow($result);
        $DB->disposeresult($result);

        return !isset($row['banned']) || $row['banned'] > 0;
    }

    private function getIp()
    {
        global $_SERVER;

        return $_SERVER['REMOTE_ADDR'];
    }
}
