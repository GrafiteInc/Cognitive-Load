<?php

namespace Tests\Fixtures;

class CleanClass
{
    public function __construct(private readonly int $value) {}

    public function double(): int
    {
        return $this->value * 2;
    }

    public function isPositive(): bool
    {
        return $this->value > 0;
    }
}
