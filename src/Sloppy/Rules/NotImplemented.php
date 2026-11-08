<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Scalar\String_;

class NotImplemented extends AbstractRule implements FileRule
{
    private const PATTERN = '/\b(not\s+(yet\s+)?implemented|todo|implement\s+me)\b/i';

    public function id(): string
    {
        return 'SL102';
    }

    public function name(): string
    {
        return 'Not implemented';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::High;
    }

    public function check(FileContext $context): array
    {
        $findings = [];

        foreach ($context->findInstanceOf(Throw_::class) as $throw) {
            $message = $this->exceptionMessage($throw);

            if ($message === null || preg_match(self::PATTERN, $message) !== 1) {
                continue;
            }

            $findings[] = $this->finding(
                $context,
                $throw,
                "Placeholder throw left in place: \"{$message}\".",
                'Finish the implementation, or remove the method if it is not needed yet. Placeholder throws pass static checks but fail at runtime.',
            );
        }

        return $findings;
    }

    private function exceptionMessage(Throw_ $throw): ?string
    {
        if (! $throw->expr instanceof New_) {
            return null;
        }

        $firstArg = $throw->expr->args[0] ?? null;
        $value = $firstArg?->value ?? null;

        return $value instanceof String_ ? $value->value : null;
    }
}
