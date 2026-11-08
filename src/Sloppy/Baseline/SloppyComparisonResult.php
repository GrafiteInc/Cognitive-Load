<?php

namespace Grafite\CognitiveLoad\Sloppy\Baseline;

use Grafite\CognitiveLoad\Sloppy\Finding;

class SloppyComparisonResult
{
    /**
     * @param  Finding[]  $newFindings
     * @param  string[]  $resolvedFingerprints
     */
    public function __construct(
        public readonly array $newFindings,
        public readonly array $resolvedFingerprints,
        public readonly string $failOn,
        public readonly bool $passed,
    ) {}

    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'fail_on' => $this->failOn,
            'new_count' => count($this->newFindings),
            'resolved_count' => count($this->resolvedFingerprints),
            'new' => array_map(fn (Finding $finding) => $finding->toArray(), $this->newFindings),
            'resolved' => $this->resolvedFingerprints,
        ];
    }
}
