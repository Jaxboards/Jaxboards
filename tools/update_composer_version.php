#!/usr/bin/env php
<?php

// Update our composer version to the latest available.
declare(strict_types=1);

const COMPOSER_VERSIONS_URL = 'https://getcomposer.org/versions';

const COULD_NOT_READ = 'Could not read ';

$versionJSON = file_get_contents(COMPOSER_VERSIONS_URL);

if ($versionJSON === false) {
    fwrite(STDERR, COULD_NOT_READ . COMPOSER_VERSIONS_URL);

    exit(1);
}

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
    fwrite(STDERR, COULD_NOT_READ . COMPOSER_FILE);

    exit(1);
}

$composerData = json_decode(
    $composerJSON,
    null,
    // Default
    512,
    JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
);

$composerData['config']['platform']['composer'] = $version;
ksort($composerData['config']['platform']);
$composerData['require-dev']['composer'] = $version;
ksort($composerData['require-dev']);

file_put_contents(
    COMPOSER_FILE,
    json_encode(
        $composerData,
        JSON_PRETTY_PRINT,
    ),
);

define('PACKAGE_FILE', dirname(__DIR__) . '/package.json');

$packageJSON = file_get_contents(PACKAGE_FILE);

if ($packageJSON === false) {
    fwrite(STDERR, COULD_NOT_READ . PACKAGE_FILE);

    exit(1);
}

$packageData = json_decode(
    $packageJSON,
    null,
    // Default
    512,
    JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
);

$packageData['engines']['composer'] = $version;
ksort($packageData['engines']);

file_put_contents(
    PACKAGE_FILE,
    json_encode(
        $packageData,
        JSON_PRETTY_PRINT,
    ),
);
