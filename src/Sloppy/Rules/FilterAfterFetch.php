<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\LaravelHeuristics;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

class FilterAfterFetch extends AbstractRule implements FileRule
{
    /** Collection method => what to do in the query instead. */
    private const ADVICE = [
        'count' => 'Call ->count() on the query so the database counts the rows.',
        'pluck' => 'Call ->pluck() on the query so only that column is selected.',
        'sum' => 'Call ->sum() on the query so the database does the aggregation.',
        'avg' => 'Call ->avg() on the query so the database does the aggregation.',
        'max' => 'Call ->max() on the query so the database does the aggregation.',
        'min' => 'Call ->min() on the query so the database does the aggregation.',
        'first' => 'Call ->first() on the query so only one row is loaded.',
        'firstwhere' => 'Use ->where(...)->first() on the query so only one row is loaded.',
        'isempty' => 'Use ->doesntExist() on the query.',
        'isnotempty' => 'Use ->exists() on the query.',
        'contains' => 'Use ->where(...)->exists() on the query.',
        'take' => 'Use ->limit() on the query.',
        'sortby' => 'Use ->orderBy() on the query.',
        'sortbydesc' => 'Use ->orderByDesc() on the query.',
        'unique' => 'Use ->distinct() on the query.',
    ];

    private const FILTERS = ['where', 'wherein', 'wherenotin', 'wherenull', 'wherenotnull', 'wherebetween', 'filter', 'reject'];

    public function id(): string
    {
        return 'SL302';
    }

    public function name(): string
    {
        return 'Filter after fetch';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::High;
    }

    public function check(FileContext $context): array
    {
        $findings = [];

        foreach ($context->findInstanceOf(MethodCall::class) as $call) {
            if (! $call->name instanceof Identifier) {
                continue;
            }

            $method = $call->name->toLowerString();
            $isFilter = in_array($method, self::FILTERS, true);

            if (! $isFilter && ! isset(self::ADVICE[$method])) {
                continue;
            }

            $fetch = $this->fetchDescription($call->var);

            if ($fetch === null) {
                continue;
            }

            $findings[] = $this->finding(
                $context,
                $call,
                "Every row is loaded with {$fetch} and then narrowed in PHP with ->{$call->name->toString()}().",
                $isFilter
                    ? 'Move the condition into the query (->where() before fetching) so the database filters the rows.'
                    : self::ADVICE[$method],
            );
        }

        return $findings;
    }

    private function fetchDescription(mixed $receiver): ?string
    {
        if ($receiver instanceof MethodCall
            && $receiver->name instanceof Identifier
            && $receiver->name->toLowerString() === 'get'
            && $receiver->getArgs() === []) {
            return '->get()';
        }

        if ($receiver instanceof StaticCall
            && $receiver->class instanceof Name
            && $receiver->name instanceof Identifier
            && $receiver->name->toLowerString() === 'all'
            && $receiver->getArgs() === []
            && LaravelHeuristics::couldBeQueryable($receiver->class->toString())) {
            return $receiver->class->getLast().'::all()';
        }

        return null;
    }
}
