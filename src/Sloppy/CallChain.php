<?php

namespace Grafite\CognitiveLoad\Sloppy;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * A fluent call chain such as `User::where(...)->orderBy(...)->get()`,
 * unwound from its outermost call back to its root.
 */
class CallChain
{
    /**
     * @param  Node  $root  The receiver of the first call: a class Name for static chains, otherwise an expression
     * @param  array<int, MethodCall|NullsafeMethodCall|StaticCall>  $calls  Ordered root-first
     */
    private function __construct(
        public readonly Node $root,
        public readonly array $calls,
    ) {}

    public static function of(Expr $outermost): self
    {
        $calls = [];
        $node = $outermost;

        while ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            array_unshift($calls, $node);
            $node = $node->var;
        }

        if ($node instanceof StaticCall) {
            array_unshift($calls, $node);
            $node = $node->class;
        }

        return new self($node, $calls);
    }

    public static function isCall(Node $node): bool
    {
        return $node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall;
    }

    /**
     * True when the call is not itself the receiver of a further chained call.
     */
    public static function isOutermost(Node $call): bool
    {
        $parent = $call->getAttribute('parent');

        return ! (($parent instanceof MethodCall || $parent instanceof NullsafeMethodCall) && $parent->var === $call);
    }

    /**
     * @return string[] Lower-cased method names, root-first ('?' for dynamic names)
     */
    public function names(): array
    {
        return array_map(
            fn (Node $call): string => $call->name instanceof Identifier ? $call->name->toLowerString() : '?',
            $this->calls,
        );
    }

    public function isStatic(): bool
    {
        return $this->root instanceof Name;
    }

    public function staticClass(): ?string
    {
        return $this->root instanceof Name ? ltrim($this->root->toString(), '\\') : null;
    }

    public function describe(): string
    {
        $parts = array_map(
            fn (Node $call): string => ($call->name instanceof Identifier ? $call->name->toString() : '{expr}').'()',
            $this->calls,
        );

        $prefix = $this->root instanceof Name ? $this->root->getLast().'::' : '';

        return $prefix.implode('->', $parts);
    }
}
