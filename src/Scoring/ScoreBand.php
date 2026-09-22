<?php

namespace Grafite\CognitiveLoad\Scoring;

/**
 * A single tier engine driven by one ascending set of thresholds, exposing
 * two label vocabularies (risk / grade) over the same tiers so the gate
 * logic and the human-facing labels never drift out of sync.
 */
class ScoreBand
{
    /**
     * @param  int[]|float[]  $thresholds  Ascending, e.g. [40, 60, 80]
     */
    public static function tierIndex(float $score, array $thresholds): int
    {
        $tier = 0;

        foreach ($thresholds as $threshold) {
            if ($score >= $threshold) {
                $tier++;
            }
        }

        return $tier;
    }

    /**
     * @param  string[]  $labels  Worst-to-best, one entry per tier
     */
    public static function label(float $score, array $thresholds, array $labels): string
    {
        $index = min(self::tierIndex($score, $thresholds), count($labels) - 1);

        return $labels[$index] ?? 'Unknown';
    }

    /**
     * Rank of a label within its vocabulary: 0 = worst tier. Used to detect
     * whether a class moved to a worse risk band between two runs.
     *
     * @param  string[]  $labels
     */
    public static function rank(string $label, array $labels): int
    {
        $index = array_search($label, $labels, true);

        return $index === false ? 0 : $index;
    }
}
