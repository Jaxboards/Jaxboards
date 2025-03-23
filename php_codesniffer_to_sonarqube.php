#!/usr/bin/env php
<?php

/**
 * Tool to convert PHP_CodeSniffer output into something SonarQube can read.
 *
 * @see https://docs.sonarsource.com/sonarqube-cloud/enriching/generic-issue-data/
 * @see https://community.sonarsource.com/t/how-can-i-include-php-codesniffer-and-mess-detector-ruleset-or-report/47776/4
 * @see https://community.sonarsource.com/t/how-can-i-include-php-codesniffer-and-mess-detector-ruleset-or-report/47776/8
 *
 * USAGE:
 * ```sh
 * phpcs_to_sonarqube.php <input.json> <output.json>
 * ```
 */

/*
 * Fetch CLI arguments.
 */

$php_codesniffer_report = $argv[1] ?? '';

if ($php_codesniffer_report === '') {
    fwrite(
        STDERR,
        'Please enter the path to your PHP_CodeSniffer report json file',
    );

    exit(1);
}

$sonarqube_report = $argv[2] ?? '';

if ($sonarqube_report === '') {
    fwrite(
        STDERR,
        'Please enter the path to where to save your SonarQube report json '
        . 'file',
    );

    exit(1);
}

/*
 * Validate CLI arguments are usable
 */

if (!file_exists($php_codesniffer_report)) {
    fwrite(STDERR, 'Provided PHP_CodeSniffer report json file does not exist');

    exit(1);
}

if (!is_readable($php_codesniffer_report)) {
    fwrite(STDERR, 'Provided PHP_CodeSniffer report json file is not readable');

    exit(1);
}

if (file_exists($sonarqube_report) && !is_writable($sonarqube_report)) {
    fwrite(
        STDERR,
        'SonarQube report file already exists and is not writable',
    );

    exit(1);
}

if (
    !file_exists($sonarqube_report)
    && !is_writable(dirname($sonarqube_report))
) {
    fwrite(
        STDERR,
        'SonarQube report file directory is not writable',
    );

    exit(1);
}

$data = json_decode(
    file_get_contents($php_codesniffer_report),
    null,
    512,
    // default
    JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
);

if (!is_array($data['files'])) {
    fwrite(
        STDERR,
        'Provided PHP_CodeSniffer report json file does not have `files` array',
    );

    exit(1);
}

$current_rules = [];

file_put_contents(
    $sonarqube_report,
    json_encode(
        array_reduce(
            array_keys($data['files']),
            static function (
                array $result,
                string $file,
            ) use (
                &$current_rules,
                $data,
            ): array {
                array_push(
                    $result['rules'],
                    ...array_map(
                        static fn(array $message): array => [
                            'id' => $message['source'],
                            'name' => $message['message'],
                            'engineId' => 'PHP_CodeSniffer',
                            'cleanCodeAttribute' => 'FORMATTED',
                            'impacts' => [
                                [
                                    'severity' => 'LOW',
                                    'softwareQuality' => 'MAINTAINABILITY',
                                ],
                            ],
                        ],
                        array_filter(
                            $data['files'][$file]['messages'],
                            // phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
                            static fn(
                            // phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact
                                array $message,
                            ): bool => !in_array(
                                $message['source'],
                                $current_rules,
                            ),
                        ),
                    ),
                );

                $current_rules = array_map(
                    static fn(array $rule): string => $rule['id'],
                    $result['rules'],
                );

                array_push(
                    $result['issues'],
                    ...array_map(
                        static fn(array $message): array => [
                            'ruleId' => $message['source'],
                            'engineId' => 'PHP_CodeSniffer',
                            'primaryLocation' => [
                                'message' => $message['message'],
                                'filePath' => $file,
                                'textRange' => [
                                    'startColumn' =>
                                        // SonarQube starts at 0 for columns
                                        // while PHP_CodeSniffer starts at 1
                                        (string) (
                                            ((int) $message['column']) - 1
                                        ),
                                    'startLine' => (string) $message['line'],
                                    // we don't know when this ends due to lack
                                    // of information from PHP_CodeSniffer so we
                                    // just add 1 to the starting positions
                                    // (otherwise SonarQube crashes)
                                    'endColumn' => (string) $message['column'],
                                    'endLine' => (string) (
                                        (int) $message['line'] + 1
                                    ),
                                ],
                            ],

                        ],
                        $data['files'][$file]['messages'],
                    ),
                );

                return $result;
            },
            [
                'issues' => [],
                'rules' => [],
            ],
        ),
        JSON_THROW_ON_ERROR,
    ),
    LOCK_EX,
);
