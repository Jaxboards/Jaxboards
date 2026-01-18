#!/usr/bin/env php
<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong,PSR12.Files.FileHeader.IncorrectOrder,Squiz.Commenting.InlineComment.DocBlock,Squiz.Commenting.BlockComment.WrongStart

/**
 * Tool to convert PHP_CodeSniffer output into something SonarQube can read.
 *
 * @see https://docs.sonarsource.com/sonarqube-cloud/enriching/generic-issue-data/
 * @see https://docs.sonarsource.com/sonarqube-server/latest/analyzing-source-code/importing-external-issues/generic-issue-import-format/
 * @see https://community.sonarsource.com/t/how-can-i-include-php-codesniffer-and-mess-detector-ruleset-or-report/47776/4
 * @see https://community.sonarsource.com/t/how-can-i-include-php-codesniffer-and-mess-detector-ruleset-or-report/47776/8
 *
 * USAGE:
 * ```sh
 * php_codesniffer_to_sonarqube.php <input.json> <output.json>
 * ```
 */

// phpcs:disable

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
    // Default
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


// phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder,Generic.Files.LineLength.TooLong
const RULE_DESCRIPTION_REPLACEMENTS = [
    '/ Currently using \d+ lines./' => '',
    '/Unused variable \$\w+/' => 'Unused variable',
    '/Class name \w+ does not match filepath [\/\w\.]+/' => 'Class name does not match filepath',
    '/Cognitive complexity for "\w+" is \d+ but has to be/' => 'Cognitive complexity must be',
    '/; contains \d+ characters/' => '',
    '/Unused parameter \$\w+/' => 'Unused parameter',
    '/Property [\\\\\w]+::\$\w+ does not have/' => 'Property does not have',
    '/Method [\\\\\w]+::\w+\(\) does not have/' => 'Method does not have',
    '/inside string is disallowed, found "\$\w+"/' => 'inside string is disallowed',
    '/is deprecated as of PHP 8.2, found "\$\{\w+\}"/' => 'is deprecated as of PHP 8.2',
    '/is deprecated as of PHP 8.3, found "\$\{\w+\}"/' => 'is deprecated as of PHP 8.3',
    '/is deprecated as of PHP 8.4, found "\$\{\w+\}"/' => 'is deprecated as of PHP 8.4',
    '/is deprecated as of PHP 8.5, found "\$\{\w+\}"/' => 'is deprecated as of PHP 8.5',
    '/on property "\$\w+"/' => 'on property',
    '/on method "\w+"/' => 'on method',
    '/variable \$\w+/' => 'varaible',
    '/for constant \w+/' => 'for a constant',
    '/default value of parameter \$\w+/' => 'default value of parameter',
    '/Useless alias "\w+" for use of "[\w\\\]+"/' => 'Useless alias',
    '/ The first wrong one is [\w\\\]+./' => '',
    '/annotation @\w+ is/' => 'this annotation is',
    '/array type hint syntax in \"\w+\[\]" is disallowed/' => 'array type hint syntax (e.g. "string[]")',
    '/Unknown type hint \"[\w\[\]\\\<,>]+\" found for \$\w+/' => 'Unknown type hint',
    '/Type hint \"[\w\[\]\\\<,>]+\" missing for \$\w+/' => 'Type hint missing',
    '/Type [\w\\\]+ is not used in this file/' => 'Type is not used in this file',
    '/ spaces but found \d/' => ' spaces',
    '/ but found "\w+"/' => '',
    '/ method "\w+" should/' => ' method should',
    '/ Found: \(\w+\)/' => '',
    '/Property name "\$\w+"/' => 'Property',
    '/Property [\\\\\w]+::\$\w+/' => 'Property',
    '/; found: <\?php.*/' => '',
    '/; found: <%.*/' => '',
    '/Parameter \$\w+/' => 'Parameter',
    '/; expected "\(\w+\)" but found "\(\w+\)"/' => '',
    '/; expected "[\\\\\w]+" but found "[\\\\\w]+"/' => '',
    '/Operator [=!]= is disallowed, use [=!]== instead/' => 'Use strict equality instead',
    '/Null type hint should be on last position in "[\w\|]+"/' => 'Null type hint should be on last position, e.g. `string|null`',
    '/; expected "\/\/ .*" but found "\/\/.*"/' => '',
    '/ class \w+/' => '',
    '/ function \w+\(\)/' => '',
    '/ tag for "[\w\\\]+" exception/' => ' tag for exception',
    '/Method name "\w+"/' => 'Method name',
    '/Method [\\\\\w]+::\w+\(\)/' => 'Method',
    '/property [\\\\\w]+::\$\w+/' => 'property',
    '/type hint "[\w<,\s>]+"; found "[\w<,\s>]+" for \$\w+/' => 'type hint not found',
    '/Expected "[\w<,\s>]+" but found "[\w<,\s>]+"/' => 'Incorrect type hint found',
    '/; \d+ found/' => '',
    '/; found \d+/' => '',
    '/parameter "\$\w+"/' => 'parameter',
    '/parameter \$\w+/' => 'parameter',
    '/; expected "\w+"/' => '',
    '/Constant [\\\\\w]+::\w+/' => 'Constant',
    '/TODO task\s+.*/' => 'TODO task',
    '/FIXME task\s+.*/' => 'FIXME task',
    '/Class name "\w+"/' => 'Class name',
    '/; expected \w+ but found \w+/' => '',
    '/Class \\\[\w\\\]+/' => 'Class',
    '/ Expected @\w+, found @\w+/' => '',
    '/ The first symbol is defined on line \d+ and the first side effect is on line \d+./' => '',
    '/method [\\\\\w]+::\w+\(\)/' => 'this method',
    '/forbidden comment ".*"/' => 'forbidden comment',
    '/Function \w+ is specialized/' => 'Function is specialized',
    '/missing in \w+\(\)/' => 'missing in function',
    '/true in \w+\(\)/' => 'true in function',
    '/prefix "\w+"/' => 'prefix, e.g. Exception prefixed with Exception',
    '/suffix "\w+"/' => 'suffix, e.g. Exception ending in Exception',
    '/; use \w+\(\) instead/' => '; use the recommended alternative instead',
    '/".+=" operator instead of "=" and ".+"/' => 'combined assignment operator instead of assignment and operation',
    // phpcs:disable Generic.Files.LineLength.TooLong
    '/placement of "[\w\s]+" group is invalid. Last group was "[\w\s]+" and one of these is expected after it: [\w\s]+/' => 'structure of this class must be ordered',
    // phpcs:enable
    '/Function \w+\(\)/' => 'Function',
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
        $output = preg_replace(
            $replace,
            $replacement,
            $output,
        ) ?? '';
    }

    return $output;
}

