<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\CallChain;
use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\LaravelHeuristics;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Foreach_;

class PossibleNPlusOne extends AbstractRule implements FileRule
{
    private const EAGER_LOADERS = ['with', 'load', 'loadmissing', 'loadcount', 'withcount', 'loadaggregate'];

    public function id(): string
    {
        return 'SL303';
    }

    public function name(): string
    {
        return 'Possible N+1';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::Low;
    }

    public function check(FileContext $context): array
    {
        $findings = [];

        foreach ($context->findInstanceOf(Foreach_::class) as $loop) {
            if (! $loop->valueVar instanceof Variable || ! is_string($loop->valueVar->name)) {
                continue;
            }

            $scope = $this->scopeOf($context, $loop);

            if (! $this->iteratesModels($context, $loop->expr, $scope) || $this->eagerLoads($context, $scope)) {
                continue;
            }

            $item = $loop->valueVar->name;
            $relation = $this->firstRelationAccess($context, $loop, $item);

            if ($relation === null) {
                continue;
            }

            $name = $relation->name->toString();

            $findings[] = $this->finding(
                $context,
                $relation,
                "\${$item}->{$name} is read on every iteration over a query result; if it is a relation, it is lazy-loaded once per row.",
                "Eager load it with ->with('{$name}') on the query, or ->load('{$name}') before the loop.",
                confidence: 'low',
                snippet: "{$item}->{$name}",
            );
        }

        return $findings;
    }

    /**
     * @return Node[]
     */
    private function scopeOf(FileContext $context, Foreach_ $loop): array
    {
        $function = $context->enclosingFunction($loop);

        return $function !== null ? ($function->getStmts() ?? []) : $context->ast;
    }

    /**
     * The loop iterates a model fetch directly, or a variable assigned from one in the same scope.
     *
     * @param  Node[]  $scope
     */
    private function iteratesModels(FileContext $context, Expr $iterable, array $scope): bool
    {
        if ($iterable instanceof Variable && is_string($iterable->name)) {
            $assignments = $context->finder->find($scope, fn (Node $node): bool => $node instanceof Assign
                && $node->var instanceof Variable
                && $node->var->name === $iterable->name);

            foreach ($assignments as $assignment) {
                if ($this->iteratesModels($context, $assignment->expr, [])) {
                    return true;
                }
            }

            return false;
        }

        return CallChain::isCall($iterable) && LaravelHeuristics::fetchesModels(CallChain::of($iterable));
    }

    /**
     * Finds `$item->relation->x` or `$item->relation->method()`, returning the `$item->relation` fetch.
     */
    private function firstRelationAccess(FileContext $context, Foreach_ $loop, string $item): ?PropertyFetch
    {
        $match = $context->finder->findFirst($loop->stmts, function (Node $node) use ($item): bool {
            if (! $node instanceof PropertyFetch && ! $node instanceof MethodCall) {
                return false;
            }

            $inner = $node->var;

            return $inner instanceof PropertyFetch
                && $inner->name instanceof Identifier
                && $inner->var instanceof Variable
                && $inner->var->name === $item;
        });

        return $match?->var;
    }

    /**
     * @param  Node[]  $scope
     */
    private function eagerLoads(FileContext $context, array $scope): bool
    {
        return $context->finder->findFirst($scope, fn (Node $node): bool => ($node instanceof MethodCall || $node instanceof StaticCall)
            && $node->name instanceof Identifier
            && in_array($node->name->toLowerString(), self::EAGER_LOADERS, true)) !== null;
    }
}
