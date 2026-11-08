<?php

namespace Grafite\CognitiveLoad\Sloppy\Baseline;

use Grafite\CognitiveLoad\Sloppy\Finding;
use Grafite\CognitiveLoad\Sloppy\Severity;
use Grafite\CognitiveLoad\Sloppy\SloppyReport;

class SloppyBaselineComparator
{
    /**
     * New findings at or above $failOn fail the comparison; findings already
     * recorded in the baseline are tolerated.
     */
    public function compare(SloppyReport $current, array $baseline, Severity $failOn): SloppyComparisonResult
    {
        $known = (array) ($baseline['findings'] ?? []);
        $currentFingerprints = [];
        $new = [];

        foreach ($current->findings as $finding) {
            $currentFingerprints[$finding->fingerprint] = true;

            if (! isset($known[$finding->fingerprint])) {
                $new[] = $finding;
            }
        }

        $resolved = array_values(array_filter(
            array_map('strval', array_keys($known)),
            fn (string $fingerprint): bool => ! isset($currentFingerprints[$fingerprint]),
        ));

        $blocking = array_filter($new, fn (Finding $finding): bool => $finding->severity->atLeast($failOn));

        return new SloppyComparisonResult(
            newFindings: $new,
            resolvedFingerprints: $resolved,
            failOn: $failOn->value,
            passed: $blocking === [],
        );
    }
}
