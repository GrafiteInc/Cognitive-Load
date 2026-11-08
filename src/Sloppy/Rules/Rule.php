<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\Severity;

interface Rule
{
    public function id(): string;

    public function name(): string;

    public function defaultSeverity(): Severity;
}
