<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\CallChain;
use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\LaravelHeuristics;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\While_;

class QueryInLoop extends AbstractRule implements FileRule
{
    public function id(): string
    {
        return 'SL301';
    }

    public function name(): string
    {
        return 'Query in loop';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::Medium;
    }

    public function check(FileContext $context): array
    {
        $findings = [];
        $seen = [];

        $loops = $context->finder->find($context->ast, fn (Node $node): bool => $node instanceof Foreach_
            || $node instanceof For_
            || $node instanceof While_
            || $node instanceof Do_);

        foreach ($loops as $loop) {
            $calls = $context->finder->find($loop->stmts, fn (Node $node): bool => CallChain::isCall($node) && CallChain::isOutermost($node));

            foreach ($calls as $call) {
                if (isset($seen[spl_object_id($call)])) {
                    continue;
                }

                $chain = CallChain::of($call);
                $confidence = $this->queryConfidence($chain);

                if ($confidence === null) {
                    continue;
                }

                $seen[spl_object_id($call)] = true;

                $findings[] = $this->finding(
                    $context,
                    $call,
                    "Query {$chain->describe()} runs on every loop iteration.",
                    'Fetch what you need once before the loop (whereIn(), eager loading with with(), or keyBy() for lookups) and read from that inside the loop.',
                    confidence: $confidence,
                );
            }
        }

        return $findings;
    }

    private function queryConfidence(CallChain $chain): ?string
    {
        $names = $chain->names();
        $hasBuilder = array_filter($names, LaravelHeuristics::isBuilder(...)) !== [];
        $endsInQuery = LaravelHeuristics::isTerminator(end($names));

        if ($chain->isStatic()) {
            $class = $chain->staticClass();

            if (LaravelHeuristics::isDbFacade($class) || LaravelHeuristics::looksLikeModel($class)) {
                return ($hasBuilder || LaravelHeuristics::isTerminator($names[0])) ? 'high' : null;
            }

            return LaravelHeuristics::couldBeQueryable($class) && $hasBuilder && $endsInQuery ? 'medium' : null;
        }

        // `$user->posts()->where(...)->get()`: a zero-arg relation call on a variable, then query methods.
        $first = $chain->calls[0] ?? null;
        $looksLikeRelation = $chain->root instanceof Variable
            && $chain->root->name !== 'this'
            && $first !== null
            && count($names) > 1
            && $first->getArgs() === []
            && ! LaravelHeuristics::isBuilder($names[0])
            && ! LaravelHeuristics::isTerminator($names[0]);

        // Collections share first()/count()/pluck(); without a builder call only DB-only terminators count.
        $queryOnly = $hasBuilder || LaravelHeuristics::isQueryOnlyTerminator(end($names));

        return $looksLikeRelation && $endsInQuery && $queryOnly ? 'low' : null;
    }
}
