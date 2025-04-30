<?php

declare(strict_types=1);

namespace Jax;

use function array_keys;
use function array_reduce;
use function count;
use function floor;
use function json_decode;
use function mail;
use function pack;
use function str_replace;
use function unpack;

use const PHP_EOL;

final readonly class Jax
{
    public const FORUM_PERMS_ORDER = ['upload', 'reply', 'start', 'read', 'view', 'poll'];

    public function __construct(
        private Config $config,
        private DomainDefinitions $domainDefinitions,
    ) {}

    /**
     * @param array<string,string> $fields
     */
    public function hiddenFormFields(array $fields): string
    {
        $html = '';
        foreach ($fields as $key => $value) {
            $html .= "<input type='hidden' name='{$key}' value='{$value}'>";
        }

        return $html;
    }

    public function parsereadmarkers(?string $readmarkers)
    {
        if ($readmarkers) {
            return json_decode($readmarkers, true) ?? [];
        }

        return [];
    }

    public function serializeForumPerms(array $forumPerms): string
    {
        $packed = '';
        foreach ($forumPerms as $groupId => $groupPerms) {
            $flag = 0;
            foreach (self::FORUM_PERMS_ORDER as $index => $field) {
                $flag += $groupPerms[$field] ?? 0 ? 1 << $index : 0;
            }
            $packed .= pack('n*', $groupId, $flag);
        }

        return $packed;
    }

    /**
     * @return array<int,array<string,bool>>
     */
    public function parseForumPerms(string $forumPerms): array
    {
        $unpack = unpack('n*', $forumPerms);
        $counter = count($unpack);
        $parsedPerms = [];
        for ($index = 1; $index < $counter; $index += 2) {
            $groupId = $unpack[$index];
            $flag = $unpack[$index + 1];

            $parsedPerms[$groupId] = array_reduce(
                array_keys(self::FORUM_PERMS_ORDER),
                static function ($perms, $key) use ($flag) {
                    $perms[self::FORUM_PERMS_ORDER[$key]] = (bool) ($flag & (1 << $key));

                    return $perms;
                },
                [],
            );
        }

        return $parsedPerms;
    }

    public function pages(int $numpages, int $active, int $tofill)
    {
        $tofill -= 2;
        $pages[] = 1;
        if ($numpages === 1) {
            return $pages;
        }

        $start = $active - floor($tofill / 2);
        if ($numpages - $start < $tofill) {
            $start -= $tofill - ($numpages - $start);
        }

        if ($start <= 1) {
            $start = 2;
        }

        for ($x = 0; $x < $tofill && ($start + $x) < $numpages; ++$x) {
            $pages[] = $x + $start;
        }

        $pages[] = $numpages;

        return $pages;
    }

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     *
     * @param mixed $email
     * @param mixed $topic
     * @param mixed $message
     */
    public function mail(string $email, string $topic, string $message): bool
    {
        $boardname = $this->config->getSetting('boardname') ?: 'JaxBoards';
        $boardurl = $this->domainDefinitions->getBoardURL();
        $boardlink = "<a href='{$boardurl}'>{$boardname}</a>";

        return mail(
            $email,
            $boardname . ' - ' . $topic,
            str_replace(
                ['{BOARDNAME}', '{BOARDURL}', '{BOARDLINK}'],
                [$boardname, $boardurl, $boardlink],
                $message,
            ),
            'MIME-Version: 1.0' . PHP_EOL
            . 'Content-type:text/html;charset=iso-8859-1' . PHP_EOL
            . 'From: ' . $this->config->getSetting('mail_from') . PHP_EOL,
        );
    }
}
