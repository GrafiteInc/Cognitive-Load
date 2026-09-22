<?php

namespace Grafite\CognitiveLoad\Output;

use Grafite\CognitiveLoad\Baseline\ComparisonResult;
use Grafite\CognitiveLoad\Results\ClassResult;
use Grafite\CognitiveLoad\Results\MethodResult;
use Grafite\CognitiveLoad\Results\ProjectResult;
use Illuminate\Console\Command;

class ConsoleTableFormatter
{
    public function render(Command $command, ProjectResult $result, bool $detailed, ?ComparisonResult $comparison = null): void
    {
        $command->info(sprintf(
            'Overall: %.1f/100 (%s)  Complexity: %.1f  Architecture: %.1f  Classes: %d',
            $result->overallScore,
            $result->band,
            $result->complexityCategoryScore,
            $result->architectureCategoryScore,
            count($result->classes),
        ));

        $command->table(
            ['Class', 'LOC', 'Methods', 'Coupling', 'Inheritance', 'Complexity', 'Architecture', 'Overall', 'Band'],
            array_map(fn (ClassResult $class) => [
                $class->fqcn,
                $class->loc,
                $class->methodCount,
                $class->couplingCount,
                $class->inheritanceDepth,
                number_format($class->complexityCategoryScore, 1),
                number_format($class->architectureCategoryScore, 1),
                number_format($class->overallScore, 1),
                $class->band,
            ], $result->classes),
        );

        if ($detailed) {
            foreach ($result->classes as $class) {
                if ($class->methods === []) {
                    continue;
                }

                $command->line("  <fg=gray>{$class->fqcn}</>");
                $command->table(
                    ['Method', 'Lines', 'Args', 'Returns', 'Vars', 'Prop Access', 'If', 'If Nesting', 'Else', 'Score'],
                    array_map(fn (MethodResult $method) => [
                        $method->name,
                        $method->rawMetrics['line_count'],
                        $method->rawMetrics['argument_count'],
                        $method->rawMetrics['return_count'],
                        $method->rawMetrics['variable_count'],
                        $method->rawMetrics['property_access_count'],
                        $method->rawMetrics['if_count'],
                        $method->rawMetrics['if_nesting_depth'],
                        $method->rawMetrics['else_count'],
                        number_format($method->totalScore, 3),
                    ], $class->methods),
                );
            }
        }

        foreach ($result->warnings as $warning) {
            $command->warn($warning);
        }

        if ($comparison === null) {
            return;
        }

        if ($comparison->passed) {
            $command->info(sprintf('Baseline comparison passed (overall delta %.1f).', $comparison->overallDelta));

            return;
        }

        $command->error(sprintf('Baseline comparison failed (overall delta %.1f).', $comparison->overallDelta));

        if ($comparison->classRegressions !== []) {
            $command->table(
                ['Class', 'Baseline', 'Current', 'Delta', 'Baseline Risk', 'Current Risk'],
                array_map(fn ($regression) => [
                    $regression->fqcn,
                    number_format($regression->baselineScore, 1),
                    number_format($regression->currentScore, 1),
                    number_format($regression->delta, 1),
                    $regression->baselineRiskBand,
                    $regression->currentRiskBand,
                ], $comparison->classRegressions),
            );
        }
    }
}
