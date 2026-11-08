<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\While_;

class ConstantCondition extends AbstractRule implements FileRule
{
    public function id(): string
    {
        return 'SL206';
    }

    public function name(): string
    {
        return 'Constant condition';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::Medium;
    }

    public function check(FileContext $context): array
    {
        $findings = [];

        $nodes = $context->finder->find($context->ast, fn (Node $node): bool => $node instanceof If_
            || $node instanceof ElseIf_
            || $node instanceof Ternary
            || $node instanceof While_);

        foreach ($nodes as $node) {
            $literal = $this->literal($node->cond);

            // `while (true)` is the idiomatic infinite loop; only a never-entered loop is sloppy.
            if ($literal === null || ($node instanceof While_ && $literal === 'true')) {
                continue;
            }

            $findings[] = $this->finding(
                $context,
                $node,
                "Condition is always {$literal}.",
                $literal === 'true'
                    ? 'Remove the condition and keep its body. If this was a debugging toggle, delete the branch you do not need.'
                    : 'Remove the dead branch, or put the real condition back.',
                snippet: $node instanceof Ternary ? $node : $node->cond,
            );
        }

        return $findings;
    }

    private function literal(Node $cond): ?string
    {
        if (! $cond instanceof ConstFetch) {
            return null;
        }

        $name = $cond->name->toLowerString();

        return in_array($name, ['true', 'false', 'null'], true) ? $name : null;
    }
}
