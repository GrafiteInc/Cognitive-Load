<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

class EnvOutsideConfig extends AbstractRule implements FileRule
{
    public function id(): string
    {
        return 'SL304';
    }

    public function name(): string
    {
        return 'env() outside config';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::High;
    }

    public function check(FileContext $context): array
    {
        if (preg_match('#(^|/)config/#', str_replace('\\', '/', $context->relativeFile)) === 1) {
            return [];
        }

        $findings = [];

        foreach ($context->findInstanceOf(FuncCall::class) as $call) {
            if (! $call->name instanceof Name || strtolower(ltrim($call->name->toString(), '\\')) !== 'env') {
                continue;
            }

            $findings[] = $this->finding(
                $context,
                $call,
                'env() is called outside a config file.',
                'Read the value in a config/*.php file and use config() here. env() returns null once the config is cached.',
            );
        }

        return $findings;
    }
}
