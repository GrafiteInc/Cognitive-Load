<?php

namespace Tests\Unit\Commands;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GateRegressionTest extends TestCase
{
    protected string $baselinePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baselinePath = sys_get_temp_dir().'/cognitive-load-gate-'.bin2hex(random_bytes(4)).'.json';

        config([
            'cognitive-load.paths' => [__DIR__.'/../../Fixtures'],
            'cognitive-load.baseline.path' => $this->baselinePath,
        ]);

        Artisan::call('cognitive-load:baseline', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->baselinePath)) {
            unlink($this->baselinePath);
        }

        parent::tearDown();
    }

    public function test_gate_passes_when_current_matches_the_baseline_exactly(): void
    {
        $exitCode = Artisan::call('cognitive-load:analyse', [
            '--compare-baseline' => true,
            '--fail-on-regression' => true,
            '--format' => 'json',
        ]);

        $this->assertSame(0, $exitCode);
    }

    public function test_gate_fails_when_the_baseline_is_far_better_than_the_current_run(): void
    {
        $baseline = json_decode(file_get_contents($this->baselinePath), true, 512, JSON_THROW_ON_ERROR);

        $baseline['overall_score'] = 100.0;

        foreach ($baseline['classes'] as $fqcn => $class) {
            $baseline['classes'][$fqcn]['overall_score'] = 100.0;
            $baseline['classes'][$fqcn]['risk_band'] = 'Low';
        }

        file_put_contents($this->baselinePath, json_encode($baseline));

        $exitCode = Artisan::call('cognitive-load:analyse', [
            '--compare-baseline' => true,
            '--fail-on-regression' => true,
            '--format' => 'json',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"passed": false', Artisan::output());
    }
}
