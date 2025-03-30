#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Tool to convert PHP_CodeSniffer output into something SonarQube can read.
 *
 * @see https://docs.sonarsource.com/sonarqube-cloud/enriching/generic-issue-data/
 * @see https://community.sonarsource.com/t/how-can-i-include-php-codesniffer-and-mess-detector-ruleset-or-report/47776/4
 * @see https://community.sonarsource.com/t/how-can-i-include-php-codesniffer-and-mess-detector-ruleset-or-report/47776/8
 *
 * USAGE:
 * ```sh
 * php_codesniffer_to_sonarqube.php <input.json> <output.json>
 * ```
 */

// Fetch CLI arguments.
$phpCodeSnifferReport = $argv[1] ?? '';

if ($phpCodeSnifferReport === '') {
    fwrite(
        STDERR,
        'Please enter the path to your PHP_CodeSniffer report json file',
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
if (!file_exists($phpCodeSnifferReport)) {
    fwrite(STDERR, 'Provided PHP_CodeSniffer report json file does not exist');

    exit(1);
}

if (!is_readable($phpCodeSnifferReport)) {
    fwrite(STDERR, 'Provided PHP_CodeSniffer report json file is not readable');

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

$dataJSON = file_get_contents($phpCodeSnifferReport);
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

if (!is_array($data['files'])) {
    fwrite(
        STDERR,
        'Provided PHP_CodeSniffer report json file does not have `files` array',
    );

    exit(1);
}

$rules = array_reduce(
    $data['files'],
    static fn(array $rules, array $file): array => array_reduce(
        $file['messages'],
        static function (array $rules, array $message) {
            if (array_key_exists($message['source'], $rules)) {
                return $rules;
            }
            $description = preg_replace(
                '/ Currently using \d+ lines./',
                '',
                (string) $message['message'],
            ) ?? '';
            $description = preg_replace(
                '/Unused variable \$\w+/',
                'Unused variable',
                $description,
            ) ?? '';
            $description = preg_replace(
                '/Class name \w+ does not match filepath [\/\w\.]+/',
                'Class name does not match filepath',
                $description,
            ) ?? '';
            $description = preg_replace(
                '/Cognitive complexity for "\w+" is \d+ but has to be/',
                'Cognitive complexity must be',
                $description,
            ) ?? '';
            $description = preg_replace(
                '/; contains \d+ characters/',
                '',
                $description,
            ) ?? '';
            $description = preg_replace(
                '/Unused parameter \$\w+/',
                'Unused parameter',
                $description,
            ) ?? '';
            $description = preg_replace(
                '/Property \\\\\w+::\$\w+ does not have/',
                'Property does not have',
                $description,
            ) ?? '';
            $description = preg_replace(
                '/Method \\\\\w+::\w+\(\) does not have/',
                'Method does not have',
                $description,
            );

            // we don't have a way to guage this easily so we always set it to
            // low/minor for errors and info for warnings
            $impactSeverity = $message['type'] === 'ERROR'
                ? 'LOW'
                : 'INFO';
            $severity = $message['type'] === 'ERROR'
                ? 'MINOR'
                : 'INFO';

            $rules[$message['source']] = [
                'cleanCodeAttribute' => 'FORMATTED',
                'description' => $description,
                'engineId' => 'PHP_CodeSniffer',
                'id' => $message['source'],
                'impacts' => [
                    [
                        'severity' => $impactSeverity,
                        // we don't have a way to guage this easily so we
                        // always set it to mainability
                        'softwareQuality' => 'MAINTAINABILITY',
                    ],
                ],
                'name' => $message['source'],
                'severity' => $severity,
                'type' => 'CODE_SMELL',
            ];

            return $rules;
        },
        $rules,
    ),
    [],
);

file_put_contents(
    $sonarQubeReport,
    json_encode(
        array_reduce(
            array_keys($data['files']),
            static function (
                array $result,
                $file,
            ) use ($data): array {
                array_push(
                    $result['issues'],
                    ...array_map(
                        static fn(array $message): array => [
                            'engineId' => 'PHP_CodeSniffer',
                            'primaryLocation' => [
                                'filePath' => $file,
                                'message' => $message['message'],
                                'textRange' => [
                                    'startColumn'
                                        // SonarQube starts at 0 for columns
                                        // while PHP_CodeSniffer starts at 1
                                        => (string) (
                                            ((int) $message['column']) - 1
                                        ),
                                    'startLine' => (string) $message['line'],
                                ],
                            ],
                            'ruleId' => $message['source'],

                        ],
                        $data['files'][$file]['messages'],
                    ),
                );

                return $result;
            },
            [
                'issues' => [],
                'rules' => array_values($rules),
            ],
        ),
        JSON_THROW_ON_ERROR,
    ),
    LOCK_EX,
);
