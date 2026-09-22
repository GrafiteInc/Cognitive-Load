<?php

namespace Grafite\CognitiveLoad\Ast;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Traversed over a single ClassMethod's body at a time. Do not reuse an
 * instance across methods — construct one per method so counters can't leak.
 */
class MethodMetricsVisitor extends NodeVisitorAbstract
{
    private int $returnCount = 0;

    private int $propertyAccessCount = 0;

    private int $ifCount = 0;

    private int $ifDepth = 0;

    private int $ifMaxDepth = 0;

    private int $elseCount = 0;

    private int $cyclomaticComplexity = 1;

    /** @var array<string, true> */
    private array $seenVariables = [];

    public function enterNode(Node $node): null
    {
        if ($node instanceof Return_) {
            $this->returnCount++;
        } elseif ($node instanceof PropertyFetch || $node instanceof StaticPropertyFetch) {
            $this->propertyAccessCount++;
        } elseif ($node instanceof Variable) {
            if (is_string($node->name) && $node->name !== 'this') {
                $this->seenVariables[$node->name] = true;
            }
        } elseif ($node instanceof If_) {
            $this->ifCount++;
            $this->ifDepth++;
            $this->ifMaxDepth = max($this->ifMaxDepth, $this->ifDepth);
            $this->cyclomaticComplexity += 1 + count($node->elseifs);

            if ($node->else instanceof Else_) {
                $this->elseCount++;
            }
        } elseif ($node instanceof Case_) {
            if ($node->cond !== null) {
                $this->cyclomaticComplexity++;
            }
        } elseif ($node instanceof MatchArm) {
            if ($node->conds !== null) {
                $this->cyclomaticComplexity++;
            }
        } elseif ($node instanceof Catch_) {
            $this->cyclomaticComplexity++;
        } elseif ($node instanceof BooleanAnd || $node instanceof BooleanOr || $node instanceof Coalesce) {
            $this->cyclomaticComplexity++;
        } elseif ($node instanceof Ternary) {
            $this->cyclomaticComplexity++;
        } elseif ($node instanceof For_ || $node instanceof Foreach_ || $node instanceof While_ || $node instanceof Do_) {
            $this->cyclomaticComplexity++;
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof If_) {
            $this->ifDepth--;
        }

        return null;
    }

    public static function analyze(ClassMethod $method): MethodRawMetrics
    {
        $visitor = new self;

        foreach ($method->params as $param) {
            if ($param->var instanceof Variable && is_string($param->var->name)) {
                $visitor->seenVariables[$param->var->name] = true;
            }
        }

        if ($method->stmts !== null) {
            $traverser = new NodeTraverser;
            $traverser->addVisitor($visitor);
            $traverser->traverse($method->stmts);
        }

        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lineCount = $startLine > 0 && $endLine >= $startLine ? $endLine - $startLine + 1 : 0;

        return new MethodRawMetrics(
            name: $method->name->toString(),
            lineCount: $lineCount,
            argumentCount: count($method->params),
            returnCount: $visitor->returnCount,
            variableCount: count($visitor->seenVariables),
            propertyAccessCount: $visitor->propertyAccessCount,
            ifCount: $visitor->ifCount,
            ifNestingDepth: $visitor->ifMaxDepth,
            elseCount: $visitor->elseCount,
            cyclomaticComplexity: $visitor->cyclomaticComplexity,
        );
    }
}
