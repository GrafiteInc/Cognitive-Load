<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Nop;

class SwallowedException extends AbstractRule implements FileRule
{
    public function id(): string
    {
        return 'SL101';
    }

    public function name(): string
    {
        return 'Swallowed exception';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::High;
    }

    public function check(FileContext $context): array
    {
        $findings = [];

        foreach ($context->findInstanceOf(Catch_::class) as $catch) {
            $statements = array_filter($catch->stmts, fn ($stmt): bool => ! $stmt instanceof Nop);

            if ($statements !== []) {
                continue;
            }

            $types = implode('|', array_map(fn (Name $type): string => $type->getLast(), $catch->types));
            $commented = $catch->stmts !== [];

            $findings[] = $this->finding(
                $context,
                $catch,
                "Caught {$types} is silently discarded".($commented ? ' (the catch block only contains a comment).' : '.'),
                'Handle, log via report(), or rethrow the exception. If ignoring it is truly intended, narrow the caught type and call report() so failures stay visible.',
                confidence: $commented ? 'medium' : 'high',
                severity: $commented ? Severity::Low : null,
            );
        }

        return $findings;
    }
}
