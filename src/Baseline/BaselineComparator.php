<?php

namespace Grafite\CognitiveLoad\Baseline;

use Grafite\CognitiveLoad\Results\ClassResult;
use Grafite\CognitiveLoad\Results\ProjectResult;
use Grafite\CognitiveLoad\Scoring\ScoreBand;

class BaselineComparator
{
    /**
     * @param  array{overall_margin: float, class_margin: float}  $gateConfig
     * @param  string[]  $riskLabels  Worst-to-best vocabulary, e.g. ['VeryHigh','High','Medium','Low']
     */
    public function compare(ProjectResult $current, array $baseline, array $gateConfig, array $riskLabels): ComparisonResult
    {
        $overallMargin = (float) ($gateConfig['overall_margin'] ?? 5.0);
        $classMargin = (float) ($gateConfig['class_margin'] ?? 8.0);

        $baselineOverallScore = (float) ($baseline['overall_score'] ?? 100.0);
        $overallDelta = $baselineOverallScore - $current->overallScore;
        $overallRegressed = $overallDelta > $overallMargin;

        $baselineClasses = (array) ($baseline['classes'] ?? []);
        $classRegressions = [];

        foreach ($current->classes as $class) {
            /** @var ClassResult $class */
            $prior = $baselineClasses[$class->fqcn] ?? null;

            if ($prior === null) {
                continue;
            }

            $priorScore = (float) ($prior['overall_score'] ?? 100.0);
            $priorRiskBand = (string) ($prior['risk_band'] ?? 'Low');
            $classDelta = $priorScore - $class->overallScore;
            $bandMoved = ScoreBand::rank($class->riskBand, $riskLabels) < ScoreBand::rank($priorRiskBand, $riskLabels);

            if ($classDelta > $classMargin || $bandMoved) {
                $classRegressions[] = new ClassRegression(
                    fqcn: $class->fqcn,
                    baselineScore: $priorScore,
                    currentScore: $class->overallScore,
                    delta: $classDelta,
                    baselineRiskBand: $priorRiskBand,
                    currentRiskBand: $class->riskBand,
                    bandMoved: $bandMoved,
                );
            }
        }

        return new ComparisonResult(
            overallDelta: $overallDelta,
            overallRegressed: $overallRegressed,
            classRegressions: $classRegressions,
            passed: ! $overallRegressed && $classRegressions === [],
        );
    }
}
