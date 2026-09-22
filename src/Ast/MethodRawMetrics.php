<?php

namespace Grafite\CognitiveLoad\Ast;

class MethodRawMetrics
{
    public function __construct(
        public readonly string $name,
        public readonly int $lineCount,
        public readonly int $argumentCount,
        public readonly int $returnCount,
        public readonly int $variableCount,
        public readonly int $propertyAccessCount,
        public readonly int $ifCount,
        public readonly int $ifNestingDepth,
        public readonly int $elseCount,
        public readonly int $cyclomaticComplexity,
    ) {}

    public function toArray(): array
    {
        return [
            'line_count' => $this->lineCount,
            'argument_count' => $this->argumentCount,
            'return_count' => $this->returnCount,
            'variable_count' => $this->variableCount,
            'property_access_count' => $this->propertyAccessCount,
            'if_count' => $this->ifCount,
            'if_nesting_depth' => $this->ifNestingDepth,
            'else_count' => $this->elseCount,
            'cyclomatic_complexity' => $this->cyclomaticComplexity,
        ];
    }
}
