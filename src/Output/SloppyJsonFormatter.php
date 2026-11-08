<?php

namespace Grafite\CognitiveLoad\Output;

use Grafite\CognitiveLoad\Sloppy\Baseline\SloppyComparisonResult;
use Grafite\CognitiveLoad\Sloppy\SloppyReport;

class SloppyJsonFormatter
{
    public function render(SloppyReport $report, ?SloppyComparisonResult $comparison = null): string
    {
        $payload = $report->toArray();

        if ($comparison !== null) {
            $payload['comparison'] = $comparison->toArray();
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }
}
