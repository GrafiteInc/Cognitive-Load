<?php

namespace Grafite\CognitiveLoad\Scoring;

use Grafite\CognitiveLoad\Ast\ClassFacts;

class ArchitectureCategoryScorer
{
    public function __construct(private readonly MetricScorer $scorer = new MetricScorer) {}

    /**
     * @param  array<string, array{threshold: float, scale: float}>  $architectureMetricsConfig
     */
    public function score(ClassFacts $facts, int $inheritanceDepth, array $architectureMetricsConfig, float $decay): float
    {
        $values = [
            'class_loc' => $facts->classLoc,
            'method_count' => count($facts->methods),
            'coupling_count' => $facts->couplingCount,
            'inheritance_depth' => $inheritanceDepth,
        ];

        $raw = 0.0;

        foreach ($architectureMetricsConfig as $key => $settings) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $raw += $this->scorer->score((float) $values[$key], (float) $settings['threshold'], (float) $settings['scale']);
        }

        return $decay <= 0.0
            ? ($raw <= 0.0 ? 100.0 : 0.0)
            : 100.0 * exp(-$raw / $decay);
    }
}
