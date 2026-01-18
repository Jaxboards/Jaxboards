<?php

declare(strict_types=1);

namespace Jax;

use Jax\Models\Service\Banlist;

use function array_any;
use function array_filter;
use function array_search;
use function filter_var;
use function implode;
use function in_array;
use function inet_ntop;
use function inet_pton;
use function mb_strlen;
use function mb_substr;
use function pack;
use function str_starts_with;

use const FILTER_VALIDATE_IP;
use const PHP_EOL;

final class IPAddress
{
    /**
     * @var array<string> list of human readable IPs that are banned
     */
    private array $ipBanCache = [];

    public function __construct(
        private readonly Config $config,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly FileSystem $fileSystem,
        private readonly Request $request,
    ) {
        $this->ipBanCache = $this->loadBannedIps();
    }

    public function asBinary(?string $ipAddress = null): ?string
    {
        if (!$ipAddress) {
            $ipAddress = self::getIp();
        }

        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            // Not an IP, so don't need to send anything back.
            return null;
        }

        return inet_pton($ipAddress) ?: null;
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

        return inet_ntop($ipAddress) ?:
            inet_ntop(pack('A' . $length, $ipAddress)) ?:
            '';
    }

    public function ban(string $ipAddress): void
    {
        if (!$this->isBanned($ipAddress)) {
            $this->ipBanCache[] = $ipAddress;
        }

        $this->writeBannedIps();
    }

    public function unBan(string $ipAddress): void
    {
        $index = array_search($ipAddress, $this->ipBanCache, true);
        if ($index === false) {
            return;
        }

        unset($this->ipBanCache[$index]);
        $this->writeBannedIps();
    }

    public function isBanned(?string $ipAddress = null): bool
    {
        $ipAddress ??= self::getIp();

        return array_any(
            $this->ipBanCache,
            static fn($bannedIp): bool => $bannedIp === $ipAddress ||
                (in_array(
                    mb_substr((string) $bannedIp, -1),
                    [':', '.'],
                    true,
                ) &&
                    str_starts_with($ipAddress, (string) $bannedIp)),
        );
    }

    public function isLocalHost(): bool
    {
        return in_array($this->asHumanReadable(), ['127.0.0.1', '::1'], true);
    }

    /**
     * @return array<string>
     */
    public function getBannedIps(): array
    {
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

        $binaryIp = self::asBinary($ipAddress);

        if (!$binaryIp) {
            // IP is somehow invalid so just assume they aren't banned
            return false;
        }

        $banlistCount = Banlist::count('WHERE `ipAddress`=?', $binaryIp);

        return $banlistCount > 0;
    }

    /**
     * @return array<string>
     */
    private function loadBannedIps(): array
    {
        $bannedIPsPath =
            $this->domainDefinitions->getBoardPath() . '/bannedips.txt';
        if ($this->fileSystem->getFileInfo($bannedIPsPath)->isFile()) {
            return array_filter(
                $this->fileSystem->getLines($bannedIPsPath) ?: [],
                // Filter out empty lines and comments
                static fn(string $line): bool => $line !== '' &&
                    $line[0] !== '#',
            );
        }

        return [];
    }

    private function writeBannedIps(): void
    {
        $this->fileSystem->putContents(
            $this->domainDefinitions->getBoardPath() . '/bannedips.txt',
            implode(PHP_EOL, $this->ipBanCache),
        );
    }

    private function getIp(): string
    {
        return $this->request->server('REMOTE_ADDR') ?? '';
    }
}
