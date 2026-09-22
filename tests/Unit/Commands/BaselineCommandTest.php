<?php

namespace Tests\Unit\Commands;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BaselineCommandTest extends TestCase
{
    protected string $baselinePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baselinePath = sys_get_temp_dir().'/cognitive-load-baseline-'.bin2hex(random_bytes(4)).'.json';

        config([
            'cognitive-load.paths' => [__DIR__.'/../../Fixtures'],
            'cognitive-load.baseline.path' => $this->baselinePath,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->baselinePath)) {
            unlink($this->baselinePath);
        }

        parent::tearDown();
    }

    public function test_it_writes_a_baseline_file_with_the_expected_shape(): void
    {
        $exitCode = Artisan::call('cognitive-load:baseline', ['--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->baselinePath);

        $baseline = json_decode(file_get_contents($this->baselinePath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $baseline['version']);
        $this->assertArrayHasKey('overall_score', $baseline);
        $this->assertArrayHasKey('classes', $baseline);
        $this->assertArrayHasKey('Tests\\Fixtures\\CleanClass', $baseline['classes']);
        $this->assertArrayHasKey('risk_band', $baseline['classes']['Tests\\Fixtures\\CleanClass']);
    }
}
