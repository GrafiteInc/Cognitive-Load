<?php

namespace Grafite\CognitiveLoad\Sloppy\Baseline;

use Grafite\CognitiveLoad\Sloppy\SloppyReport;
use Illuminate\Filesystem\Filesystem;

class SloppyBaselineRepository
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

    public function save(string $path, SloppyReport $report): void
    {
        $findings = [];

        foreach ($report->findings as $finding) {
            $findings[$finding->fingerprint] = [
                'rule' => $finding->ruleId,
                'severity' => $finding->severity->value,
                'file' => $finding->file,
                'symbol' => $finding->symbol,
            ];
        }

        ksort($findings);

        $payload = [
            'version' => 1,
            'generated_at' => $report->generatedAt,
            'finding_count' => count($findings),
            'findings' => $findings,
        ];

        $directory = dirname($path);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $this->files->put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
