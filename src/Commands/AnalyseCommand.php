<?php

namespace Grafite\CognitiveLoad\Commands;

use Grafite\CognitiveLoad\Baseline\BaselineComparator;
use Grafite\CognitiveLoad\Baseline\BaselineRepository;
use Grafite\CognitiveLoad\CognitiveLoadAnalyzer;
use Grafite\CognitiveLoad\Output\ConsoleTableFormatter;
use Grafite\CognitiveLoad\Output\JsonFormatter;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class AnalyseCommand extends Command
{
    protected $signature = 'cognitive-load:analyse
        {path? : Path to scan, overrides config(cognitive-load.paths)}
        {--format=console : Output format for the terminal report: console or json}
        {--detailed : Include a per-method breakdown table for each class}
        {--output= : Also write the full JSON report to this file path}
        {--compare-baseline : Load the configured baseline and report a delta against it}
        {--fail-on-regression : Exit non-zero when the comparison fails the configured gate margins (implies --compare-baseline)}
        {--baseline-path= : Override config(cognitive-load.baseline.path) for this run}';

    protected $description = 'Analyse the codebase for cognitive complexity and architecture markers.';

    public function handle(
        CognitiveLoadAnalyzer $analyzer,
        BaselineRepository $baselineRepository,
        BaselineComparator $baselineComparator,
        Filesystem $files,
    ): int {
        $path = $this->argument('path');
        $result = $analyzer->analyze($path ? [$path] : null);

        $shouldCompare = (bool) $this->option('compare-baseline') || (bool) $this->option('fail-on-regression');
        $comparison = null;

        if ($shouldCompare) {
            $baselinePath = $this->option('baseline-path') ?: (string) config('cognitive-load.baseline.path');
            $baseline = $baselineRepository->load($baselinePath);

            if ($baseline === null) {
                if ((bool) config('cognitive-load.gate.fail_if_baseline_missing', false)) {
                    $this->error("No baseline found at [{$baselinePath}].");

                    return self::FAILURE;
                }

                $this->warn("No baseline found at [{$baselinePath}]; skipping comparison.");
            } else {
                $riskLabels = (array) config('cognitive-load.bands.risk_labels', []);
                $comparison = $baselineComparator->compare($result, $baseline, (array) config('cognitive-load.gate', []), $riskLabels);
            }
        }

        $detailed = (bool) $this->option('detailed');

        if ($this->option('format') === 'json') {
            $this->line((new JsonFormatter)->render($result, $detailed, $comparison));
        } else {
            (new ConsoleTableFormatter)->render($this, $result, $detailed, $comparison);
        }

        if ($outputPath = $this->option('output')) {
            $files->put($outputPath, (new JsonFormatter)->render($result, true, $comparison));
        }

        if ((bool) $this->option('fail-on-regression') && $comparison !== null && ! $comparison->passed) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
