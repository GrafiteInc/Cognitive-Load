<?php

namespace Grafite\CognitiveLoad\Scoring;

use Grafite\CognitiveLoad\Ast\ClassFacts;

/**
 * Only sees classes present in the scanned file set — depth cannot recurse
 * past a parent that lives outside the scan (e.g. Illuminate\Database\Eloquent\Model),
 * it simply stops counting at that hop. This is a documented v1 limitation,
 * not full reflection-based resolution.
 */
class InheritanceResolver
{
    private const MAX_DEPTH = 50;

    /**
     * @param  ClassFacts[]  $classes
     * @return array<string, int> fqcn => local inheritance depth
     */
    public function resolve(array $classes): array
    {
        $parentMap = [];

        foreach ($classes as $facts) {
            $parentMap[$facts->fqcn] = $facts->extendsFqcn;
        }

        $depths = [];

        foreach (array_keys($parentMap) as $fqcn) {
            $depths[$fqcn] = $this->depthFor($fqcn, $parentMap);
        }

        return $depths;
    }

    /**
     * @param  array<string, ?string>  $parentMap
     */
    private function depthFor(string $fqcn, array $parentMap): int
    {
        $depth = 0;
        $visited = [];
        $current = $parentMap[$fqcn] ?? null;

        while ($current !== null) {
            if (isset($visited[$current]) || $depth >= self::MAX_DEPTH) {
                break;
            }

            $visited[$current] = true;
            $depth++;

            if (! array_key_exists($current, $parentMap)) {
                break;
            }

            $current = $parentMap[$current];
        }

        return $depth;
    }
}
