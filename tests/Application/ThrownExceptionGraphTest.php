<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ThrownExceptionGraphTest extends TestCase
{
    public function testConditionalExceptionsRespectGuardContinuationStructure(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $source = 'controller:App\\ExceptionFlow\\ExceptionFlowHandler::run';

        $rootConditions = array_values(array_filter(
            $graph->outgoingEdges($source),
            static fn ($edge): bool =>
                $graph->node($edge->target())?->type() === NodeType::CONDITION,
        ));

        self::assertCount(1, $rootConditions);
        self::assertSame(
            'IF !$valid',
            $graph->node($rootConditions[0]->target())?->label(),
        );

        $firstException = $graph->outgoingEdges($rootConditions[0]->target());

        self::assertCount(1, $firstException);
        self::assertSame(
            NodeType::EXCEPTION,
            $graph->node($firstException[0]->target())?->type(),
        );
        self::assertSame(
            'throws App\\ExceptionFlow\\DomainProblem',
            $graph->node($firstException[0]->target())?->label(),
        );

        $continuations = array_values(array_filter(
            $graph->outgoingEdges($source),
            static fn ($edge): bool =>
                $graph->node($edge->target())?->type() === NodeType::CONTINUATION,
        ));

        self::assertCount(1, $continuations);
        self::assertSame(
            'CONTINUE',
            $graph->node($continuations[0]->target())?->label(),
        );

        $continuedConditions = array_values(array_filter(
            $graph->outgoingEdges($continuations[0]->target()),
            static fn ($edge): bool =>
                $graph->node($edge->target())?->type() === NodeType::CONDITION,
        ));

        self::assertCount(1, $continuedConditions);
        self::assertSame(
            'IF $items === []',
            $graph->node($continuedConditions[0]->target())?->label(),
        );
    }
}
