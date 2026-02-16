<?php

declare(strict_types=1);

namespace Tools;

use Override;

final class ComposerVersion implements CLIRoute
{
    const string COMPOSER_VERSIONS_URL = 'https://getcomposer.org/versions';

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
        // Fetch the composer version for use in our pre-commit hook.
        define('PACKAGE_FILE', dirname(__DIR__) . '/package.json');
        $packageJSON = file_get_contents(PACKAGE_FILE);
        if ($packageJSON === false) {
            fwrite(STDERR, 'Could not read ' . PACKAGE_FILE);

            exit(1);
        }

        /** @var array{engines:array{composer:?string}} $packageData */
        $packageData = json_decode(
            $packageJSON,
            null,
            // Default
            512,
            JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
        );
        echo $packageData['engines']['composer'] ?? null;
    }

    /**
     * Update our composer version to the latest available.
     */
    private function update(): void
    {
        $versionJSON = file_get_contents(self::COMPOSER_VERSIONS_URL);

        if ($versionJSON === false) {
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
            fwrite(STDERR, 'Could not retrieve composer version' . PHP_EOL);

            exit(1);
        }

        define('COMPOSER_FILE', dirname(__DIR__) . '/composer.json');

        $composerJSON = file_get_contents(COMPOSER_FILE);

        if ($composerJSON === false) {
            fwrite(STDERR, 'Could not read ' . COMPOSER_FILE);

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

        file_put_contents(COMPOSER_FILE, json_encode($composerData, JSON_PRETTY_PRINT));

        define('PACKAGE_FILE', dirname(__DIR__) . '/package.json');

        $packageJSON = file_get_contents(PACKAGE_FILE);

        if ($packageJSON === false) {
            fwrite(STDERR, 'Could not read ' . PACKAGE_FILE);

            exit(1);
        }

        /** @var array{engines:array{composer:?string}} $packageData */
        $packageData = json_decode(
            $packageJSON,
            null,
            // Default
            512,
            JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
        );

        $packageData['engines']['composer'] = $version;
        ksort($packageData['engines']);

        file_put_contents(PACKAGE_FILE, json_encode($packageData, JSON_PRETTY_PRINT));
    }
}
