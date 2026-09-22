<?php

namespace Grafite\CognitiveLoad\Scoring;

class OverallGradeCalculator
{
    /**
     * @param  array{complexity: float, architecture: float}  $weights
     */
    public function combine(float $complexityScore, float $architectureScore, array $weights): float
    {
        $complexityWeight = $weights['complexity'] ?? 0.6;
        $architectureWeight = $weights['architecture'] ?? 0.4;
        $totalWeight = $complexityWeight + $architectureWeight;

        if ($totalWeight <= 0.0) {
            return ($complexityScore + $architectureScore) / 2;
        }

        return (($complexityScore * $complexityWeight) + ($architectureScore * $architectureWeight)) / $totalWeight;
    }
}
