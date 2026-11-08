<?php

namespace Tests\Unit\Commands;

use Grafite\CognitiveLoad\Sloppy\RuleRegistry;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SloppyCommandTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../SloppyFixtures';

    protected string $baselinePath;

    protected string $scratchFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baselinePath = sys_get_temp_dir().'/cognitive-load-sloppy-'.bin2hex(random_bytes(4)).'.json';
        $this->scratchFile = self::FIXTURES.'/Scratch'.bin2hex(random_bytes(4)).'.php';

        config([
            'cognitive-load.paths' => [self::FIXTURES],
            'cognitive-load.sloppy.baseline.path' => $this->baselinePath,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->baselinePath, $this->scratchFile] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    private function sloppyReport(array $options = []): array
    {
        Artisan::call('cognitive-load:sloppy', ['--format' => 'json', ...$options]);

        return json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_every_built_in_rule_fires_on_the_fixtures_and_the_clean_class_has_none(): void
    {
        $report = $this->sloppyReport();

        $this->assertSame(array_keys(RuleRegistry::RULES), array_keys($report['by_rule']));

        $files = array_column($report['findings'], 'file');
        $this->assertNotContains('CleanSloppyClass.php', $files);
        $this->assertNotContains('config/services.php', $files);
    }

    public function test_min_severity_filters_findings(): void
    {
        $report = $this->sloppyReport(['--min-severity' => 'high']);

        $this->assertGreaterThan(0, $report['finding_count']);
        $this->assertSame(['high'], array_values(array_unique(array_column($report['findings'], 'severity'))));
    }

    public function test_invalid_min_severity_fails(): void
    {
        $this->assertSame(1, Artisan::call('cognitive-load:sloppy', ['--min-severity' => 'urgent']));
    }

    public function test_disabled_rules_are_skipped(): void
    {
        config(['cognitive-load.sloppy.rules.SL104' => ['enabled' => false]]);

        $this->assertArrayNotHasKey('SL104', $this->sloppyReport()['by_rule']);
    }

    public function test_console_output_lists_findings_with_advice(): void
    {
        $exitCode = Artisan::call('cognitive-load:sloppy', ['path' => self::FIXTURES.'/UnfinishedClass.php']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Sloppiness:', $output);
        $this->assertStringContainsString('SL101', $output);
        $this->assertStringContainsString('→', $output);
    }

    public function test_baseline_round_trip_passes_and_a_new_finding_fails_the_gate(): void
    {
        Artisan::call('cognitive-load:sloppy-baseline', ['--force' => true]);

        $this->assertFileExists($this->baselinePath);
        $this->assertSame(0, Artisan::call('cognitive-load:sloppy', ['--fail-on-new' => true, '--format' => 'json']));

        file_put_contents($this->scratchFile, '<?php class Scratch { public function run() { try { go(); } catch (\Exception $e) {} } }');

        $exitCode = Artisan::call('cognitive-load:sloppy', ['--fail-on-new' => true, '--format' => 'json']);
        $comparison = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR)['comparison'];

        $this->assertSame(1, $exitCode);
        $this->assertFalse($comparison['passed']);
        $this->assertSame(['SL101'], array_column($comparison['new'], 'rule'));
    }

    public function test_missing_baseline_treats_every_finding_as_new(): void
    {
        $this->assertSame(1, Artisan::call('cognitive-load:sloppy', ['--fail-on-new' => true]));
        $this->assertStringContainsString('treating every finding as new', Artisan::output());

        config(['cognitive-load.sloppy.gate.fail_if_baseline_missing' => true]);

        $this->assertSame(1, Artisan::call('cognitive-load:sloppy', ['--fail-on-new' => true]));
        $this->assertStringContainsString('No sloppiness baseline found', Artisan::output());
    }

    public function test_output_option_writes_the_json_report(): void
    {
        $outputPath = $this->baselinePath.'.report.json';

        Artisan::call('cognitive-load:sloppy', ['--output' => $outputPath]);

        $this->assertFileExists($outputPath);
        $this->assertArrayHasKey('findings', json_decode(file_get_contents($outputPath), true));

        unlink($outputPath);
    }
}
