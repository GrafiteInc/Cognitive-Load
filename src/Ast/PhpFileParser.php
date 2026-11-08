<?php

namespace Grafite\CognitiveLoad\Ast;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Parses PHP source into a name-resolved AST. Original (pre-resolution)
 * names are kept on the `originalName` attribute so callers can still tell
 * how a name was written, e.g. to detect unused imports.
 */
class PhpFileParser
{
    /**
     * @return array{ast: Node[]|null, code: string|null, warning: string|null}
     */
    public function parse(string $file): array
    {
        $code = file_get_contents($file);

        if ($code === false) {
            return ['ast' => null, 'code' => null, 'warning' => "Unable to read file: {$file}"];
        }

        try {
            $ast = $this->parseCode($code);
        } catch (Error $e) {
            return ['ast' => null, 'code' => $code, 'warning' => "Parse error in {$file}: {$e->getMessage()}"];
        }

        return ['ast' => $ast, 'code' => $code, 'warning' => null];
    }

    /**
     * @return Node[]
     *
     * @throws Error
     */
    public function parseCode(string $code): array
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);

        if ($ast === null || $ast === []) {
            return [];
        }

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver(null, ['preserveOriginalNames' => true]));

        return $traverser->traverse($ast);
    }
}
