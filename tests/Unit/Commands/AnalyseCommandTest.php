<?php

namespace Tests\Unit\Commands;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AnalyseCommandTest extends TestCase
{
    public function test_it_analyses_the_configured_path_and_outputs_json(): void
    {
        config(['cognitive-load.paths' => [__DIR__.'/../../Fixtures']]);

        Artisan::call('cognitive-load:analyse', ['--format' => 'json']);
        $output = Artisan::output();

        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('overall_score', $decoded);
        $this->assertArrayHasKey('classes', $decoded);
        $this->assertNotEmpty($decoded['classes']);

        $fqcns = array_column($decoded['classes'], 'fqcn');
        $this->assertContains('Tests\\Fixtures\\CleanClass', $fqcns);
        $this->assertContains('Tests\\Fixtures\\HighComplexityMethod', $fqcns);
    }

    public function test_clean_class_scores_higher_than_the_high_complexity_fixture(): void
    {
        config(['cognitive-load.paths' => [__DIR__.'/../../Fixtures']]);

        Artisan::call('cognitive-load:analyse', ['--format' => 'json']);
        $decoded = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $scores = array_column($decoded['classes'], 'complexity_category_score', 'fqcn');

        $this->assertGreaterThan(
            $scores['Tests\\Fixtures\\HighComplexityMethod'],
            $scores['Tests\\Fixtures\\CleanClass'],
        );
    }

    public function test_it_accepts_an_explicit_path_argument(): void
    {
        $exitCode = Artisan::call('cognitive-load:analyse', [
            'path' => __DIR__.'/../../Fixtures',
            '--format' => 'console',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Overall:', Artisan::output());
    }
}
