<?php

namespace Tests\Unit\Scoring;

use Grafite\CognitiveLoad\Results\MethodResult;
use Grafite\CognitiveLoad\Scoring\ComplexityCategoryScorer;
use Tests\TestCase;

class ComplexityCategoryScorerTest extends TestCase
{
    private function method(float $totalScore): MethodResult
    {
        return new MethodResult('m', [], [], $totalScore);
    }

    public function test_zero_raw_score_yields_exactly_one_hundred(): void
    {
        $scorer = new ComplexityCategoryScorer;

        $this->assertSame(100.0, $scorer->score([$this->method(0.0), $this->method(0.0)], 40.0));
    }

    public function test_score_strictly_decreases_and_stays_bounded_as_raw_grows(): void
    {
        $scorer = new ComplexityCategoryScorer;

        $scoreForSmallRaw = $scorer->score([$this->method(5.0)], 40.0);
        $scoreForLargeRaw = $scorer->score([$this->method(50.0)], 40.0);

        $this->assertLessThan(100.0, $scoreForSmallRaw);
        $this->assertGreaterThan(0.0, $scoreForLargeRaw);
        $this->assertLessThan($scoreForSmallRaw, $scoreForLargeRaw);
    }
}
