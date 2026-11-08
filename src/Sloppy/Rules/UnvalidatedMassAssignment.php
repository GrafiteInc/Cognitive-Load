<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

class UnvalidatedMassAssignment extends AbstractRule implements FileRule
{
    private const WRITERS = [
        'create', 'forcecreate', 'update', 'fill', 'forcefill', 'insert', 'upsert',
        'updateorcreate', 'firstorcreate', 'firstornew',
    ];

    // Request methods that hand back every submitted field.
    private const RAW_INPUT = ['all', 'input', 'except', 'post'];

    public function id(): string
    {
        return 'SL306';
    }

    public function name(): string
    {
        return 'Unvalidated mass assignment';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::High;
    }

    public function check(FileContext $context): array
    {
        $findings = [];

        $calls = $context->finder->find($context->ast, fn (Node $node): bool => ($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall)
            && $node->name instanceof Identifier
            && in_array($node->name->toLowerString(), self::WRITERS, true));

        foreach ($calls as $call) {
            foreach ($call->getArgs() as $arg) {
                if (! $this->isRawRequestInput($arg->value)) {
                    continue;
                }

                $findings[] = $this->finding(
                    $context,
                    $call,
                    "Raw request input is passed straight to {$call->name->toString()}().",
                    'Pass $request->validated() (from a Form Request) or $request->safe()->only([...]) so only validated fields are written.',
                );

                break;
            }
        }

        return $findings;
    }

    private function isRawRequestInput(Node $value): bool
    {
        if ($value instanceof StaticCall) {
            return $value->class instanceof Name
                && strtolower($value->class->getLast()) === 'request'
                && $this->isRawInputMethod($value);
        }

        return $value instanceof MethodCall
            && $this->isRawInputMethod($value)
            && $this->isRequest($value->var);
    }

    private function isRawInputMethod(MethodCall|StaticCall $call): bool
    {
        if (! $call->name instanceof Identifier) {
            return false;
        }

        $method = $call->name->toLowerString();

        // input()/post() only return everything when called without a key.
        if (in_array($method, ['input', 'post'], true)) {
            return $call->getArgs() === [];
        }

        return in_array($method, self::RAW_INPUT, true);
    }

    private function isRequest(Node $node): bool
    {
        return match (true) {
            $node instanceof Variable => $node->name === 'request',
            $node instanceof FuncCall => $node->name instanceof Name && strtolower($node->name->toString()) === 'request' && $node->getArgs() === [],
            $node instanceof PropertyFetch => $node->name instanceof Identifier && $node->name->toString() === 'request',
            default => false,
        };
    }
}
