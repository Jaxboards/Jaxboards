<?php

declare(strict_types=1);

namespace Tools;

/*
 * Fetch the greater version property between two json files.
 *
 * USAGE:
 * ```sh
 * <script.php> <first.json> <second.json>
 * ```
 */

final class GreaterVersion implements CLIRoute
{
    #[\Override]
    public function route(array $params): void
    {
        $input1 = file_get_contents($params[0] ?? '');
        $version1 = '0';
        if ($input1 !== false) {
            /** @var string $version1 */
            $version1 =
                json_decode(
                    $input1,
                    null,
                    // Default
                    512,
                    JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
                )['version']
                ?? '0';
        }

        $input2 = file_get_contents($params[1] ?? '');
        $version2 = '0';
        if ($input2 !== false) {
            /** @var string $version2 */
            $version2 =
                json_decode(
                    $input2,
                    null,
                    // Default
                    512,
                    JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
                )['version']
                ?? '0';
        }

        if (version_compare($version1, $version2, '>=')) {
            echo $version1;

            exit(0);
        }

        echo $version2;
    }
}