$rules = array_reduce(
    $data['files'],
    static fn(array $rules, array $file): array => array_reduce(
        $file['messages'],
        static function (array $rules, array $message): array {
            if (array_key_exists($message['source'], $rules)) {
                return $rules;
            }

            $description = generify($message['message'] ?? '');

            // We don't have a way to guage severity so we always set it to
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
                        // We don't have a way to guage this easily so we
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

const TAB_CHARACTER = "\t";
const TAB_REPLACEMENT_CHARACTER = '🐛';
const TAB_WIDTH = 4;

// All issues from all files
$issues = array_merge_recursive(
    ...array_map(
        static function (
            string $filename,
        ) use ($data): array {
            $file = new SplFileObject($filename);

            return array_map(
                static function (array $message) use (
                    $file,
                    $filename,
                ): array {
                    $column = (static function (
                        int $column_input,
                        SplFileObject $file,
                        int $line,
                    ): int {
                        // PHP_CodeSniffer starts at 1 for columns, we
                        // need to subtract 1 since SonarQube starts at
                        // 0
                        $column = $column_input - 1;

                        // Next we need to check if there are tabs in
                        // the line up to the specified column -
                        // PHP_CodeSniffer counts tabs as 4 characters
                        // while SonarQube only counts them as one
                        $file->seek(
                            // Seek value should be one under the line
                            // since lines are 0 indexed for seek but 1
                            // indexed for the report
                            $line - 1,
                        );
                        $original_line = $file->current();
                        if (!$original_line) {
                            // Empty line without line ending or end of
                            // the file reached, no column to include
                            return -1;
                        }
                        // SplFileObject::current may return an array if
                        // configured to parse file as a CSV, we don't
                        // do that so if we have anything other than a
                        // string at this point we should error out
                        assert(is_string($original_line));
                        if (mb_strlen($original_line) === 1) {
                            // Empty lines should not contain a column
                            // lines usually have a line ending
                            // character
                            return -1;
                        }
                        $measurement_line = str_replace(
                            TAB_CHARACTER,
                            str_repeat(
                                TAB_REPLACEMENT_CHARACTER,
                                TAB_WIDTH,
                            ),
                            $original_line,
                        );
                        // The str_replace function may return an array
                        // if the input is an array, but since we're
                        // passing it strings we're sure it's a string
                        // @phpstan-ignore-next-line
                        assert(is_string($measurement_line));
                        $up_to_column = mb_substr(
                            $measurement_line,
                            0,
                            $column,
                        );
                        $tab_count = intdiv(
                            mb_substr_count(
                                $up_to_column,
                                TAB_REPLACEMENT_CHARACTER,
                            ),
                            TAB_WIDTH,
                        );

                        return $column - ((TAB_WIDTH - 1) * $tab_count);
                    })(
                        (int) $message['column'],
                        $file,
                        (int) $message['line'],
                    );

                    $issue = [
                        'engineId' => 'PHP_CodeSniffer',
                        'primaryLocation' => [
                            'filePath' => $filename,
                            'message' => $message['message'],
                            'textRange' => [
                                'startColumn' => $column,
                                'startLine' => $message['line'],
                            ],
                        ],
                        'ruleId' => $message['source'],
                    ];

                    if ($column === -1) {
                        // Remove column if we don't need it, which
                        // we've marked by a -1
                        unset($issue['primaryLocation']['textRange']['startColumn']);
                    }

                    return $issue;
                },
                $data['files'][$filename]['messages'],
            );
        },
        array_keys($data['files']),
    ),
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
