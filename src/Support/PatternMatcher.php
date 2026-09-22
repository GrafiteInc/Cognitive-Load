<?php

namespace Grafite\CognitiveLoad\Support;

class PatternMatcher
{
    public static function matchesAny(string $subject, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern !== '' && preg_match($pattern, $subject) === 1) {
                return true;
            }
        }

        return false;
    }
}
