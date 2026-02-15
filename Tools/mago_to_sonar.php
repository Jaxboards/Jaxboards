<?php

declare(strict_types=1);

use Tools\Mago\LintIssues;
use Tools\Sonar\Impact;
use Tools\Sonar\Issue;
use Tools\Sonar\Location;
use Tools\Sonar\Rule;
use Tools\Sonar\TextRange;

require_once dirname(__DIR__) . '/vendor/autoload.php';

define('MAGO', realpath(dirname(__DIR__) . '/vendor/bin/mago'));

function get_mago_rules_for_sonar(): array
{
    exec(MAGO . ' lint --list-rules --json', $output);

    $rules = json_decode(implode('', $output));

    /** @var Array<Rule> $sonarRules */
    $sonarRules = [];

    foreach ($rules as $rule) {
        $sonarRule = new Rule();
        $sonarRule->id = $rule->code;
        $sonarRule->name = $rule->name;
        $sonarRule->description = $rule->description;
        $sonarRule->cleanCodeAttribute = 'CONVENTIONAL';
        $sonarRule->engineId = 'mago';
        $sonarRule->type = 'CODE_SMELL';

        $impact = new Impact();
        $impact->severity = 'MEDIUM';

        $impact->softwareQuality = match ($rule->category) {
            'BestPractices', 'Deprecation', 'Safety', 'Security' => 'RELIABILITY',
            default => 'MAINTAINABILITY',
        };

        $sonarRule->impacts[] = $impact;
        $sonarRules[] = $sonarRule;
    }

    return $sonarRules;
}

function get_mago_issues_for_sonar(): array
{
    exec(MAGO . ' lint --reporting-format json', $output);

    /** @var LintIssues $lintIssues */
    $lintIssues = json_decode(implode('', $output));

    /** @var array<Issue> */
    $sonarIssues = [];
    foreach ($lintIssues->issues as $magoIssue) {
        $magoAnnotation = $magoIssue->annotations[0];

        $sonarIssue = new Issue();
        $sonarIssue->ruleId = $magoIssue->code;
        $sonarIssue->effortMinutes = 5;

        $primaryLocation = new Location();
        $primaryLocation->filePath = $magoAnnotation->span->file_id->name;
        $primaryLocation->message = $magoAnnotation->message;

        $textRange = new TextRange();
        $textRange->startLine = $magoAnnotation->span->start->line + 1;
        $textRange->endLine = $magoAnnotation->span->end->line + 1;
        $primaryLocation->textRange = $textRange;

        $sonarIssue->primaryLocation = $primaryLocation;

        $sonarIssues[] = $sonarIssue;
    }

    return $sonarIssues;
}

function get_sonar_payload(): string
{
    return json_encode([
        'rules' => get_mago_rules_for_sonar(),
        'issues' => get_mago_issues_for_sonar(),
    ], JSON_PRETTY_PRINT);
}

file_put_contents('mago-report-sonar.json', get_sonar_payload());
