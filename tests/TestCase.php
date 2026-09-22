<?php

namespace Tests;

use Grafite\CognitiveLoad\CognitiveLoadServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            CognitiveLoadServiceProvider::class,
        ];
    }
}
