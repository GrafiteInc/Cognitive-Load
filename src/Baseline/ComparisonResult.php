<?php

namespace Grafite\CognitiveLoad\Baseline;

use JsonSerializable;

class ComparisonResult implements JsonSerializable
{
    /**
     * @param  ClassRegression[]  $classRegressions
     */
    public function __construct(
        public readonly float $overallDelta,
        public readonly bool $overallRegressed,
        public readonly array $classRegressions,
        public readonly bool $passed,
    ) {}

    public function toArray(): array
    {
        return [
            'overall_delta' => $this->overallDelta,
            'overall_regressed' => $this->overallRegressed,
            'class_regressions' => array_map(fn (ClassRegression $r) => $r->toArray(), $this->classRegressions),
            'passed' => $this->passed,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
