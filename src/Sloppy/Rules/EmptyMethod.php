<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node\Stmt\ClassMethod;

class EmptyMethod extends AbstractRule implements FileRule
{
    public function id(): string
    {
        return 'SL104';
    }

    public function name(): string
    {
        return 'Empty method';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::Medium;
    }

    public function check(FileContext $context): array
    {
        $findings = [];

        foreach ($context->findInstanceOf(ClassMethod::class) as $method) {
            // null = abstract/interface; a body holding only a comment is a documented no-op.
            if ($method->stmts !== []) {
                continue;
            }

            if ($method->name->toLowerString() === '__construct' && $method->params !== []) {
                continue;
            }

            $findings[] = $this->finding(
                $context,
                $method,
                "Method {$method->name->toString()}() has an empty body.",
                'Implement it, delete it, or add a comment explaining why a no-op is correct here.',
                confidence: 'medium',
                snippet: 'empty:'.$method->name->toLowerString(),
            );
        }

        return $findings;
    }
}
