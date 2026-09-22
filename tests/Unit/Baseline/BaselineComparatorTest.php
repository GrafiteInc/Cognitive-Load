<?php

namespace Tests\Unit\Baseline;

use Grafite\CognitiveLoad\Baseline\BaselineComparator;
use Grafite\CognitiveLoad\Results\ClassResult;
use Grafite\CognitiveLoad\Results\ProjectResult;
use Tests\TestCase;

class BaselineComparatorTest extends TestCase
{
    private const RISK_LABELS = ['VeryHigh', 'High', 'Medium', 'Low'];

    private const GATE = ['overall_margin' => 5.0, 'class_margin' => 8.0];

    private function classResult(string $fqcn, float $overallScore, string $riskBand): ClassResult
    {
        return new ClassResult($fqcn, "{$fqcn}.php", 10, 1, 0, 0, $overallScore, $overallScore, $overallScore, 'Good', $riskBand, []);
    }

    private function project(ClassResult ...$classes): ProjectResult
    {
        $overall = count($classes) > 0
            ? array_sum(array_map(fn ($c) => $c->overallScore, $classes)) / count($classes)
            : 100.0;

        return new ProjectResult('now', ['app'], $overall, $overall, $overall, 'Good', $classes, []);
    }

    private function baseline(array $classes, float $overallScore): array
    {
        return ['overall_score' => $overallScore, 'classes' => $classes];
    }

    public function test_improvement_passes(): void
    {
        $current = $this->project($this->classResult('App\\Foo', 90.0, 'Low'));
        $baseline = $this->baseline(['App\\Foo' => ['overall_score' => 70.0, 'risk_band' => 'Medium']], 70.0);

        $comparison = (new BaselineComparator)->compare($current, $baseline, self::GATE, self::RISK_LABELS);

        $this->assertTrue($comparison->passed);
        $this->assertSame([], $comparison->classRegressions);
    }

    public function test_small_regression_within_margin_passes(): void
    {
        $current = $this->project($this->classResult('App\\Foo', 85.0, 'Low'));
        $baseline = $this->baseline(['App\\Foo' => ['overall_score' => 88.0, 'risk_band' => 'Low']], 88.0);

        $comparison = (new BaselineComparator)->compare($current, $baseline, self::GATE, self::RISK_LABELS);

        $this->assertTrue($comparison->passed);
    }

    public function test_large_overall_regression_fails(): void
    {
        $current = $this->project($this->classResult('App\\Foo', 50.0, 'Medium'));
        $baseline = $this->baseline(['App\\Foo' => ['overall_score' => 90.0, 'risk_band' => 'Low']], 90.0);

        $comparison = (new BaselineComparator)->compare($current, $baseline, self::GATE, self::RISK_LABELS);

        $this->assertFalse($comparison->passed);
        $this->assertTrue($comparison->overallRegressed);
    }

    public function test_risk_band_tier_move_fails_even_within_the_point_margin(): void
    {
        $current = $this->project($this->classResult('App\\Foo', 82.0, 'Medium'));
        $baseline = $this->baseline(['App\\Foo' => ['overall_score' => 85.0, 'risk_band' => 'Low']], 85.0);

        $comparison = (new BaselineComparator)->compare($current, $baseline, self::GATE, self::RISK_LABELS);

        $this->assertFalse($comparison->passed);
        $this->assertCount(1, $comparison->classRegressions);
        $this->assertTrue($comparison->classRegressions[0]->bandMoved);
    }

    public function test_new_class_with_no_baseline_entry_is_ignored(): void
    {
        $current = $this->project($this->classResult('App\\NewClass', 20.0, 'VeryHigh'));
        $baseline = $this->baseline([], 100.0);

        $comparison = (new BaselineComparator)->compare($current, $baseline, self::GATE, self::RISK_LABELS);

        $this->assertSame([], $comparison->classRegressions);
    }
}
