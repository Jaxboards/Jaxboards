<?php

declare(strict_types=1);

namespace Tools;

use Jax\FileSystem;
use Override;

final class ComposerVersion implements CLIRoute
{
    const string COMPOSER_VERSIONS_URL = 'https://getcomposer.org/versions';

    public function __construct(
        private FileSystem $fileSystem
    ) {}

    #[Override]
    public function route(array $params): void
    {
        match ($params[0] ?? '') {
            'update' => $this->update(),
            default => $this->get_current_version(),
        };
    }

    private function get_current_version(): void
    {
        echo $this->get_package_json()['engines']['composer'] ?? null;
    }

    /**
     * @return array{engines:array{composer:?string}}
     */
    private function get_package_json(): array
    {
        $packageJSON = $this->fileSystem->getContents('package.json');
        if ($packageJSON === '') {
            fwrite(STDERR, 'Could not read package.json');

            exit(1);
        }

        /** @var array{engines:array{composer:?string}} */
        return json_decode(
            $packageJSON,
            null,
            // Default
            512,
            JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Update our composer version to the latest available.
     */
    private function update(): void
    {
        $versionJSON = $this->fileSystem->getContents(self::COMPOSER_VERSIONS_URL);

        if ($versionJSON === '') {
            fwrite(STDERR, 'Could not read ' . self::COMPOSER_VERSIONS_URL);

            exit(1);
        }

        /** @var array{stable:array<array{version:string}>} */
        $versions = json_decode(
            $versionJSON,
            null,
            // Default
            512,
            JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
        );

        $version = $versions['stable'][0]['version'] ?? null;

        if ($version === null) {
            error_log('Could not retrieve composer version' . PHP_EOL);

            exit(1);
        }

        $composerJSON = $this->fileSystem->getContents('composer.json');

        if ($composerJSON === '') {
            error_log('Could not read composer.json');

            exit(1);
        }

        /** @var array{require:array{composer:string},config:array{platform:array{composer:string}},require:array<string>,require-dev:array<string>} $composerData */
        $composerData = json_decode(
            $composerJSON,
            null,
            // Default
            512,
            JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
        );

        $composerData['config']['platform']['composer'] = $version;
        ksort($composerData['config']['platform']);
        $composerData['require']['composer'] = $version;
        ksort($composerData['require']);
        ksort($composerData['require-dev']);

        $this->fileSystem->putContents('composer.json', json_encode($composerData, JSON_PRETTY_PRINT));

        $packageData = $this->get_package_json();

        $packageData['engines']['composer'] = $version;
        ksort($packageData['engines']);

        $this->fileSystem->putContents('package.json', json_encode($packageData, JSON_PRETTY_PRINT));
    }
}
