<?php

namespace Grafite\CognitiveLoad\Scoring;

class MetricScorer
{
    /**
     * Score is 0 while the value stays at or below the threshold, then grows
     * logarithmically past it so a handful of metrics all being a little
     * over their threshold doesn't dominate a single wildly over one.
     */
    public function score(float $value, float $threshold, float $scale): float
    {
        if ($value <= $threshold) {
            return 0.0;
        }

        return $scale * log(1 + (($value - $threshold) / 10));
    }
}
