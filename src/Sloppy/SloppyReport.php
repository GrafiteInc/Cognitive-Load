<?php

namespace Grafite\CognitiveLoad\Sloppy;

use JsonSerializable;

class SloppyReport implements JsonSerializable
{
    /**
     * @param  string[]  $paths
     * @param  Finding[]  $findings
     * @param  string[]  $warnings
     */
    public function __construct(
        public readonly string $generatedAt,
        public readonly array $paths,
        public readonly int $fileCount,
        public readonly array $findings,
        public readonly array $warnings,
    ) {}

    /**
     * @return array<string, int>
     */
    public function countsBySeverity(): array
    {
        $counts = [Severity::High->value => 0, Severity::Medium->value => 0, Severity::Low->value => 0];

        foreach ($this->findings as $finding) {
            $counts[$finding->severity->value]++;
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function countsByRule(): array
    {
        $counts = [];

        foreach ($this->findings as $finding) {
            $counts[$finding->ruleId] = ($counts[$finding->ruleId] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    public function toArray(): array
    {
        return [
            'generated_at' => $this->generatedAt,
            'paths' => $this->paths,
            'file_count' => $this->fileCount,
            'finding_count' => count($this->findings),
            'by_severity' => $this->countsBySeverity(),
            'by_rule' => $this->countsByRule(),
            'findings' => array_map(fn (Finding $finding) => $finding->toArray(), $this->findings),
            'warnings' => $this->warnings,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
