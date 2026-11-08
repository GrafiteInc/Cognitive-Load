<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\ClassMemberUsage;
use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;

class UnusedConstructorDependency extends AbstractRule implements FileRule
{
    public function id(): string
    {
        return 'SL201';
    }

    public function name(): string
    {
        return 'Unused constructor dependency';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::Medium;
    }

    public function check(FileContext $context): array
    {
        $findings = [];

        foreach ($context->classes() as $class) {
            $constructor = $class->getMethod('__construct');

            if ($constructor === null || $constructor->stmts === null) {
                continue;
            }

            $usage = new ClassMemberUsage($class);
            $bodyVariables = $this->variableNames($constructor->stmts);

            foreach ($constructor->params as $param) {
                if (! $param->var instanceof Variable || ! is_string($param->var->name)) {
                    continue;
                }

                $name = $param->var->name;

                if ($param->isPromoted()) {
                    if (! $param->isPrivate() || $usage->readsProperty($name)) {
                        continue;
                    }

                    $message = "Promoted dependency \${$name} is never read.";
                } elseif (isset($bodyVariables[$name])) {
                    continue;
                } else {
                    $message = "Constructor parameter \${$name} is never used.";
                }

                $findings[] = $this->finding(
                    $context,
                    $param,
                    $message,
                    'Remove the parameter (and its binding) so the class does not advertise a dependency it does not have.',
                    confidence: $usage->usesTraits ? 'medium' : 'high',
                    snippet: 'param:'.$name,
                );
            }
        }

        return $findings;
    }

    /**
     * @return array<string, true>
     */
    private function variableNames(array $stmts): array
    {
        $names = [];

        foreach ((new NodeFinder)->findInstanceOf($stmts, Variable::class) as $variable) {
            if (is_string($variable->name)) {
                $names[$variable->name] = true;
            }
        }

        return $names;
    }
}
