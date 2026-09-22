<?php

namespace Grafite\CognitiveLoad\Scoring;

use Grafite\CognitiveLoad\Ast\MethodRawMetrics;
use Grafite\CognitiveLoad\Results\MethodResult;

class MethodScoreCalculator
{
    public function __construct(private readonly MetricScorer $scorer = new MetricScorer) {}

    /**
     * @param  array<string, array{enabled: bool, threshold: float, scale: float}>  $metricsConfig
     */
    public function calculate(MethodRawMetrics $raw, array $metricsConfig): MethodResult
    {
        $rawValues = $raw->toArray();
        $perMetricScores = [];
        $totalScore = 0.0;

        foreach ($metricsConfig as $key => $settings) {
            if (! ($settings['enabled'] ?? true) || ! array_key_exists($key, $rawValues)) {
                continue;
            }

            $score = $this->scorer->score((float) $rawValues[$key], (float) $settings['threshold'], (float) $settings['scale']);
            $perMetricScores[$key] = $score;
            $totalScore += $score;
        }

        return new MethodResult(
            name: $raw->name,
            rawMetrics: $rawValues,
            perMetricScores: $perMetricScores,
            totalScore: $totalScore,
        );
    }
}
