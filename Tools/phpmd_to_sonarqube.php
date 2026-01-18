#!/usr/bin/env php
<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong,PSR12.Files.FileHeader.IncorrectOrder,Squiz.Commenting.InlineComment.DocBlock,Squiz.Commenting.BlockComment.WrongStart

/**
 * Tool to convert phpmd output into something SonarQube can read.
 *
 * @see https://docs.sonarsource.com/sonarqube-cloud/enriching/generic-issue-data/
 * @see https://docs.sonarsource.com/sonarqube-server/latest/analyzing-source-code/importing-external-issues/generic-issue-import-format/
 * @see https://community.sonarsource.com/t/how-can-i-include-php-codesniffer-and-mess-detector-ruleset-or-report/47776/4
 * @see https://community.sonarsource.com/t/how-can-i-include-php-codesniffer-and-mess-detector-ruleset-or-report/47776/8
 *
 * USAGE:
 * ```sh
 * phpmd_to_sonarqube.php <input.json> <output.json>
 * ```
 */

// phpcs:enable

// Fetch CLI arguments.
$phpmdReport = $argv[1] ?? '';

if ($phpmdReport === '') {
    fwrite(STDERR, 'Please enter the path to your phpmd report json file');

    exit(1);
}

$sonarQubeReport = $argv[2] ?? '';

if ($sonarQubeReport === '') {
    fwrite(
        STDERR,
        'Please enter the path to where to save your SonarQube report json ' .
            'file',
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
    fwrite(STDERR, 'SonarQube report file already exists and is not writable');

    exit(1);
}

if (
    !file_exists($sonarQubeReport)
    && !is_writable(dirname($sonarQubeReport))
) {
    fwrite(STDERR, 'SonarQube report file directory is not writable');

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

// phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder,Generic.Files.LineLength.TooLong
const RULE_DESCRIPTION_REPLACEMENTS = [
    '/ on line \d+/' => '',
    '/ variable names like \$\w+/' => ' variable names',
    '/ variable \$\w+ is/' => ' variable is',
    '/ variables with short names like \$\w+/' => ' variables with short names',
    '/ undefined variables such as \'\$\w+\'/' => ' undefined variables',
    '/ unused local variables such as \'\$\w+\'/' => ' unused local variables',
    '/ method \w+\(\) has a Cyclomatic Complexity of \d+/' =>
        ' method has a Cyclomatic Complexity that exceeds the threshoold',
    '/The method \w+ uses an else expression. /' => '',
    '/The class \w+ has \d+ lines of code. /' =>
        'The class has more lines of code than our threshold. ',
    '/The class \w+ has an overall complexity of \d+ /' =>
        'The class an overall complexity ',
    '/ method \w+ has a boolean flag argument \$\w+/' =>
        ' method has a boolean flag argument',
    '/ class \$\w+ is/' => ' class is',
    '/ property \$\w+ is/' => ' property is',
    '/The method \w+\(\) has an NPath complexity of \d+. /' =>
        'The method has more NPath complexity than our threshold. ',
    '/\w+ accesses the super-global variable \$\w+/' =>
        'No accessing superglobal variables',
    '/ \(line \'\d+\', column \'\d+\'\)/' => '',
    '/The class \w+ is not named/' => 'The class is not named',
    '/The method \w+\(\) has \d+ lines of code. /' =>
        'The method has excessive lines of code. ',
    '/parameter \$\w+/' => 'parameter',
    '/method \w+\(\) contains/' => 'method contains',
    '/class \w+ has \d+ public methods/' => 'class has a lot of public methods',
    '/class \w+ has \d+ non-getter-/' => 'class has a lot of non-getter-',
    '/such as \'\$\w+\'/' => '',
    '/The method \w+::\w+\(\)/' => 'The method',
    '/The class \w+ has \d+ fields. Consider redesigning \w+/' =>
        'Consider redesigning this class',
    '/ short method names like \w+::\w+\(\)/' => ' short method names',
    '/ classes with short names like \w+/' => ' classes with short names',
];
// phpcs:enable

/**
 * Make rule descriptions more generic for SonarCloud issue rules.
 *
 * @param string $input The description to work with
 *
 * @return string The "generified" input
 */
function generify(string $input): string
{
    $output = $input;

    foreach (RULE_DESCRIPTION_REPLACEMENTS as $replace => $replacement) {
        $output = preg_replace($replace, $replacement, $output ?? '');
    }

    return $output ?? '';
}

$rules = array_reduce(
    $data['files'],
    static fn(array $rules, array $file): array => array_reduce(
        $file['violations'],
        static function (array $rules, array $violation): array {
            if (array_key_exists($violation['rule'], $rules)) {
                return $rules;
            }

            $description = generify($violation['description'] ?? '');

            $description .=
                PHP_EOL . PHP_EOL . 'Rule Set: ' . $violation['ruleSet'];

            if ($violation['externalInfoUrl'] !== '#') {
                $description .=
                    PHP_EOL . PHP_EOL . $violation['externalInfoUrl'];
            }

            $rules[$violation['rule']] = [
                'cleanCodeAttribute' => 'CONVENTIONAL',
                'description' => $description,
                'engineId' => 'phpmd',
                'id' => $violation['rule'],
                'impacts' => [
                    [
                        'severity' => match ((int) $violation['priority']) {
                            // despite there being different levels, it looks
                            // like all issues are getting grouped as a blocker
                            // so we should generally consider them low
                            1, 2, 3, 4 => 'LOW',
                            default => 'INFO',
                        },
                        // we don't have a way to guage this easily so we
                        // always set mainability
                        'softwareQuality' => 'MAINTAINABILITY',
                    ],
                ],
                'name' => $violation['rule'],
                'severity' => match ((int) $violation['priority']) {
                    1 => 'BLOCKER',
                    2 => 'CRITICAL',
                    3 => 'MAJOR',
                    4 => 'MINOR',
                    default => 'INFO',
                },
                'type' => 'CODE_SMELL',
            ];

            return $rules;
        },
        $rules,
    ),
    [],
);

define(
    'REMOVE_JAXBOARDS_ROOT',
    '/^' . preg_quote(dirname(__DIR__) . '/', '/') . '/',
);
$issues = array_merge(
    [],
    ...array_map(static function (array $file): array {
        $filename = preg_replace(
            REMOVE_JAXBOARDS_ROOT,
            '',
            (string) $file['file'],
        );

        return array_map(static function (array $violation) use (
            $filename,
        ): array {
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

            if (
                $issue['primaryLocation']['textRange']['endLine'] ===
                $issue['primaryLocation']['textRange']['startLine']
            ) {
                unset($issue['primaryLocation']['textRange']['endLine']);
            }

            if ($issue['primaryLocation']['textRange']['startLine'] < 1) {
                $issue['primaryLocation']['textRange']['startLine'] = '1';
            }

            $matches = [];
            preg_match(
                '/ \(line \'\d+\', column \'(?P<column>\d+)\'\)/',
                (string) $violation['description'],
                $matches,
            );
            $column = $matches['column'] ?? '';

            if ($column !== '' && $column > 0) {
                $issue['primaryLocation']['textRange']['startColumn'] = $column;
            }

            return $issue;
        }, $file['violations']);
    }, $data['files']),
);

file_put_contents(
    $sonarQubeReport,
    json_encode(
        [
            'issues' => $issues,
            'rules' => array_values($rules),
        ],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT,
    ),
    LOCK_EX,
);
