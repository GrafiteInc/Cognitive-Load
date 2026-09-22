<?php

namespace Grafite\CognitiveLoad\Results;

use JsonSerializable;

class ProjectResult implements JsonSerializable
{
    /**
     * @param  string[]  $paths
     * @param  ClassResult[]  $classes
     * @param  string[]  $warnings
     */
    public function __construct(
        public readonly string $generatedAt,
        public readonly array $paths,
        public readonly float $complexityCategoryScore,
        public readonly float $architectureCategoryScore,
        public readonly float $overallScore,
        public readonly string $band,
        public readonly array $classes,
        public readonly array $warnings,
    ) {}

    public function toArray(bool $detailed = true): array
    {
        return [
            'generated_at' => $this->generatedAt,
            'paths' => $this->paths,
            'complexity_category_score' => $this->complexityCategoryScore,
            'architecture_category_score' => $this->architectureCategoryScore,
            'overall_score' => $this->overallScore,
            'band' => $this->band,
            'classes' => array_map(fn (ClassResult $c) => $c->toArray($detailed), $this->classes),
            'warnings' => $this->warnings,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
