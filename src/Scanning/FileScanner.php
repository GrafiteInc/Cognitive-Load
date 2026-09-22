<?php

namespace Grafite\CognitiveLoad\Scanning;

use Grafite\CognitiveLoad\Support\PatternMatcher;
use Symfony\Component\Finder\Finder;

class FileScanner
{
    /**
     * @param  string[]  $paths
     * @param  string[]  $excludePathPatterns  Regex, matched against each file's relative pathname
     * @param  string[]  $excludeFilePatterns  Regex, matched against the filename only
     * @return string[] Absolute file paths
     */
    public function scan(array $paths, array $excludePathPatterns = [], array $excludeFilePatterns = []): array
    {
        $existingPaths = array_values(array_filter($paths, fn (string $path): bool => is_dir($path) || is_file($path)));

        if ($existingPaths === []) {
            return [];
        }

        $finder = (new Finder)->files()->name('*.php')->in($existingPaths);

        $files = [];

        foreach ($finder as $file) {
            $relativePathname = $file->getRelativePathname();
            $filename = $file->getFilename();

            if (PatternMatcher::matchesAny($relativePathname, $excludePathPatterns)) {
                continue;
            }

            if (PatternMatcher::matchesAny($filename, $excludeFilePatterns)) {
                continue;
            }

            $files[] = $file->getRealPath();
        }

        sort($files);

        return $files;
    }
}
