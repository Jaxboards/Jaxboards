<?php

declare(strict_types=1);

namespace Jax;

use function array_keys;
use function array_reduce;
use function count;
use function floor;
use function pack;
use function unpack;


final readonly class Jax
{
    public const array FORUM_PERMS_ORDER = ['upload', 'reply', 'start', 'read', 'view', 'poll'];

    public const array IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];

    /**
     * @param array<int,array<string,bool>> $forumPerms
     */
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
        $unpack = unpack('n*', $forumPerms) ?: [];
        $counter = count($unpack);
        $parsedPerms = [];
        for ($index = 1; $index < $counter; $index += 2) {
            $groupId = $unpack[$index];
            $flag = $unpack[$index + 1];

            $parsedPerms[$groupId] = array_reduce(
                array_keys(self::FORUM_PERMS_ORDER),
                static function (array $perms, int $key) use ($flag): array {
                    $perms[self::FORUM_PERMS_ORDER[$key]] = (bool) ($flag & (1 << $key));

                    return $perms;
                },
                [],
            );
        }

        return $parsedPerms;
    }

    /**
     * Given the total pages, active page, and number of links you want
     * Returns an array that always has the first and last page, with the active page in the middle.
     *
     * @return array<int>
     */
    public function pages(int $numpages, int $active, int $tofill): array
    {
        if ($numpages <= 0) {
            return [];
        }

        $tofill -= 2;
        $pages = [1];
        if ($numpages === 1) {
            return $pages;
        }

        $start = $active - (int) floor($tofill / 2);
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
}
