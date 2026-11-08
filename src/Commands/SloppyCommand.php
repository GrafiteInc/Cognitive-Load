<?php

namespace Grafite\CognitiveLoad\Commands;

use Grafite\CognitiveLoad\Output\SloppyConsoleFormatter;
use Grafite\CognitiveLoad\Output\SloppyJsonFormatter;
use Grafite\CognitiveLoad\Sloppy\Baseline\SloppyBaselineComparator;
use Grafite\CognitiveLoad\Sloppy\Baseline\SloppyBaselineRepository;
use Grafite\CognitiveLoad\Sloppy\Severity;
use Grafite\CognitiveLoad\Sloppy\SloppyAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class SloppyCommand extends Command
{
    protected $signature = 'cognitive-load:sloppy
        {path? : Path to scan, overrides config(cognitive-load.paths)}
        {--format=console : Output format for the terminal report: console or json}
        {--min-severity= : Only report findings at or above this severity: low, medium or high}
        {--output= : Also write the full JSON report to this file path}
        {--compare-baseline : Load the sloppiness baseline and report new/resolved findings}
        {--fail-on-new : Exit non-zero when new findings reach config(cognitive-load.sloppy.gate.fail_on) (implies --compare-baseline)}
        {--baseline-path= : Override config(cognitive-load.sloppy.baseline.path) for this run}';

    protected $description = 'Find sloppy code: swallowed errors, unfinished work, dead code, Eloquent misuse and duplication.';

    public function handle(
        SloppyAnalyzer $analyzer,
        SloppyBaselineRepository $baselineRepository,
        SloppyBaselineComparator $baselineComparator,
        Filesystem $files,
    ): int {
        $minSeverity = null;

        if ($this->option('min-severity')) {
            $minSeverity = Severity::tryFrom(strtolower((string) $this->option('min-severity')));

            if ($minSeverity === null) {
                $this->error('Invalid --min-severity; expected low, medium or high.');

                return self::FAILURE;
            }
        }

        $path = $this->argument('path');
        $report = $analyzer->analyze($path ? [$path] : null, $minSeverity);

        $shouldCompare = (bool) $this->option('compare-baseline') || (bool) $this->option('fail-on-new');
        $comparison = null;

        if ($shouldCompare) {
            $baselinePath = $this->option('baseline-path') ?: (string) config('cognitive-load.sloppy.baseline.path');
            $baseline = $baselineRepository->load($baselinePath);

            if ($baseline === null) {
                if ((bool) config('cognitive-load.sloppy.gate.fail_if_baseline_missing', false)) {
                    $this->error("No sloppiness baseline found at [{$baselinePath}].");

                    return self::FAILURE;
                }

                $this->warn("No sloppiness baseline found at [{$baselinePath}]; treating every finding as new.");
            }

            $failOn = Severity::tryFrom((string) config('cognitive-load.sloppy.gate.fail_on', 'medium')) ?? Severity::Medium;
            $comparison = $baselineComparator->compare($report, $baseline ?? [], $failOn);
        }

        if ($this->option('format') === 'json') {
            $this->line((new SloppyJsonFormatter)->render($report, $comparison));
        } else {
            (new SloppyConsoleFormatter)->render($this, $report, $comparison);
        }

        if ($outputPath = $this->option('output')) {
            $files->put($outputPath, (new SloppyJsonFormatter)->render($report, $comparison));
        }

        if ((bool) $this->option('fail-on-new') && $comparison !== null && ! $comparison->passed) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
