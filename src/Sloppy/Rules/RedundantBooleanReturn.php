<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Return_;

class RedundantBooleanReturn extends AbstractRule implements FileRule
{
    private const ADVICE = 'Return the condition itself, e.g. `return $condition;`, or cast with (bool) if it is not already a boolean.';

    public function id(): string
    {
        return 'SL205';
    }

    public function name(): string
    {
        return 'Redundant boolean return';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::Low;
    }

    public function check(FileContext $context): array
    {
        $findings = [];

        foreach ($context->findInstanceOf(Return_::class) as $return) {
            $expr = $return->expr;

            if ($expr instanceof Ternary && $expr->if !== null && $this->areOpposites($expr->if, $expr->else)) {
                $findings[] = $this->finding($context, $return, 'Ternary returns literal true/false.', self::ADVICE);
            }
        }

        foreach ($this->statementLists($context) as $stmts) {
            $stmts = array_values(array_filter($stmts, fn (Node $stmt): bool => ! $stmt instanceof Nop));

            foreach ($stmts as $index => $stmt) {
                if (! $stmt instanceof If_ || $stmt->elseifs !== []) {
                    continue;
                }

                $then = $this->singleBoolReturn($stmt->stmts);
                $otherwise = $stmt->else !== null
                    ? $this->singleBoolReturn($stmt->else->stmts)
                    : $this->singleBoolReturn(isset($stmts[$index + 1]) ? [$stmts[$index + 1]] : []);

                if ($then !== null && $otherwise !== null && $this->areOpposites($then, $otherwise)) {
                    $findings[] = $this->finding($context, $stmt, 'if/else only returns literal true/false.', self::ADVICE, snippet: $stmt->cond);
                }
            }
        }

        return $findings;
    }

    /**
     * @return array<int, Node[]>
     */
    private function statementLists(FileContext $context): array
    {
        $lists = [$context->ast];

        foreach ($context->finder->find($context->ast, fn (Node $node): bool => property_exists($node, 'stmts') && is_array($node->stmts)) as $node) {
            $lists[] = $node->stmts;
        }

        return $lists;
    }

    private function singleBoolReturn(array $stmts): ?ConstFetch
    {
        $stmts = array_values(array_filter($stmts, fn (Node $stmt): bool => ! $stmt instanceof Nop));

        if (count($stmts) !== 1 || ! $stmts[0] instanceof Return_ || $stmts[0]->expr === null) {
            return null;
        }

        return self::isBoolLiteral($stmts[0]->expr) ? $stmts[0]->expr : null;
    }

    private function areOpposites(Node $a, Node $b): bool
    {
        return self::isBoolLiteral($a) && self::isBoolLiteral($b)
            && $a->name->toLowerString() !== $b->name->toLowerString();
    }
}
