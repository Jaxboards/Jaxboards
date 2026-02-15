<?php

declare(strict_types=1);

use Tools\Mago\LintIssues;
use Tools\Mago\LintRule;
use Tools\Sonar\Impact;
use Tools\Sonar\Issue;
use Tools\Sonar\Location;
use Tools\Sonar\Rule;
use Tools\Sonar\TextRange;

require_once dirname(__DIR__) . '/vendor/autoload.php';

final class MagoToSonar
{
    private string $mago = '';

    public function __construct()
    {
        $mago = realpath(dirname(__DIR__) . '/vendor/bin/mago');
        if (!$mago) {
            error_log('Mago not installed. Please run composer install first.');
            return;
        }

        $this->mago = $mago;
    }

    /**
     * @return Array<Rule>
     */
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

    /**
     * Mago does not currently have a --list-rules for analyze so we can fake them until they make them
     *
     * @param Array<Issue> $issues
     * @return Array<Rule>
     */
    private function get_analyze_rules_for_sonar(array $issues): array
    {
        $seenRules = [];

        $sonarRules = [];
        foreach ($issues as $issue) {
            if (array_key_exists($issue->ruleId, $seenRules)) {
                continue;
            }
            $seenRules[$issue->ruleId] = true;

            $sonarRule = new Rule();
            $sonarRule->id = $issue->ruleId;
            $sonarRule->name = $issue->ruleId;
            $sonarRule->description = '';
            $sonarRule->cleanCodeAttribute = 'CONVENTIONAL';
            $sonarRule->engineId = 'mago';
            $sonarRule->type = 'CODE_SMELL';

            $impact = new Impact();
            $impact->severity = 'MAJOR';
            $impact->softwareQuality = 'RELIABILITY';

            $sonarRule->impacts[] = $impact;
            $sonarRules[] = $sonarRule;
        }

        return $sonarRules;
    }

    /**
     * @return array<Issue>
     */
    private function get_mago_issues_for_sonar(string $subCommand = 'lint'): array
    {
        /** @var array<string> $output */
        exec("{$this->mago} {$subCommand} --reporting-format json", $output);

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
        $analyzeIssues = $this->get_mago_issues_for_sonar('analyze');

        file_put_contents('mago-report-sonar.json', json_encode([
            'rules' => array_merge(
                $this->get_mago_rules_for_sonar(),
                $this->get_analyze_rules_for_sonar($analyzeIssues)
            ),
            'issues' => array_merge(
                $this->get_mago_issues_for_sonar('lint'),
                $analyzeIssues
            ),
        ], JSON_PRETTY_PRINT));
    }
}

$magoToSonar = new MagoToSonar();
$magoToSonar->write_sonar_report();
