<?php

namespace Tests\Unit\Sloppy\Rules;

use Grafite\CognitiveLoad\Ast\PhpFileParser;
use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Finding;
use Grafite\CognitiveLoad\Sloppy\Rules\FileRule;

trait ChecksRules
{
    protected function context(string $code, string $relativeFile = 'app/Example.php'): FileContext
    {
        return new FileContext('/project/'.$relativeFile, $relativeFile, $code, (new PhpFileParser)->parseCode($code));
    }

    /**
     * @return Finding[]
     */
    protected function check(FileRule $rule, string $code, string $relativeFile = 'app/Example.php'): array
    {
        return $rule->check($this->context($code, $relativeFile));
    }

    /**
     * @param  Finding[]  $findings
     * @return string[]
     */
    protected function symbols(array $findings): array
    {
        return array_map(fn (Finding $finding): string => $finding->symbol, $findings);
    }
}
