<?php

namespace Grafite\CognitiveLoad\Sloppy;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter\Standard;
use Throwable;

/**
 * Line-independent identity for a finding. The offending code is
 * pretty-printed with whitespace collapsed, so inserting lines or imports
 * above a finding never changes its fingerprint; editing the code itself does.
 */
class Fingerprint
{
    public static function snippetHash(Node|string $subject): string
    {
        if (is_string($subject)) {
            return sha1(self::collapse($subject));
        }

        try {
            $printer = new Standard;
            $printed = match (true) {
                $subject instanceof Expr => $printer->prettyPrintExpr($subject),
                $subject instanceof Stmt => $printer->prettyPrint([$subject]),
                default => $subject->getType(),
            };
        } catch (Throwable) {
            $printed = $subject->getType();
        }

        return sha1(self::collapse($printed));
    }

    public static function make(string $ruleId, string $file, string $symbol, string $snippetHash, int $occurrence): string
    {
        return sha1(implode('|', [$ruleId, $file, $symbol, $snippetHash, $occurrence]));
    }

    private static function collapse(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
