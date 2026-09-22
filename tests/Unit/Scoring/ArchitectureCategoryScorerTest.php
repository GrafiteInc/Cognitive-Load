<?php

namespace Tests\Unit\Scoring;

use Grafite\CognitiveLoad\Ast\ClassFacts;
use Grafite\CognitiveLoad\Scoring\ArchitectureCategoryScorer;
use Tests\TestCase;

class ArchitectureCategoryScorerTest extends TestCase
{
    private const CONFIG = [
        'class_loc' => ['threshold' => 200, 'scale' => 4.0],
        'method_count' => ['threshold' => 10, 'scale' => 3.0],
        'coupling_count' => ['threshold' => 6, 'scale' => 3.0],
        'inheritance_depth' => ['threshold' => 2, 'scale' => 4.0],
    ];

    private function facts(int $classLoc, int $methodCount, int $couplingCount): ClassFacts
    {
        return new ClassFacts('Fixture', 'Fixture.php', $classLoc, null, $couplingCount, array_fill(0, $methodCount, null));
    }

    public function test_small_class_within_thresholds_scores_exactly_one_hundred(): void
    {
        $scorer = new ArchitectureCategoryScorer;

        $score = $scorer->score($this->facts(50, 3, 2), 0, self::CONFIG, 30.0);

        $this->assertSame(100.0, $score);
    }

    public function test_large_coupled_class_scores_lower_than_a_small_one(): void
    {
        $scorer = new ArchitectureCategoryScorer;

        $small = $scorer->score($this->facts(50, 3, 2), 0, self::CONFIG, 30.0);
        $large = $scorer->score($this->facts(400, 20, 15), 3, self::CONFIG, 30.0);

        $this->assertLessThan($small, $large);
        $this->assertGreaterThanOrEqual(0.0, $large);
    }
}
