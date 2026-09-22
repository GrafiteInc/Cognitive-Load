<?php

namespace Grafite\CognitiveLoad\Results;

use JsonSerializable;

class ClassResult implements JsonSerializable
{
    /**
     * @param  MethodResult[]  $methods
     */
    public function __construct(
        public readonly string $fqcn,
        public readonly string $file,
        public readonly int $loc,
        public readonly int $methodCount,
        public readonly int $couplingCount,
        public readonly int $inheritanceDepth,
        public readonly float $complexityCategoryScore,
        public readonly float $architectureCategoryScore,
        public readonly float $overallScore,
        public readonly string $band,
        public readonly string $riskBand,
        public readonly array $methods,
    ) {}

    public function toArray(bool $detailed = true): array
    {
        return [
            'fqcn' => $this->fqcn,
            'file' => $this->file,
            'loc' => $this->loc,
            'method_count' => $this->methodCount,
            'coupling_count' => $this->couplingCount,
            'inheritance_depth' => $this->inheritanceDepth,
            'complexity_category_score' => $this->complexityCategoryScore,
            'architecture_category_score' => $this->architectureCategoryScore,
            'overall_score' => $this->overallScore,
            'band' => $this->band,
            'risk_band' => $this->riskBand,
            ...($detailed ? ['methods' => array_map(fn (MethodResult $m) => $m->toArray(), $this->methods)] : []),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
