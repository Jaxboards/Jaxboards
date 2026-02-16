<?php

declare(strict_types=1);

namespace Tools;

use Override;
use Tools\Mago\LintIssues;
use Tools\Mago\LintRule;
use Tools\Sonar\Impact;
use Tools\Sonar\Issue;
use Tools\Sonar\Location;
use Tools\Sonar\Rule;
use Tools\Sonar\TextRange;

final readonly class MagoToSonar implements CLIRoute
{
    private string $mago;

    public function __construct()
    {
        $mago = realpath(dirname(__DIR__) . '/vendor/bin/mago');
        $this->mago = $mago ? $mago : '';
    }

    private function get_mago_rules_for_sonar(): array
    {
        /** @var array<string> $output */
        exec($this->mago . ' lint --list-rules --json', $output);

        /** @var Array<LintRule> */
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

    private function get_mago_issues_for_sonar(): array
    {
        /** @var array<string> $output */
        exec($this->mago . ' lint --reporting-format json', $output);

        /** @var LintIssues $lintIssues */
        $lintIssues = json_decode(implode('', $output));

        /** @var array<Issue> */
        $sonarIssues = [];
        foreach ($lintIssues->issues as $magoIssue) {
            $magoAnnotation = $magoIssue->annotations[0] ?? null;

            if (!$magoAnnotation) {
                continue;
            }

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

    public function write_sonar_report(): void
    {
        file_put_contents('mago-report-sonar.json', json_encode([
            'rules' => $this->get_mago_rules_for_sonar(),
            'issues' => $this->get_mago_issues_for_sonar(),
        ], JSON_PRETTY_PRINT));
    }

    #[Override]
    public function route(array $params): void
    {
        $this->write_sonar_report();
    }
}
