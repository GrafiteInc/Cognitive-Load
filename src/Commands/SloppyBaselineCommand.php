<?php

namespace Grafite\CognitiveLoad\Commands;

use Grafite\CognitiveLoad\Sloppy\Baseline\SloppyBaselineRepository;
use Grafite\CognitiveLoad\Sloppy\Severity;
use Grafite\CognitiveLoad\Sloppy\SloppyAnalyzer;
use Illuminate\Console\Command;

class SloppyBaselineCommand extends Command
{
    protected $signature = 'cognitive-load:sloppy-baseline
        {path? : Path to scan, overrides config(cognitive-load.paths)}
        {--force : Overwrite an existing baseline file without confirmation}
        {--baseline-path= : Override config(cognitive-load.sloppy.baseline.path) for this run}';

    protected $description = 'Snapshot the current sloppiness findings as the baseline for future comparisons.';

    public function handle(SloppyAnalyzer $analyzer, SloppyBaselineRepository $baselineRepository): int
    {
        $baselinePath = $this->option('baseline-path') ?: (string) config('cognitive-load.sloppy.baseline.path');

        if (file_exists($baselinePath) && ! $this->option('force')) {
            if (! $this->confirm("A sloppiness baseline already exists at [{$baselinePath}]. Overwrite it?")) {
                $this->info('Baseline not written.');

                return self::SUCCESS;
            }
        }

        $path = $this->argument('path');

        // The baseline records every severity so raising min_severity later never resurfaces old findings as new.
        $report = $analyzer->analyze($path ? [$path] : null, Severity::Low);

        $baselineRepository->save($baselinePath, $report);

        $this->info(sprintf(
            'Sloppiness baseline written to [%s] with %d finding(s) across %d file(s).',
            $baselinePath,
            count($report->findings),
            $report->fileCount,
        ));

        return self::SUCCESS;
    }
}
