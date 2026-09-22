<?php

namespace Tests\Unit\Ast;

use Grafite\CognitiveLoad\Ast\MethodMetricsVisitor;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Tests\TestCase;

class MethodMetricsVisitorTest extends TestCase
{
    private function firstMethod(string $classBody): ClassMethod
    {
        $code = "<?php\nclass Fixture {\n{$classBody}\n}\n";
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);

        return (new NodeFinder)->findFirstInstanceOf($ast, ClassMethod::class);
    }

    public function test_it_scores_zero_complexity_for_a_trivial_method(): void
    {
        $metrics = MethodMetricsVisitor::analyze($this->firstMethod('public function noop(): void {}'));

        $this->assertSame(0, $metrics->argumentCount);
        $this->assertSame(0, $metrics->returnCount);
        $this->assertSame(0, $metrics->ifCount);
        $this->assertSame(0, $metrics->ifNestingDepth);
        $this->assertSame(0, $metrics->elseCount);
        $this->assertSame(0, $metrics->propertyAccessCount);
        $this->assertSame(1, $metrics->cyclomaticComplexity);
    }

    public function test_it_counts_nested_if_else_and_property_access(): void
    {
        $method = $this->firstMethod(<<<'PHP'
            public function process(int $a, int $b): int
            {
                $x = $a + $b;
                if ($x > 10) {
                    if ($b > 5) {
                        $x = $x - $this->offset;
                    }
                } else {
                    $x = $x + $this->offset;
                }
                return $x;
            }
            PHP);

        $metrics = MethodMetricsVisitor::analyze($method);

        $this->assertSame(2, $metrics->argumentCount);
        $this->assertSame(1, $metrics->returnCount);
        $this->assertSame(2, $metrics->ifCount);
        $this->assertSame(2, $metrics->ifNestingDepth);
        $this->assertSame(1, $metrics->elseCount);
        $this->assertSame(2, $metrics->propertyAccessCount);
        $this->assertSame(3, $metrics->variableCount);
        $this->assertSame(3, $metrics->cyclomaticComplexity);
    }

    public function test_it_counts_multiple_returns_without_double_counting_elseif_as_else(): void
    {
        $method = $this->firstMethod(<<<'PHP'
            public function grade(int $score): string
            {
                if ($score > 90) {
                    return 'A';
                } elseif ($score > 70) {
                    return 'B';
                } else {
                    return 'C';
                }
            }
            PHP);

        $metrics = MethodMetricsVisitor::analyze($method);

        $this->assertSame(3, $metrics->returnCount);
        $this->assertSame(1, $metrics->ifCount);
        $this->assertSame(1, $metrics->elseCount);
        $this->assertSame(1, $metrics->ifNestingDepth);
        $this->assertSame(3, $metrics->cyclomaticComplexity);
    }
}
