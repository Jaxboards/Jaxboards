<?php

declare(strict_types=1);

namespace Tools;

use Jax\FileSystem;
use Override;

/**
 * Fetch the greater version property between two json files.
 *
 * Usage:
 * - greater-version <first.json> <second.json>
 */

final readonly class GreaterVersion implements CLIRoute
{
    public function __construct(
        private Console $console,
        private FileSystem $fileSystem,
    ) {}

    /**
     * @param array<string> $params
     */
    #[Override]
    public function route(array $params): void
    {
        if (count($params) < 2) {
            $this->console->error('Two files required to compare');
            exit(1);
        }

        $version1 = $this->get_version($params[0] ?? '');
        $version2 = $this->get_version($params[1] ?? '');

        if (version_compare($version1, $version2, '>=')) {
            echo $version1;

            exit(0);
        }

        echo $version2;
    }

    private function get_version(string $file): string
    {
        $input1 = $this->fileSystem->getContents($file);
        if ($input1 !== '') {
            /** @var array{version:?string} $json */
            $json = json_decode(
                $input1,
                null,
                // Default
                512,
                JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
            );

            return $json['version'] ?? '0';
        }

        return '0';
    }
}
