<?php

namespace Grafite\CognitiveLoad\Scoring;

use Grafite\CognitiveLoad\Results\MethodResult;

class ComplexityCategoryScorer
{
    /**
     * @param  MethodResult[]  $methods
     */
    public function score(array $methods, float $decay): float
    {
        $raw = array_sum(array_map(fn (MethodResult $m) => $m->totalScore, $methods));

        return $this->decay($raw, $decay);
    }

    private function decay(float $raw, float $decay): float
    {
        if ($decay <= 0.0) {
            return $raw <= 0.0 ? 100.0 : 0.0;
        }

        return 100.0 * exp(-$raw / $decay);
    }
}
