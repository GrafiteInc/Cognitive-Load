<?php

namespace Tests\Unit\Sloppy;

use Grafite\CognitiveLoad\Sloppy\Finding;
use Grafite\CognitiveLoad\Sloppy\SloppyAnalyzer;
use Tests\TestCase;

class FingerprintTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/cognitive-load-fingerprint-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*.php'));
        rmdir($this->directory);

        parent::tearDown();
    }

    /**
     * @return array<string, string> rule => fingerprint
     */
    private function fingerprints(string $code): array
    {
        file_put_contents($this->directory.'/Service.php', $code);

        $report = $this->app->make(SloppyAnalyzer::class)->analyze([$this->directory]);

        return array_column(array_map(fn (Finding $f) => ['rule' => $f->ruleId, 'fp' => $f->fingerprint], $report->findings), 'fp', 'rule');
    }

    public function test_fingerprints_survive_line_shifts_but_not_code_changes(): void
    {
        $original = $this->fingerprints(<<<'PHP'
            <?php
            namespace App;
            class Service {
                public function run() { try { work(); } catch (\Exception $e) {} }
            }
            PHP);

        $shifted = $this->fingerprints(<<<'PHP'
            <?php
            namespace App;

            use Illuminate\Support\Str;


            class Service {
                public function other() { return Str::of('x'); }

                public function run() {
                    try { work(); } catch (\Exception $e) {}
                }
            }
            PHP);

        $changed = $this->fingerprints(<<<'PHP'
            <?php
            namespace App;
            class Service {
                public function run() { try { work(); } catch (\RuntimeException $e) {} }
            }
            PHP);

        $this->assertNotEmpty($original['SL101']);
        $this->assertSame($original['SL101'], $shifted['SL101']);
        $this->assertNotSame($original['SL101'], $changed['SL101']);
    }

    public function test_identical_findings_in_one_method_get_distinct_fingerprints(): void
    {
        file_put_contents($this->directory.'/Service.php', '<?php class Service { public function run($x) { dd($x); dd($x); } }');

        $report = $this->app->make(SloppyAnalyzer::class)->analyze([$this->directory]);
        $fingerprints = array_map(fn (Finding $f) => $f->fingerprint, $report->findings);

        $this->assertCount(2, $fingerprints);
        $this->assertCount(2, array_unique($fingerprints));
    }
}
