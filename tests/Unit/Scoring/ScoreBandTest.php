<?php

namespace Tests\Unit\Scoring;

use Grafite\CognitiveLoad\Scoring\ScoreBand;
use Tests\TestCase;

class ScoreBandTest extends TestCase
{
    private const THRESHOLDS = [40, 60, 80];

    private const RISK_LABELS = ['VeryHigh', 'High', 'Medium', 'Low'];

    public function test_tier_boundaries_are_inclusive_on_the_upper_side(): void
    {
        $this->assertSame(0, ScoreBand::tierIndex(39.9, self::THRESHOLDS));
        $this->assertSame(1, ScoreBand::tierIndex(40.0, self::THRESHOLDS));
        $this->assertSame(2, ScoreBand::tierIndex(60.0, self::THRESHOLDS));
        $this->assertSame(3, ScoreBand::tierIndex(80.0, self::THRESHOLDS));
        $this->assertSame(3, ScoreBand::tierIndex(100.0, self::THRESHOLDS));
    }

    public function test_label_maps_tier_to_the_matching_vocabulary_entry(): void
    {
        $this->assertSame('VeryHigh', ScoreBand::label(10.0, self::THRESHOLDS, self::RISK_LABELS));
        $this->assertSame('Low', ScoreBand::label(90.0, self::THRESHOLDS, self::RISK_LABELS));
    }

    public function test_rank_orders_worst_to_best(): void
    {
        $this->assertSame(0, ScoreBand::rank('VeryHigh', self::RISK_LABELS));
        $this->assertSame(3, ScoreBand::rank('Low', self::RISK_LABELS));
        $this->assertLessThan(ScoreBand::rank('Medium', self::RISK_LABELS), ScoreBand::rank('High', self::RISK_LABELS));
    }
}
