<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\FileContext;
use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Comment;
use PhpParser\Comment\Doc;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt\InlineHTML;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class CommentedOutCode extends AbstractRule implements FileRule
{
    private ?Parser $parser = null;

    public function id(): string
    {
        return 'SL207';
    }

    public function name(): string
    {
        return 'Commented-out code';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::Low;
    }

    public function check(FileContext $context): array
    {
        $findings = [];

        foreach ($this->blocks($context) as ['comments' => $comments, 'node' => $node]) {
            $text = implode("\n", array_map(fn (Comment $comment): string => $this->strip($comment), $comments));

            if (! $this->looksLikeCode($text)) {
                continue;
            }

            $findings[] = $this->finding(
                $context,
                $node,
                'Block of commented-out code.',
                'Delete it. Version control already keeps the old code.',
                confidence: 'medium',
                snippet: $text,
                line: $comments[0]->getStartLine(),
            );
        }

        return $findings;
    }

    /**
     * Consecutive line comments attached to the same node form one block.
     *
     * @return array<int, array{comments: Comment[], node: Node}>
     */
    private function blocks(FileContext $context): array
    {
        $blocks = [];
        $current = null;

        foreach ($context->comments() as ['comment' => $comment, 'node' => $node]) {
            if ($comment instanceof Doc) {
                continue;
            }

            $isLineComment = ! str_starts_with($comment->getText(), '/*');
            $continues = $current !== null
                && $isLineComment
                && $current['line']
                && $current['node'] === $node
                && $comment->getStartLine() === end($current['comments'])->getEndLine() + 1;

            if ($continues) {
                $current['comments'][] = $comment;

                continue;
            }

            if ($current !== null) {
                $blocks[] = $current;
            }

            $current = ['comments' => [$comment], 'node' => $node, 'line' => $isLineComment];
        }

        if ($current !== null) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    private function strip(Comment $comment): string
    {
        $text = $comment->getText();

        if (str_starts_with($text, '/*')) {
            $text = (string) preg_replace(['#^/\*+#', '#\*+/$#', '#^\s*\*#m'], '', $text);
        } else {
            $text = (string) preg_replace('#^\s*(//|\#)#', '', $text);
        }

        return trim($text);
    }

    private function looksLikeCode(string $text): bool
    {
        if ($text === '' || preg_match('/^\s*(@|phpcs|phpstan|psalm|php-cs-fixer)/i', $text) === 1) {
            return false;
        }

        // Prose rarely ends a line with a statement terminator or brace.
        if (preg_match('/[;{}]\s*$/m', $text) !== 1) {
            return false;
        }

        $this->parser ??= (new ParserFactory)->createForNewestSupportedVersion();

        try {
            $stmts = $this->parser->parse('<?php '.$text) ?? [];
        } catch (Error) {
            return false;
        }

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Nop && ! $stmt instanceof InlineHTML) {
                return true;
            }
        }

        return false;
    }
}
