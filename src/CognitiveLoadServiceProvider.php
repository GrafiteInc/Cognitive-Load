<?php

namespace Grafite\CognitiveLoad;

use Grafite\CognitiveLoad\Commands\AnalyseCommand;
use Grafite\CognitiveLoad\Commands\BaselineCommand;
use Illuminate\Support\ServiceProvider;

class CognitiveLoadServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/cognitive-load.php' => config_path('cognitive-load.php'),
        ], 'cognitive-load-config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cognitive-load.php', 'cognitive-load');

        $this->app->singleton(CognitiveLoadAnalyzer::class, function ($app) {
            return new CognitiveLoadAnalyzer($app['config']);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                AnalyseCommand::class,
                BaselineCommand::class,
            ]);
        }
    }
}
