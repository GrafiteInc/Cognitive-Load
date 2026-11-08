<?php

namespace Tests\SloppyFixtures;

use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

class DeadCodeClass
{
    private array $cache = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly int $limit,
        string $unused,
    ) {}

    public function isOverLimit(int $value): bool
    {
        if ($value > $this->limit) {
            return true;
        }

        return false;
    }

    public function isEven(int $value): bool
    {
        return $value % 2 === 0 ? true : false;
    }

    public function alwaysRuns(): string
    {
        if (true) {
            return 'yes';
        }

        // $legacy = $this->legacy();
        // return $legacy;
        return 'no';
    }

    private function helper(): void {}
}
