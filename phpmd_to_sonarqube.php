#!/usr/bin/env php
<?php

/**
 * Tool to convert phpmd output into something SonarQube can read.
 *
 * @see https://docs.sonarsource.com/sonarqube-cloud/enriching/generic-issue-data/
 * @see https://community.sonarsource.com/t/how-can-i-include-php-codesniffer-and-mess-detector-ruleset-or-report/47776/4
 * @see https://community.sonarsource.com/t/how-can-i-include-php-codesniffer-and-mess-detector-ruleset-or-report/47776/8
 *
 * USAGE:
 * ```sh
 * phpmd_to_sonarqube.php <input.json> <output.json>
 * ```
 */

// Fetch CLI arguments.
$phpmdReport = $argv[1] ?? '';

if ($phpmdReport === '') {
    fwrite(
        STDERR,
        'Please enter the path to your phpmd report json file',
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
if (!file_exists($phpmdReport)) {
    fwrite(STDERR, 'Provided phpmd report json file does not exist');

    exit(1);
}

if (!is_readable($phpmdReport)) {
    fwrite(STDERR, 'Provided phpmd report json file is not readable');

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

$dataJSON = file_get_contents($phpmdReport);
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
        'Provided phpmd report json file does not have `files` array',
    );

    exit(1);
}

$rules = array_reduce(
    $data['files'],
    static fn(array $rules, array $file): array => array_reduce(
        $file['violations'],
        static function (array $rules, array $violation) {
            if (array_key_exists($violation['rule'], $rules)) {
                return $rules;
            }
            $description = preg_replace(
                '/ on line \d+/',
                '',
                (string) $violation['description'],
            );
            $description = preg_replace(
                '/ variable names like \$\w+/',
                ' variable names',
                $description,
            );
            $description = preg_replace(
                '/ variable \$\w+ is/',
                ' variable is',
                $description,
            );
            $description = preg_replace(
                '/ variables with short names like \$\w+/',
                ' variables with short names',
                $description,
            );
            $description = preg_replace(
                '/ undefined variables such as \'\$\w+\'/',
                ' undefined variables',
                $description,
            );
            $description = preg_replace(
                '/ unused local variables such as \'\$\w+\'/',
                ' unused local variables',
                $description,
            );
            $description = preg_replace(
                '/ method \w+\(\) has a Cyclomatic Complexity of \d+/',
                ' method has a Cyclomatic Complexity that exceeds the threshoold',
                $description,
            );
            $description = preg_replace(
                '/The method \w+ uses an else expression. /',
                '',
                $description,
            );
            $description = preg_replace(
                '/The class \w+ has \d+ lines of code. /',
                'The class has more lines of code than our threshold. ',
                $description,
            );
            $description = preg_replace(
                '/The class \w+ has an overall complexity of \d+ /',
                'The class an overall complexity ',
                $description,
            );
            $description = preg_replace(
                '/ method \w+ has a boolean flag argument \$\w+/',
                ' method has a boolean flag argument',
                $description,
            );
            $description = preg_replace(
                '/ class \$\w+ is/',
                ' class is',
                $description,
            );
            $description = preg_replace(
                '/ property \$\w+ is/',
                ' property is',
                $description,
            );
            $description = preg_replace(
                '/The method \w+\(\) has an NPath complexity of \d+. /',
                'The method has more NPath complexity than our threshold. ',
                $description,
            );
            $description = preg_replace(
                '/\w+ accesses the super-global variable \$\w+/',
                'No accessing superglobal variables',
                $description,
            );
            $description = preg_replace(
                '/ \(line \'\d+\', column \'\d+\'\)/',
                '',
                $description,
            );
            $description = preg_replace(
                '/The class \w+ is not named/',
                'The class is not named',
                $description,
            );
            $description = preg_replace(
                '/The method \w+\(\) has \d+ lines of code. /',
                'The method has excessive lines of code. ',
                $description,
            );
            $description = preg_replace(
                '/parameter \$\w+/',
                'parameter',
                $description,
            );
            $description = preg_replace(
                '/method \w+\(\) contains/',
                'method contains',
                $description,
            );
            $description = preg_replace(
                '/class \w+ has \d+ public methods/',
                'class has a lot of public methods',
                $description,
            );
            $description = preg_replace(
                '/class \w+ has \d+ non-getter-/',
                'class has a lot of non-getter-',
                $description,
            );
            $description = preg_replace(
                '/such as \'\$\w+\'/',
                '',
                $description,
            );
            $description = preg_replace(
                '/The method \w+::\w+\(\)/',
                'The method',
                $description,
            );
            $description = preg_replace(
                '/The class \w+ has \d+ fields. Consider redesigning \w+/',
                'Consider redesigning this class',
                $description,
            );
            $description = preg_replace(
                '/ short method names like \w+::\w+\(\)/',
                ' short method names',
                $description,
            );
            $description = preg_replace(
                '/ classes with short names like \w+/',
                ' classes with short names',
                $description,
            );

            $description .= PHP_EOL
                . PHP_EOL
                . 'Rule Set: '
                . $violation['ruleSet'];

            if ($violation['externalInfoUrl'] !== '#') {
                $description .= PHP_EOL
                    . PHP_EOL
                    . $violation['externalInfoUrl'];
            }

            $rules[$violation['rule']] = [
                'cleanCodeAttribute' => 'CONVENTIONAL',
                'description' => $description,
                'engineId' => 'phpmd',
                'id' => $violation['rule'],
                'impacts' => [
                    [
                        'severity' => match ((int) $violation['priority']) {
                            1 => 'BLOCKER',
                            2 => 'HIGH',
                            3 => 'MEDIUM',
                            4 => 'LOW',
                            default => 'INFO',
                        },
                        // we don't have a way to guage this easily so we
                        // always set mainability
                        'softwareQuality' => 'MAINTAINABILITY',
                    ],
                ],
                'name' => $violation['rule'],
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
            $data['files'],
            static function (
                array $result,
                array $file,
            ): array {
                $filename = $file['file'];
                array_push(
                    $result['issues'],
                    ...array_map(
                        static function (array $violation) use ($filename): array {
                            $issue = [
                                'engineId' => 'phpmd',
                                'primaryLocation' => [
                                    'filePath' => $filename,
                                    'message' => $violation['description'],
                                    'textRange' => [
                                        'endLine' => (string) $violation['endLine'],
                                        'startLine' => (string) $violation['beginLine'],
                                    ],
                                ],
                                'ruleId' => $violation['rule'],
                            ];

                            $matches = [];
                            preg_match(
                                '/ \(line \'\d+\', column \'(?P<column>\d+)\'\)/',
                                (string) $violation['description'],
                                $matches,
                            );
                            $column = $matches['column'] ?? '';

                            if ($column !== '') {
                                $issue['primaryLocation']['textRange']['startColumn'] = $column;
                            }

                            return $issue;
                        },
                        $file['violations'],
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
