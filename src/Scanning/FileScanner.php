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
        $directories = array_values(array_filter($paths, fn (string $path): bool => is_dir($path)));

        // Finder only accepts directories, so explicitly named files are added directly.
        $files = array_values(array_filter(array_map(
            fn (string $path): string|false => is_file($path) && str_ends_with($path, '.php') ? realpath($path) : false,
            $paths,
        )));

        if ($directories === []) {
            sort($files);

            return $files;
        }

        $finder = (new Finder)->files()->name('*.php')->in($directories);

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

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }
}
