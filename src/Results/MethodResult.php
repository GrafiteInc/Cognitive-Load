<?php

namespace Grafite\CognitiveLoad\Results;

use JsonSerializable;

class MethodResult implements JsonSerializable
{
    /**
     * @param  array<string, int>  $rawMetrics
     * @param  array<string, float>  $perMetricScores
     */
    public function __construct(
        public readonly string $name,
        public readonly array $rawMetrics,
        public readonly array $perMetricScores,
        public readonly float $totalScore,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'raw_metrics' => $this->rawMetrics,
            'metric_scores' => $this->perMetricScores,
            'total_score' => $this->totalScore,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
