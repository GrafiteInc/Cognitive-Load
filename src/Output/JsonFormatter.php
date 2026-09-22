<?php

namespace Grafite\CognitiveLoad\Output;

use Grafite\CognitiveLoad\Baseline\ComparisonResult;
use Grafite\CognitiveLoad\Results\ProjectResult;

class JsonFormatter
{
    public function render(ProjectResult $result, bool $detailed, ?ComparisonResult $comparison = null): string
    {
        $payload = $result->toArray($detailed);

        if ($comparison !== null) {
            $payload['comparison'] = $comparison->toArray();
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }
}
