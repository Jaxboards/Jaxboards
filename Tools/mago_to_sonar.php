<?php

declare(strict_types=1);

use Tools\Sonar\Impact;
use Tools\Sonar\Issue;
use Tools\Sonar\Location;
use Tools\Sonar\Rule;
use Tools\Sonar\TextRange;

require_once dirname(__DIR__) . '/vendor/autoload.php';

define('MAGO', realpath(dirname(__DIR__) . '/vendor/bin/mago'));

function getMagoRulesForSonar(): array
{
    exec(MAGO . ' lint --list-rules --json', $output);

    $rules = json_decode(implode('', $output));

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

        switch ($rule->category) {
            case 'BestPractices':
            case 'Deprecation':
            case 'Safety':
            case 'Security':
                $impact->softwareQuality = 'RELIABILITY';
                break;
            case 'Correctness':
            case 'Clarity':
            case 'Consistency':
            case 'Maintainability':
            case 'Redundancy':
                $impact->softwareQuality = 'MAINTAINABILITY';
                break;
        }

        $sonarRule->impacts[] = $impact;
        $sonarRules[] = $sonarRule;
    }

    return $sonarRules;
}

function getMagoIssuesForSonar(): array
{
    exec(MAGO . ' lint --reporting-format json', $output);

    $outputJSON = json_decode(implode('', $output));

    /** @var array<Issue> */
    $sonarIssues = [];
    foreach ($outputJSON->issues as $magoIssue) {
        $magoAnnotation = $magoIssue->annotations[0];

        $sonarIssue = new Issue();
        $sonarIssue->ruleId = $magoIssue->code;
        $sonarIssue->effortMinutes = 5;

        $primaryLocation = new Location();
        $primaryLocation->filePath = $magoAnnotation->span->file_id->name;
        // TODO: does it make sense to use the more detailed $magoAnnotation->message ?
        $primaryLocation->message = $magoIssue->message;

        $textRange = new TextRange();
        $textRange->startLine = $magoAnnotation->span->start->line ?: 1;
        $textRange->endLine = $magoAnnotation->span->end->line ?: 1;
        $primaryLocation->textRange = $textRange;

        $sonarIssue->primaryLocation = $primaryLocation;

        $sonarIssues[] = $sonarIssue;
    }

    return $sonarIssues;
}

function getSonarPayload(): string
{
    return json_encode([
        'rules' => getMagoRulesForSonar(),
        'issues' => getMagoIssuesForSonar(),
    ], JSON_PRETTY_PRINT);
}

file_put_contents('mago-report-sonar.json', getSonarPayload());
