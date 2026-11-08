<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Finding;
use Grafite\CognitiveLoad\Sloppy\Fingerprint;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;

abstract class AbstractRule implements Rule
{
    /**
     * @param  array<string, mixed>  $config  This rule's entry from config(cognitive-load.sloppy.rules)
     */
    public function __construct(protected readonly array $config = []) {}

    protected function option(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * A configured `severity` always wins over both the default and any
     * severity the rule escalates to on its own.
     */
    protected function finding(
        FileContext $context,
        Node $node,
        string $message,
        string $advice,
        string $confidence = 'high',
        ?Severity $severity = null,
        Node|string|null $snippet = null,
        ?string $symbol = null,
        ?int $line = null,
    ): Finding {
        $configured = isset($this->config['severity']) ? Severity::tryFrom((string) $this->config['severity']) : null;

        return new Finding(
            ruleId: $this->id(),
            ruleName: $this->name(),
            severity: $configured ?? $severity ?? $this->defaultSeverity(),
            confidence: $confidence,
            file: $context->relativeFile,
            line: $line ?? $node->getStartLine(),
            symbol: $symbol ?? $context->symbolFor($node),
            message: $message,
            advice: $advice,
            snippetHash: Fingerprint::snippetHash($snippet ?? $node),
        );
    }

    protected static function isBoolLiteral(Node $node, ?bool $value = null): bool
    {
        if (! $node instanceof ConstFetch) {
            return false;
        }

        $name = $node->name->toLowerString();

        return $value === null
            ? in_array($name, ['true', 'false'], true)
            : $name === ($value ? 'true' : 'false');
    }
}
