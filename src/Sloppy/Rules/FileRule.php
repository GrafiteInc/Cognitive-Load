<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Finding;

interface FileRule extends Rule
{
    /**
     * @return Finding[]
     */
    public function check(FileContext $context): array;
}
