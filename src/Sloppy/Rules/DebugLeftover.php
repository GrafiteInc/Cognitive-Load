<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

class DebugLeftover extends AbstractRule implements FileRule
{
    private const DEFAULT_FUNCTIONS = ['dd', 'ddd', 'dump', 'var_dump', 'print_r', 'var_export', 'ray'];

    // These only print when their "return" argument is omitted or false.
    private const RETURNABLE = ['print_r', 'var_export'];

    // `->dump()` is left out on purpose: it is a regular method on dumper/exporter classes.
    private const METHODS = ['dd', 'ddrawsql', 'dumprawsql'];

    public function id(): string
    {
        return 'SL105';
    }

    public function name(): string
    {
        return 'Debug leftover';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::High;
    }

    public function check(FileContext $context): array
    {
        $functions = array_map('strtolower', (array) $this->option('functions', self::DEFAULT_FUNCTIONS));
        $findings = [];

        foreach ($context->findInstanceOf(FuncCall::class) as $call) {
            if (! $call->name instanceof Name) {
                continue;
            }

            $name = strtolower(ltrim($call->name->toString(), '\\'));

            if (! in_array($name, $functions, true) || $this->returnsInstead($name, $call)) {
                continue;
            }

            $findings[] = $this->finding(
                $context,
                $call,
                "Debug call {$name}() left in code.",
                'Remove it, or replace it with Log::debug() if the output is genuinely needed.',
            );
        }

        foreach ($context->findInstanceOf(MethodCall::class) as $call) {
            if (! $call->name instanceof Identifier || ! in_array($call->name->toLowerString(), self::METHODS, true)) {
                continue;
            }

            $findings[] = $this->finding(
                $context,
                $call,
                "Debug call ->{$call->name->toString()}() left in a chain.",
                'Remove it before shipping. It dumps (and may halt) in production.',
                confidence: 'medium',
            );
        }

        return $findings;
    }

    private function returnsInstead(string $name, FuncCall $call): bool
    {
        if (! in_array($name, self::RETURNABLE, true)) {
            return false;
        }

        $second = $call->args[1]->value ?? null;

        return $second !== null && ! self::isBoolLiteral($second, false);
    }
}
