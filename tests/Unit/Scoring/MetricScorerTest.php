<?php

namespace Tests\Unit\Scoring;

use Grafite\CognitiveLoad\Scoring\MetricScorer;
use Tests\TestCase;

class MetricScorerTest extends TestCase
{
    public function test_value_at_or_below_threshold_scores_zero(): void
    {
        $scorer = new MetricScorer;

        $this->assertSame(0.0, $scorer->score(5, 10, 3.0));
        $this->assertSame(0.0, $scorer->score(10, 10, 3.0));
    }

    public function test_value_above_threshold_scores_positive_and_grows_monotonically(): void
    {
        $scorer = new MetricScorer;

        $low = $scorer->score(20, 10, 3.0);
        $high = $scorer->score(40, 10, 3.0);

        $this->assertGreaterThan(0.0, $low);
        $this->assertGreaterThan($low, $high);
    }

    public function test_scale_multiplies_the_score_linearly(): void
    {
        $scorer = new MetricScorer;

        $base = $scorer->score(30, 10, 1.0);
        $scaled = $scorer->score(30, 10, 4.0);

        $this->assertEqualsWithDelta($base * 4, $scaled, 0.00001);
    }
}
