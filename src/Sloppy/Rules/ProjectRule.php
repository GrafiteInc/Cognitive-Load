<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Finding;

/**
 * A rule that needs every scanned file at once, e.g. cross-file duplication.
 */
interface ProjectRule extends Rule
{
    /**
     * @param  FileContext[]  $contexts
     * @return Finding[]
     */
    public function checkProject(array $contexts): array;
}
