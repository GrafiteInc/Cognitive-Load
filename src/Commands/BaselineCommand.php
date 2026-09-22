<?php

namespace Grafite\CognitiveLoad\Commands;

use Grafite\CognitiveLoad\Baseline\BaselineRepository;
use Grafite\CognitiveLoad\CognitiveLoadAnalyzer;
use Illuminate\Console\Command;

class BaselineCommand extends Command
{
    protected $signature = 'cognitive-load:baseline
        {path? : Path to scan, overrides config(cognitive-load.paths)}
        {--force : Overwrite an existing baseline file without confirmation}
        {--baseline-path= : Override config(cognitive-load.baseline.path) for this run}';

    protected $description = 'Snapshot the current cognitive load scores as the baseline for future comparisons.';

    public function handle(CognitiveLoadAnalyzer $analyzer, BaselineRepository $baselineRepository): int
    {
        $baselinePath = $this->option('baseline-path') ?: (string) config('cognitive-load.baseline.path');

        if (file_exists($baselinePath) && ! $this->option('force')) {
            if (! $this->confirm("A baseline already exists at [{$baselinePath}]. Overwrite it?")) {
                $this->info('Baseline not written.');

                return self::SUCCESS;
            }
        }

        $path = $this->argument('path');
        $result = $analyzer->analyze($path ? [$path] : null);

        $baselineRepository->save($baselinePath, $result);

        $this->info(sprintf(
            'Baseline written to [%s]. Overall: %.1f/100 (%s) across %d class(es).',
            $baselinePath,
            $result->overallScore,
            $result->band,
            count($result->classes),
        ));

        return self::SUCCESS;
    }
}
