<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\LaravelHeuristics;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

class FatController extends AbstractRule implements FileRule
{
    public function id(): string
    {
        return 'SL305';
    }

    public function name(): string
    {
        return 'Fat controller';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::Medium;
    }

    public function check(FileContext $context): array
    {
        $maxActions = (int) $this->option('max_actions', 7);
        $findings = [];

        foreach ($context->classes() as $class) {
            if ($class->isAbstract() || ! $this->isController($class)) {
                continue;
            }

            $symbol = $context->classNameOf($class);
            $actions = count(array_filter(
                $class->getMethods(),
                fn ($method): bool => $method->isPublic() && ! $method->isStatic() && ! $method->isMagic(),
            ));

            if ($actions > $maxActions) {
                $findings[] = $this->finding(
                    $context,
                    $class,
                    "Controller has {$actions} public actions (max {$maxActions}).",
                    'Split it into resource or single-action controllers, or move the logic into action/service classes.',
                    snippet: 'actions',
                    symbol: $symbol,
                );
            }

            $dbCall = (new NodeFinder)->findFirst($class->stmts, fn ($node): bool => $node instanceof StaticCall
                && $node->class instanceof Name
                && LaravelHeuristics::isDbFacade($node->class->toString()));

            if ($dbCall !== null) {
                $findings[] = $this->finding(
                    $context,
                    $dbCall,
                    'Controller queries the database directly through the DB facade.',
                    'Move the query into a model scope, query object or action class, and keep the controller to HTTP concerns.',
                    snippet: 'db',
                    symbol: $symbol,
                );
            }
        }

        return $findings;
    }

    private function isController(Class_ $class): bool
    {
        return str_ends_with($class->name?->toString() ?? '', 'Controller')
            || ($class->extends !== null && str_ends_with($class->extends->getLast(), 'Controller'));
    }
}
