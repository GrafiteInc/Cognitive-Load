<?php

namespace Grafite\CognitiveLoad\Ast;

class ClassFacts
{
    /**
     * @param  MethodRawMetrics[]  $methods
     */
    public function __construct(
        public readonly string $fqcn,
        public readonly string $file,
        public readonly int $classLoc,
        public readonly ?string $extendsFqcn,
        public readonly int $couplingCount,
        public readonly array $methods,
    ) {}
}
