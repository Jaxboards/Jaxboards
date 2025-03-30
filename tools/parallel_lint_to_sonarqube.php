#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Tool to convert parallel-lint output into something SonarQube can read.
 *
 * @see https://docs.sonarsource.com/sonarqube-cloud/enriching/generic-issue-data/
 * @see https://community.sonarsource.com/t/how-can-i-include-php-codesniffer-and-mess-detector-ruleset-or-report/47776/4
 * @see https://community.sonarsource.com/t/how-can-i-include-php-codesniffer-and-mess-detector-ruleset-or-report/47776/8
 *
 * USAGE:
 * ```sh
 * parallel_lint_to_sonarqube.php <input.json> <output.json>
 * ```
 */

// Fetch CLI arguments.
$parallelLintReport = $argv[1] ?? '';

if ($parallelLintReport === '') {
    fwrite(
        STDERR,
        'Please enter the path to your parallel-lint report json file',
    );

    exit(1);
}

$sonarQubeReport = $argv[2] ?? '';

if ($sonarQubeReport === '') {
    fwrite(
        STDERR,
        'Please enter the path to where to save your SonarQube report json '
        . 'file',
    );

    exit(1);
}

// Validate CLI arguments are usable
if (!file_exists($parallelLintReport)) {
    fwrite(STDERR, 'Provided parallel-lint report json file does not exist');

    exit(1);
}

if (!is_readable($parallelLintReport)) {
    fwrite(STDERR, 'Provided parallel-lint report json file is not readable');

    exit(1);
}

if (file_exists($sonarQubeReport) && !is_writable($sonarQubeReport)) {
    fwrite(
        STDERR,
        'SonarQube report file already exists and is not writable',
    );

    exit(1);
}

if (
    !file_exists($sonarQubeReport)
    && !is_writable(dirname($sonarQubeReport))
) {
    fwrite(
        STDERR,
        'SonarQube report file directory is not writable',
    );

    exit(1);
}

$dataJSON = file_get_contents($parallelLintReport);
if ($dataJSON === false) {
    $dataJSON = '';
}
$data = json_decode(
    $dataJSON,
    null,
    // default
    512,
    JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
);

if (!is_array($data['results']['errors'] ?? null)) {
    fwrite(
        STDERR,
        'Provided parallel-lint report json file does not have '
        . '`results`.`errors` array',
    );

    exit(1);
}

$rules = array_reduce(
    $data['results']['errors'],
    static function (array $rules, array $error): array {
        if (array_key_exists($error['normalizeMessage'], $rules)) {
            return $rules;
        }

        $rules[$error['normalizeMessage']] = [
            'cleanCodeAttribute' => 'LOGICAL',
            'description' => $error['normalizeMessage'],
            'engineId' => 'php',
            'id' => $error['normalizeMessage'],
            'impacts' => [
                [
                    'severity' => 'BLOCKER',
                    'softwareQuality' => 'RELIABILITY',
                ],
            ],
            'name' => $error['normalizeMessage'],
            'severity' => 'BLOCKER',
            'type' => 'BUG',
        ];

        return $rules;
    },
    [],
);

file_put_contents(
    $sonarQubeReport,
    json_encode(
        [
            'issues' => array_map(
                static fn(array $error): array => [
                    'engineId' => 'php',
                    'primaryLocation' => [
                        'filePath' => $error['file'],
                        'message' => $error['message'],
                        'textRange' => [
                            'startLine' => (string) $error['line'],
                        ],
                    ],
                    'ruleId' => $error['normalizeMessage'],
                ],
                $data['results']['errors'],
            ),
            'rules' => array_values($rules),
        ],
        JSON_THROW_ON_ERROR,
    ),
    LOCK_EX,
);
