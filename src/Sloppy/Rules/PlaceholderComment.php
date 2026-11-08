<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Return_;

class PlaceholderComment extends AbstractRule implements FileRule
{
    private const PATTERN = '/(?<![A-Za-z])(TODO|FIXME|XXX|HACK)(?![A-Za-z])/';

    public function id(): string
    {
        return 'SL103';
    }

    public function name(): string
    {
        return 'Placeholder comment';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::Low;
    }

    public function check(FileContext $context): array
    {
        $findings = [];

        foreach ($context->comments() as ['comment' => $comment, 'node' => $node]) {
            if (preg_match(self::PATTERN, $comment->getText(), $matches) !== 1) {
                continue;
            }

            $function = $context->enclosing($node, [ClassMethod::class, Function_::class]);
            $placeholderBody = $function !== null && $this->isPlaceholderBody($function->stmts ?? null);

            $findings[] = $this->finding(
                $context,
                $node,
                $placeholderBody
                    ? "{$matches[1]} comment stands in for the whole body."
                    : "{$matches[1]} comment left in code.",
                $placeholderBody
                    ? 'Implement the body, or remove the stub so callers do not rely on a method that silently does nothing.'
                    : 'Resolve it now, or move it to your issue tracker and link the ticket.',
                confidence: 'high',
                severity: $placeholderBody ? Severity::Medium : null,
                snippet: trim($comment->getText()),
                line: $comment->getStartLine(),
            );
        }

        return $findings;
    }

    private function isPlaceholderBody(?array $stmts): bool
    {
        if ($stmts === null) {
            return false;
        }

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Nop) {
                continue;
            }

            if ($stmt instanceof Return_ && ($stmt->expr === null || ($stmt->expr instanceof ConstFetch && $stmt->expr->name->toLowerString() === 'null'))) {
                continue;
            }

            return false;
        }

        return true;
    }
}
