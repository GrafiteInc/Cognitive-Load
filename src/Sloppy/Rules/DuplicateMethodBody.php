<?php

namespace Grafite\CognitiveLoad\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\Severity;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter\Standard;

class DuplicateMethodBody extends AbstractRule implements ProjectRule
{
    public function id(): string
    {
        return 'SL401';
    }

    public function name(): string
    {
        return 'Duplicate method body';
    }

    public function defaultSeverity(): Severity
    {
        return Severity::Medium;
    }

    public function checkProject(array $contexts): array
    {
        $minStatements = (int) $this->option('min_statements', 5);
        $groups = [];

        foreach ($contexts as $context) {
            $functions = $context->finder->find($context->ast, fn (Node $node): bool => $node instanceof ClassMethod || $node instanceof Function_);

            foreach ($functions as $function) {
                $stmts = array_values(array_filter($function->stmts ?? [], fn (Node $stmt): bool => ! $stmt instanceof Nop));

                if (count($stmts) < $minStatements) {
                    continue;
                }

                $groups[$this->normalizedHash($stmts)][] = [
                    'context' => $context,
                    'node' => $function,
                    'symbol' => $context->symbolFor($function),
                ];
            }
        }

        $findings = [];

        foreach ($groups as $hash => $members) {
            if (count($members) < 2) {
                continue;
            }

            usort($members, fn (array $a, array $b): int => [$a['context']->relativeFile, $a['node']->getStartLine()] <=> [$b['context']->relativeFile, $b['node']->getStartLine()]);

            $original = $members[0];
            $others = count($members) - 2;

            foreach (array_slice($members, 1) as $member) {
                $findings[] = $this->finding(
                    $member['context'],
                    $member['node'],
                    sprintf(
                        'Body duplicates %s (%s:%d)%s.',
                        $original['symbol'],
                        $original['context']->relativeFile,
                        $original['node']->getStartLine(),
                        $others > 0 ? " and {$others} other location(s)" : '',
                    ),
                    'Extract the shared logic into one method, trait or class and call it from each place.',
                    snippet: $hash,
                );
            }
        }

        return $findings;
    }

    /**
     * Hash of the body with comments removed and variables renamed by order of
     * first appearance, so renaming locals does not hide a copy-paste.
     *
     * @param  Node[]  $stmts
     */
    private function normalizedHash(array $stmts): string
    {
        $cloner = new NodeTraverser(new CloningVisitor);
        $stmts = $cloner->traverse($stmts);

        $normalizer = new NodeTraverser(new class extends NodeVisitorAbstract
        {
            /** @var array<string, string> */
            private array $names = [];

            public function enterNode(Node $node): null
            {
                $node->setAttribute('comments', []);

                if ($node instanceof Variable && is_string($node->name) && $node->name !== 'this') {
                    $node->name = $this->names[$node->name] ??= 'v'.count($this->names);
                }

                return null;
            }
        });

        return sha1((new Standard)->prettyPrint($normalizer->traverse($stmts)));
    }
}
