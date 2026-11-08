<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;

class UnusedImport extends AbstractRule implements FileRule
{
    public function id(): string
    {
        return 'SL204';
    }

    public function name(): string
    {
        return 'Unused import';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::Low;
    }

    public function check(FileContext $context): array
    {
        $referenced = $this->referencedAliases($context);
        $docText = implode("\n", array_map(fn (array $entry): string => $entry['comment']->getText(), $context->comments()));
        $classes = $context->classes();
        $symbol = $classes !== [] ? $context->classNameOf($classes[0]) : $context->relativeFile;
        $findings = [];

        foreach ($this->importedItems($context) as $item) {
            $alias = $item->getAlias()->toString();

            if (isset($referenced[strtolower($alias)])) {
                continue;
            }

            if (preg_match('/(?<![\w\\\\])'.preg_quote($alias, '/').'(?!\w)/i', $docText) === 1) {
                continue;
            }

            $findings[] = $this->finding(
                $context,
                $item,
                "Imported {$item->name->toString()} is never used.",
                'Remove the use statement.',
                snippet: 'use:'.$item->name->toString(),
                symbol: $symbol,
            );
        }

        return $findings;
    }

    /**
     * @return array<string, true>
     */
    private function referencedAliases(FileContext $context): array
    {
        $aliases = [];

        foreach ($context->findInstanceOf(Name::class) as $name) {
            $original = $name->getAttribute('originalName');

            if ($original instanceof Name && ! $original->isFullyQualified()) {
                $aliases[strtolower($original->getFirst())] = true;
            }
        }

        return $aliases;
    }

    /**
     * @return UseItem[]
     */
    private function importedItems(FileContext $context): array
    {
        $items = [];

        foreach ($context->findInstanceOf(Use_::class) as $use) {
            if ($use->type !== Use_::TYPE_NORMAL) {
                continue;
            }

            array_push($items, ...$use->uses);
        }

        foreach ($context->findInstanceOf(GroupUse::class) as $group) {
            foreach ($group->uses as $item) {
                $type = $item->type === Use_::TYPE_UNKNOWN ? $group->type : $item->type;

                if ($type === Use_::TYPE_NORMAL) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }
}
