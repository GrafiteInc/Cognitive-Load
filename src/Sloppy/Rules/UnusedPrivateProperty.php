<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\ClassMemberUsage;
use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Severity;

class UnusedPrivateProperty extends AbstractRule implements FileRule
{
    public function id(): string
    {
        return 'SL203';
    }

    public function name(): string
    {
        return 'Unused private property';
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

            foreach ($class->getProperties() as $property) {
                if (! $property->isPrivate() || $property->hooks !== []) {
                    continue;
                }

                foreach ($property->props as $item) {
                    $name = $item->name->toString();

                    if ($usage->readsProperty($name)) {
                        continue;
                    }

                    $findings[] = $this->finding(
                        $context,
                        $item,
                        "Private property \${$name} is never read.",
                        'Delete the property and any writes to it. State nothing reads is just noise.',
                        confidence: $usage->usesTraits ? 'medium' : 'high',
                        snippet: 'property:'.$name,
                    );
                }
            }
        }

        return $findings;
    }
}
