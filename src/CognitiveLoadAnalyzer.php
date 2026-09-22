<?php

namespace Grafite\CognitiveLoad;

use Grafite\CognitiveLoad\Ast\ClassAstAnalyzer;
use Grafite\CognitiveLoad\Ast\ClassFacts;
use Grafite\CognitiveLoad\Results\ClassResult;
use Grafite\CognitiveLoad\Results\ProjectResult;
use Grafite\CognitiveLoad\Scanning\FileScanner;
use Grafite\CognitiveLoad\Scoring\ArchitectureCategoryScorer;
use Grafite\CognitiveLoad\Scoring\ComplexityCategoryScorer;
use Grafite\CognitiveLoad\Scoring\InheritanceResolver;
use Grafite\CognitiveLoad\Scoring\MethodScoreCalculator;
use Grafite\CognitiveLoad\Scoring\OverallGradeCalculator;
use Grafite\CognitiveLoad\Scoring\ScoreBand;
use Grafite\CognitiveLoad\Support\PatternMatcher;
use Illuminate\Config\Repository;
use Illuminate\Support\Carbon;

class CognitiveLoadAnalyzer
{
    public function __construct(
        private readonly Repository $config,
        private readonly FileScanner $fileScanner = new FileScanner,
        private readonly ClassAstAnalyzer $classAstAnalyzer = new ClassAstAnalyzer,
        private readonly MethodScoreCalculator $methodScoreCalculator = new MethodScoreCalculator,
        private readonly ComplexityCategoryScorer $complexityCategoryScorer = new ComplexityCategoryScorer,
        private readonly ArchitectureCategoryScorer $architectureCategoryScorer = new ArchitectureCategoryScorer,
        private readonly OverallGradeCalculator $overallGradeCalculator = new OverallGradeCalculator,
        private readonly InheritanceResolver $inheritanceResolver = new InheritanceResolver,
    ) {}

    /**
     * @param  string[]|null  $paths
     */
    public function analyze(?array $paths = null): ProjectResult
    {
        $paths = $paths ?? (array) $this->config->get('cognitive-load.paths', []);

        $files = $this->fileScanner->scan(
            $paths,
            (array) $this->config->get('cognitive-load.exclude', []),
            (array) $this->config->get('cognitive-load.exclude_files', []),
        );

        $warnings = [];
        /** @var ClassFacts[] $allFacts */
        $allFacts = [];

        foreach ($files as $file) {
            $analysis = $this->classAstAnalyzer->analyzeFile($file);

            if ($analysis['warning'] !== null) {
                $warnings[] = $analysis['warning'];
            }

            foreach ($analysis['classes'] as $facts) {
                $allFacts[] = $facts;
            }
        }

        $excludeClassPatterns = (array) $this->config->get('cognitive-load.exclude_classes', []);
        $allFacts = array_values(array_filter(
            $allFacts,
            fn (ClassFacts $facts) => ! PatternMatcher::matchesAny($facts->fqcn, $excludeClassPatterns)
        ));

        $inheritanceDepths = $this->inheritanceResolver->resolve($allFacts);

        $metricsConfig = (array) $this->config->get('cognitive-load.metrics', []);
        $architectureMetricsConfig = (array) $this->config->get('cognitive-load.architecture_metrics', []);
        $categoryScaling = (array) $this->config->get('cognitive-load.category_scaling', []);
        $categoryWeights = (array) $this->config->get('cognitive-load.category_weights', []);
        $bandsConfig = (array) $this->config->get('cognitive-load.bands', []);

        $complexityDecay = (float) ($categoryScaling['complexity_decay'] ?? 40.0);
        $architectureDecay = (float) ($categoryScaling['architecture_decay'] ?? 30.0);
        $thresholds = (array) ($bandsConfig['thresholds'] ?? [40, 60, 80]);
        $riskLabels = (array) ($bandsConfig['risk_labels'] ?? ['VeryHigh', 'High', 'Medium', 'Low']);
        $gradeLabels = (array) ($bandsConfig['grade_labels'] ?? ['Critical', 'Poor', 'Fair', 'Good']);

        $classResults = [];

        foreach ($allFacts as $facts) {
            $methodResults = array_map(
                fn ($raw) => $this->methodScoreCalculator->calculate($raw, $metricsConfig),
                $facts->methods,
            );

            $complexityScore = $this->complexityCategoryScorer->score($methodResults, $complexityDecay);
            $inheritanceDepth = $inheritanceDepths[$facts->fqcn] ?? 0;
            $architectureScore = $this->architectureCategoryScorer->score(
                $facts,
                $inheritanceDepth,
                $architectureMetricsConfig,
                $architectureDecay,
            );
            $overallScore = $this->overallGradeCalculator->combine($complexityScore, $architectureScore, $categoryWeights);

            $classResults[] = new ClassResult(
                fqcn: $facts->fqcn,
                file: $facts->file,
                loc: $facts->classLoc,
                methodCount: count($facts->methods),
                couplingCount: $facts->couplingCount,
                inheritanceDepth: $inheritanceDepth,
                complexityCategoryScore: $complexityScore,
                architectureCategoryScore: $architectureScore,
                overallScore: $overallScore,
                band: ScoreBand::label($overallScore, $thresholds, $gradeLabels),
                riskBand: ScoreBand::label($overallScore, $thresholds, $riskLabels),
                methods: $methodResults,
            );
        }

        usort($classResults, fn (ClassResult $a, ClassResult $b) => $a->overallScore <=> $b->overallScore);

        $classCount = count($classResults);
        $projectComplexityScore = $classCount > 0
            ? array_sum(array_map(fn (ClassResult $c) => $c->complexityCategoryScore, $classResults)) / $classCount
            : 100.0;
        $projectArchitectureScore = $classCount > 0
            ? array_sum(array_map(fn (ClassResult $c) => $c->architectureCategoryScore, $classResults)) / $classCount
            : 100.0;
        $projectOverallScore = $this->overallGradeCalculator->combine($projectComplexityScore, $projectArchitectureScore, $categoryWeights);

        return new ProjectResult(
            generatedAt: Carbon::now()->toIso8601String(),
            paths: $paths,
            complexityCategoryScore: $projectComplexityScore,
            architectureCategoryScore: $projectArchitectureScore,
            overallScore: $projectOverallScore,
            band: ScoreBand::label($projectOverallScore, $thresholds, $gradeLabels),
            classes: $classResults,
            warnings: $warnings,
        );
    }
}
