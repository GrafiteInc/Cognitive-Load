<?php

namespace Grafite\CognitiveLoad\Sloppy;

use JsonSerializable;

class Finding implements JsonSerializable
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $ruleName,
        public readonly Severity $severity,
        public readonly string $confidence,
        public readonly string $file,
        public readonly int $line,
        public readonly string $symbol,
        public readonly string $message,
        public readonly string $advice,
        public readonly string $snippetHash,
        public readonly string $fingerprint = '',
    ) {}

    public function withFingerprint(string $fingerprint): self
    {
        return new self(
            ruleId: $this->ruleId,
            ruleName: $this->ruleName,
            severity: $this->severity,
            confidence: $this->confidence,
            file: $this->file,
            line: $this->line,
            symbol: $this->symbol,
            message: $this->message,
            advice: $this->advice,
            snippetHash: $this->snippetHash,
            fingerprint: $fingerprint,
        );
    }

    public function className(): string
    {
        return explode('::', $this->symbol, 2)[0];
    }

    public function toArray(): array
    {
        return [
            'rule' => $this->ruleId,
            'name' => $this->ruleName,
            'severity' => $this->severity->value,
            'confidence' => $this->confidence,
            'file' => $this->file,
            'line' => $this->line,
            'symbol' => $this->symbol,
            'message' => $this->message,
            'advice' => $this->advice,
            'fingerprint' => $this->fingerprint,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
