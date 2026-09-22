<?php

namespace Grafite\CognitiveLoad\Baseline;

use JsonSerializable;

class ClassRegression implements JsonSerializable
{
    public function __construct(
        public readonly string $fqcn,
        public readonly float $baselineScore,
        public readonly float $currentScore,
        public readonly float $delta,
        public readonly string $baselineRiskBand,
        public readonly string $currentRiskBand,
        public readonly bool $bandMoved,
    ) {}

    public function toArray(): array
    {
        return [
            'fqcn' => $this->fqcn,
            'baseline_score' => $this->baselineScore,
            'current_score' => $this->currentScore,
            'delta' => $this->delta,
            'baseline_risk_band' => $this->baselineRiskBand,
            'current_risk_band' => $this->currentRiskBand,
            'band_moved' => $this->bandMoved,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
