<?php

namespace Grafite\CognitiveLoad\Baseline;

use Grafite\CognitiveLoad\Results\ProjectResult;
use Illuminate\Filesystem\Filesystem;

class BaselineRepository
{
    public function __construct(private readonly Filesystem $files = new Filesystem) {}

    public function load(string $path): ?array
    {
        if (! $this->files->exists($path)) {
            return null;
        }

        $decoded = json_decode($this->files->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function save(string $path, ProjectResult $result): void
    {
        $classes = [];

        foreach ($result->classes as $class) {
            $classes[$class->fqcn] = [
                'overall_score' => $class->overallScore,
                'complexity_category_score' => $class->complexityCategoryScore,
                'architecture_category_score' => $class->architectureCategoryScore,
                'band' => $class->band,
                'risk_band' => $class->riskBand,
            ];
        }

        $payload = [
            'version' => 1,
            'generated_at' => $result->generatedAt,
            'overall_score' => $result->overallScore,
            'complexity_category_score' => $result->complexityCategoryScore,
            'architecture_category_score' => $result->architectureCategoryScore,
            'band' => $result->band,
            'classes' => $classes,
        ];

        $directory = dirname($path);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $this->files->put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
