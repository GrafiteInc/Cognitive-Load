<?php

namespace Grafite\CognitiveLoad\Sloppy;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeFinder;

/**
 * How a class refers to its own members: which properties it reads, which
 * methods it calls on itself, and which string literals it contains (to
 * catch callables like [$this, 'method'] and `'method'` passed by name).
 */
class ClassMemberUsage
{
    /** @var array<string, true> */
    private array $propertyReads = [];

    /** @var array<string, true> */
    private array $methodCalls = [];

    /** @var array<string, true> */
    private array $strings = [];

    public readonly bool $usesTraits;

    public function __construct(Class_ $class)
    {
        $finder = new NodeFinder;
        $self = strtolower($class->namespacedName?->toString() ?? '');

        foreach ($finder->find($class->stmts, fn (Node $node): bool => true) as $node) {
            if (($node instanceof PropertyFetch || $node instanceof NullsafePropertyFetch) && self::isThis($node->var) && $node->name instanceof Identifier) {
                if (! self::isAssignmentTarget($node)) {
                    $this->propertyReads[$node->name->toString()] = true;
                }
            } elseif ($node instanceof StaticPropertyFetch && self::isSelf($node->class, $self) && $node->name instanceof Identifier) {
                if (! self::isAssignmentTarget($node)) {
                    $this->propertyReads[$node->name->toString()] = true;
                }
            } elseif (($node instanceof MethodCall || $node instanceof NullsafeMethodCall) && self::isThis($node->var) && $node->name instanceof Identifier) {
                $this->methodCalls[$node->name->toLowerString()] = true;
            } elseif ($node instanceof StaticCall && self::isSelf($node->class, $self) && $node->name instanceof Identifier) {
                $this->methodCalls[$node->name->toLowerString()] = true;
            } elseif ($node instanceof String_) {
                $this->strings[strtolower($node->value)] = true;
            }
        }

        $this->usesTraits = $finder->findFirstInstanceOf($class->stmts, TraitUse::class) !== null;
    }

    public function readsProperty(string $name): bool
    {
        return isset($this->propertyReads[$name]) || isset($this->strings[strtolower($name)]);
    }

    public function callsMethod(string $name): bool
    {
        $name = strtolower($name);

        return isset($this->methodCalls[$name]) || isset($this->strings[$name]);
    }

    private static function isThis(Node $node): bool
    {
        return $node instanceof Variable && $node->name === 'this';
    }

    private static function isSelf(Node $class, string $selfName): bool
    {
        if (! $class instanceof Name) {
            return false;
        }

        $name = $class->toLowerString();

        return in_array($name, ['self', 'static'], true) || ($selfName !== '' && $name === $selfName);
    }

    private static function isAssignmentTarget(Node $node): bool
    {
        $parent = $node->getAttribute('parent');

        return ($parent instanceof Assign || $parent instanceof AssignRef) && $parent->var === $node;
    }
}
