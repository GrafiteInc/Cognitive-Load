<?php

namespace Grafite\CognitiveLoad\Output;

use Grafite\CognitiveLoad\Sloppy\Baseline\SloppyComparisonResult;
use Grafite\CognitiveLoad\Sloppy\Finding;
use Grafite\CognitiveLoad\Sloppy\Severity;
use Grafite\CognitiveLoad\Sloppy\SloppyReport;
use Illuminate\Console\Command;

class SloppyConsoleFormatter
{
    private const COLORS = [
        'high' => 'red',
        'medium' => 'yellow',
        'low' => 'gray',
    ];

    public function render(Command $command, SloppyReport $report, ?SloppyComparisonResult $comparison = null): void
    {
        $counts = $report->countsBySeverity();

        $command->info(sprintf(
            'Sloppiness: %d finding(s)  High: %d  Medium: %d  Low: %d  Files: %d',
            count($report->findings),
            $counts[Severity::High->value],
            $counts[Severity::Medium->value],
            $counts[Severity::Low->value],
            $report->fileCount,
        ));

        foreach ($report->findings as $finding) {
            $this->renderFinding($command, $finding);
        }

        foreach ($report->warnings as $warning) {
            $command->warn($warning);
        }

        if ($comparison === null) {
            return;
        }

        $summary = sprintf(
            '%d new, %d resolved (failing on %s and above).',
            count($comparison->newFindings),
            count($comparison->resolvedFingerprints),
            $comparison->failOn,
        );

        if ($comparison->passed) {
            $command->info("Baseline comparison passed: {$summary}");

            return;
        }

        $command->error("Baseline comparison failed: {$summary}");

        $command->table(
            ['Severity', 'Rule', 'Location', 'Message'],
            array_map(fn (Finding $finding) => [
                strtoupper($finding->severity->value),
                $finding->ruleId,
                "{$finding->file}:{$finding->line}",
                $finding->message,
            ], $comparison->newFindings),
        );
    }

    private function renderFinding(Command $command, Finding $finding): void
    {
        $color = self::COLORS[$finding->severity->value];

        $command->newLine();
        $command->line(sprintf(
            '<fg=%s;options=bold>%-6s</> <options=bold>%s</> %s  <fg=cyan>%s:%d</>  <fg=gray>%s</>',
            $color,
            strtoupper($finding->severity->value),
            $finding->ruleId,
            $finding->ruleName,
            $finding->file,
            $finding->line,
            $finding->symbol,
        ));
        $command->line("       {$finding->message} <fg=gray>(confidence: {$finding->confidence})</>");
        $command->line("       <fg=green>→</> {$finding->advice}");
    }
}
