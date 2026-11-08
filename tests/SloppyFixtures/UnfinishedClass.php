<?php

namespace Tests\SloppyFixtures;

use RuntimeException;

class UnfinishedClass
{
    public function swallow(): void
    {
        try {
            $this->risky();
        } catch (RuntimeException $e) {
        }
    }

    public function notReady(): int
    {
        throw new RuntimeException('Not implemented yet');
    }

    public function stub(): ?array
    {
        // TODO: build the export
        return null;
    }

    public function nothing(): void {}

    public function debugging(array $rows): array
    {
        dd($rows);

        return $rows;
    }

    public function risky(): void
    {
        throw new RuntimeException('boom');
    }
}
