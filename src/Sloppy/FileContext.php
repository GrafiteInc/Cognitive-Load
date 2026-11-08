<?php

namespace Grafite\CognitiveLoad\Sloppy;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

/**
 * One parsed file handed to every rule, so each file is parsed exactly once.
 * Every node carries a `parent` attribute for walking back up the tree.
 */
class FileContext
{
    public readonly NodeFinder $finder;

    /** @var array<int, array{comment: Comment, node: Node}>|null */
    private ?array $comments = null;

    /**
     * @param  Node[]  $ast  Name-resolved AST
     */
    public function __construct(
        public readonly string $file,
        public readonly string $relativeFile,
        public readonly string $code,
        public readonly array $ast,
    ) {
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new ParentConnectingVisitor);
        $traverser->traverse($this->ast);

        $this->finder = new NodeFinder;
    }

    /**
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @return T[]
     */
    public function findInstanceOf(string $class): array
    {
        return $this->finder->findInstanceOf($this->ast, $class);
    }

    /**
     * @return Class_[]
     */
    public function classes(): array
    {
        return array_values(array_filter(
            $this->findInstanceOf(Class_::class),
            fn (Class_ $class): bool => ! $class->isAnonymous(),
        ));
    }

    /**
     * Nearest ancestor (or the node itself) that is an instance of one of the given classes.
     *
     * @param  class-string[]  $classes
     */
    public function enclosing(Node $node, array $classes): ?Node
    {
        $current = $node;

        while ($current !== null) {
            foreach ($classes as $class) {
                if ($current instanceof $class) {
                    return $current;
                }
            }

            $current = $current->getAttribute('parent');
        }

        return null;
    }

    public function enclosingFunction(Node $node): ?FunctionLike
    {
        $function = $this->enclosing($node, [FunctionLike::class]);

        return $function instanceof FunctionLike ? $function : null;
    }

    /**
     * Human-readable location: `Fqcn::method`, `Fqcn`, `function()` or the file itself.
     */
    public function symbolFor(Node $node): string
    {
        $method = $this->enclosing($node, [ClassMethod::class, Function_::class]);
        $class = $this->enclosing($node, [ClassLike::class]);
        $className = $class instanceof ClassLike ? $this->classNameOf($class) : null;

        if ($method instanceof ClassMethod && $className !== null) {
            return $className.'::'.$method->name->toString();
        }

        if ($method instanceof Function_) {
            return ($method->namespacedName?->toString() ?? $method->name->toString()).'()';
        }

        return $className ?? $this->relativeFile;
    }

    public function classNameOf(ClassLike $class): string
    {
        return $class->namespacedName?->toString() ?? $class->name?->toString() ?? 'class@anonymous';
    }

    /**
     * Every comment in the file, paired with the node it is attached to.
     *
     * @return array<int, array{comment: Comment, node: Node}>
     */
    public function comments(): array
    {
        if ($this->comments !== null) {
            return $this->comments;
        }

        $this->comments = [];

        foreach ($this->finder->find($this->ast, fn (Node $node): bool => $node->getComments() !== []) as $node) {
            foreach ($node->getComments() as $comment) {
                $this->comments[$comment->getStartFilePos()] ??= ['comment' => $comment, 'node' => $node];
            }
        }

        ksort($this->comments);

        return $this->comments = array_values($this->comments);
    }
}
