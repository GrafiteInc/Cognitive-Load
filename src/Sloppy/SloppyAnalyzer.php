<?php

namespace Grafite\CognitiveLoad\Sloppy;

use Grafite\CognitiveLoad\Ast\PhpFileParser;
use Grafite\CognitiveLoad\Scanning\FileScanner;
use Grafite\CognitiveLoad\Sloppy\Rules\FileRule;
use Grafite\CognitiveLoad\Sloppy\Rules\ProjectRule;
use Grafite\CognitiveLoad\Support\PatternMatcher;
use Illuminate\Config\Repository;
use Illuminate\Support\Carbon;

class SloppyAnalyzer
{
    public function __construct(
        private readonly Repository $config,
        private readonly FileScanner $fileScanner = new FileScanner,
        private readonly PhpFileParser $parser = new PhpFileParser,
        private readonly RuleRegistry $ruleRegistry = new RuleRegistry,
    ) {}

    /**
     * @param  string[]|null  $paths
     */
    public function analyze(?array $paths = null, ?Severity $minSeverity = null): SloppyReport
    {
        $paths = $paths ?? (array) $this->config->get('cognitive-load.paths', []);
        $minSeverity ??= Severity::tryFrom((string) $this->config->get('cognitive-load.sloppy.min_severity', 'low')) ?? Severity::Low;

        $files = $this->fileScanner->scan(
            $paths,
            (array) $this->config->get('cognitive-load.exclude', []),
            (array) $this->config->get('cognitive-load.exclude_files', []),
        );

        $rules = $this->ruleRegistry->enabled(
            (array) $this->config->get('cognitive-load.sloppy.rules', []),
            (array) $this->config->get('cognitive-load.sloppy.custom_rules', []),
        );

        $warnings = [];
        $contexts = [];
        $findings = [];

        foreach ($files as $file) {
            $parsed = $this->parser->parse($file);

            if ($parsed['warning'] !== null) {
                $warnings[] = $parsed['warning'];

                continue;
            }

            if ($parsed['ast'] === null || $parsed['ast'] === []) {
                continue;
            }

            $context = new FileContext($file, $this->relativePath($file, $paths), (string) $parsed['code'], $parsed['ast']);
            $contexts[] = $context;

            foreach ($rules as $rule) {
                if ($rule instanceof FileRule) {
                    array_push($findings, ...$rule->check($context));
                }
            }
        }

        foreach ($rules as $rule) {
            if ($rule instanceof ProjectRule) {
                array_push($findings, ...$rule->checkProject($contexts));
            }
        }

        $excludeClassPatterns = (array) $this->config->get('cognitive-load.exclude_classes', []);

        $findings = array_values(array_filter(
            $findings,
            fn (Finding $finding): bool => $finding->severity->atLeast($minSeverity)
                && ! PatternMatcher::matchesAny($finding->className(), $excludeClassPatterns),
        ));

        return new SloppyReport(
            generatedAt: Carbon::now()->toIso8601String(),
            paths: $paths,
            fileCount: count($files),
            findings: $this->fingerprint($findings),
            warnings: $warnings,
        );
    }

    /**
     * Sorts findings (most severe first, then by location) and assigns each a
     * stable fingerprint. Identical findings within one symbol are told apart
     * by their order of appearance.
     *
     * @param  Finding[]  $findings
     * @return Finding[]
     */
    private function fingerprint(array $findings): array
    {
        usort($findings, fn (Finding $a, Finding $b): int => [$b->severity->rank(), $a->file, $a->line, $a->ruleId]
            <=> [$a->severity->rank(), $b->file, $b->line, $b->ruleId]);

        $occurrences = [];

        return array_map(function (Finding $finding) use (&$occurrences): Finding {
            $key = implode('|', [$finding->ruleId, $finding->file, $finding->symbol, $finding->snippetHash]);
            $occurrence = $occurrences[$key] = ($occurrences[$key] ?? -1) + 1;

            return $finding->withFingerprint(
                Fingerprint::make($finding->ruleId, $finding->file, $finding->symbol, $finding->snippetHash, $occurrence)
            );
        }, $findings);
    }

    /**
     * Paths are made relative to the app's base path (or failing that, the
     * scanned path) so fingerprints match across machines and CI runners.
     *
     * @param  string[]  $paths
     */
    private function relativePath(string $file, array $paths): string
    {
        $roots = [function_exists('base_path') ? base_path() : null];

        foreach ($paths as $path) {
            $roots[] = is_file($path) ? dirname($path) : $path;
        }

        foreach ($roots as $root) {
            $real = $root !== null ? realpath($root) : false;

            if ($real !== false && str_starts_with($file, $real.DIRECTORY_SEPARATOR)) {
                return str_replace('\\', '/', substr($file, strlen($real) + 1));
            }
        }

        return str_replace('\\', '/', $file);
    }
}
