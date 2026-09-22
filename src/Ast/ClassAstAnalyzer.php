<?php

namespace Grafite\CognitiveLoad\Ast;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Parses a single PHP file into ClassFacts. Vendor/third-party code is never
 * parsed, so cross-file resolution (coupling, inheritance) only ever sees
 * what was included in the scanned path set.
 */
class ClassAstAnalyzer
{
    /**
     * @return array{classes: ClassFacts[], warning: ?string}
     */
    public function analyzeFile(string $file): array
    {
        $code = file_get_contents($file);

        if ($code === false) {
            return ['classes' => [], 'warning' => "Unable to read file: {$file}"];
        }

        try {
            $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);
        } catch (Error $e) {
            return ['classes' => [], 'warning' => "Parse error in {$file}: {$e->getMessage()}"];
        }

        if ($ast === null || $ast === []) {
            return ['classes' => [], 'warning' => null];
        }

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $ast = $traverser->traverse($ast);

        $finder = new NodeFinder;
        $useImports = $this->collectUseImports($finder, $ast);

        $classNodes = $finder->find(
            $ast,
            fn (Node $node): bool => ($node instanceof Class_ && ! $node->isAnonymous())
                || $node instanceof Trait_
                || $node instanceof Enum_
        );

        $classes = [];

        foreach ($classNodes as $node) {
            $classes[] = $this->buildClassFacts($node, $file, $useImports);
        }

        return ['classes' => $classes, 'warning' => null];
    }

    /**
     * @param  Class_|Trait_|Enum_  $node
     * @param  string[]  $fileUseImports
     */
    private function buildClassFacts(Node $node, string $file, array $fileUseImports): ClassFacts
    {
        $fqcn = $node->namespacedName?->toString() ?? $node->name?->toString() ?? 'Unknown';
        $extendsFqcn = $node instanceof Class_ && $node->extends instanceof Name
            ? $node->extends->toString()
            : null;

        $methods = [];

        foreach ($node->getMethods() as $method) {
            $methods[] = MethodMetricsVisitor::analyze($method);
        }

        $constructorTypeImports = $this->collectConstructorTypeImports($node);
        $couplingCount = count(array_unique(array_merge($fileUseImports, $constructorTypeImports)));

        $startLine = $node->getStartLine();
        $endLine = $node->getEndLine();
        $classLoc = $startLine > 0 && $endLine >= $startLine ? $endLine - $startLine + 1 : 0;

        return new ClassFacts(
            fqcn: $fqcn,
            file: $file,
            classLoc: $classLoc,
            extendsFqcn: $extendsFqcn,
            couplingCount: $couplingCount,
            methods: $methods,
        );
    }

    /**
     * @param  Class_|Trait_|Enum_  $node
     * @return string[]
     */
    private function collectConstructorTypeImports(Node $node): array
    {
        $constructor = null;

        foreach ($node->getMethods() as $method) {
            if ($method->name->toString() === '__construct') {
                $constructor = $method;
                break;
            }
        }

        if ($constructor === null) {
            return [];
        }

        $types = [];

        foreach ($constructor->params as $param) {
            foreach ($this->extractClassTypeNames($param->type) as $typeName) {
                $types[] = $typeName;
            }
        }

        return $types;
    }

    /**
     * @return string[]
     */
    private function extractClassTypeNames(?Node $type): array
    {
        if ($type === null) {
            return [];
        }

        if ($type instanceof NullableType) {
            return $this->extractClassTypeNames($type->type);
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            $names = [];

            foreach ($type->types as $subType) {
                $names = array_merge($names, $this->extractClassTypeNames($subType));
            }

            return $names;
        }

        if ($type instanceof Name) {
            $name = ltrim($type->toString(), '\\');

            if (in_array(strtolower($name), self::builtinTypeNames(), true)) {
                return [];
            }

            return [$name];
        }

        if ($type instanceof ComplexType) {
            return [];
        }

        return [];
    }

    /**
     * @return string[]
     */
    private function collectUseImports(NodeFinder $finder, array $ast): array
    {
        $useNodes = $finder->findInstanceOf($ast, Use_::class);
        $imports = [];

        foreach ($useNodes as $useNode) {
            if ($useNode->type !== Use_::TYPE_NORMAL) {
                continue;
            }

            foreach ($useNode->uses as $useItem) {
                $imports[] = ltrim($useItem->name->toString(), '\\');
            }
        }

        return array_values(array_unique($imports));
    }

    /**
     * @return string[]
     */
    private static function builtinTypeNames(): array
    {
        return [
            'self', 'static', 'parent', 'mixed', 'void', 'never', 'null',
            'true', 'false', 'int', 'float', 'string', 'bool', 'array',
            'object', 'iterable', 'callable',
        ];
    }
}
