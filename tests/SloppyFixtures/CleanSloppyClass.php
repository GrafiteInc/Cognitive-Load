<?php

namespace Tests\SloppyFixtures;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Exercises the patterns every rule must leave alone.
 */
class CleanSloppyClass
{
    public function __construct(private readonly int $limit) {}

    public function isOverLimit(int $value): bool
    {
        return $value > $this->limit;
    }

    public function attempt(callable $callback): bool
    {
        try {
            $callback();
        } catch (RuntimeException $e) {
            Log::warning($e->getMessage());

            return false;
        }

        return true;
    }

    public function activeCount(): int
    {
        return User::where('active', true)->count();
    }

    public function names(array $ids): array
    {
        $users = User::with('team')->whereIn('id', $ids)->get();
        $names = [];

        foreach ($users as $user) {
            $names[] = $user->team->name;
        }

        return $names;
    }

    public function poll(): void
    {
        while (true) {
            if ($this->tick()) {
                break;
            }
        }
    }

    private function tick(): bool
    {
        // Returns once the queue has drained.
        return $this->limit > 0;
    }
}
