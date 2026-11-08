<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\ClassMemberUsage;
use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Severity;

class UnusedPrivateMethod extends AbstractRule implements FileRule
{
    public function id(): string
    {
        return 'SL202';
    }

    public function name(): string
    {
        return 'Unused private method';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::Medium;
    }

    public function check(FileContext $context): array
    {
        $findings = [];

        foreach ($context->classes() as $class) {
            $usage = new ClassMemberUsage($class);

            foreach ($class->getMethods() as $method) {
                if (! $method->isPrivate() || $method->isMagic() || $usage->callsMethod($method->name->toString())) {
                    continue;
                }

                $findings[] = $this->finding(
                    $context,
                    $method,
                    "Private method {$method->name->toString()}() is never called.",
                    'Delete it. Private methods cannot be called from outside the class, so nothing depends on it.',
                    confidence: $usage->usesTraits ? 'medium' : 'high',
                    snippet: 'method:'.$method->name->toLowerString(),
                );
            }
        }

        return $findings;
    }
}
