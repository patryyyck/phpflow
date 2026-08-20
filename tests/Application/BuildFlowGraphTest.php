<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class BuildFlowGraphTest extends TestCase
{
    public function testItBuildsRoutesControllersAndMessages(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        self::assertNotEmpty($graph->nodes());
        self::assertNotEmpty($graph->edges());

        $route = array_values(array_filter(
            $graph->nodes(),
            static fn ($node): bool =>
                $node->type() === NodeType::ROUTE
                && $node->label() === 'POST /users',
        ));

        self::assertCount(1, $route);

        $message = $graph->node('message:App\\Message\\CreateUser');
        self::assertNotNull($message);
        self::assertSame(NodeType::MESSAGE, $message->type());

        $dispatchEdges = array_values(array_filter(
            $graph->edges(),
            static fn ($edge): bool =>
                $edge->type() === EdgeType::DISPATCHES
                && $edge->target() === 'message:App\\Message\\CreateUser',
        ));

        self::assertCount(1, $dispatchEdges);
        self::assertSame(
            'controller:App\\Controller\\UserController::create',
            $dispatchEdges[0]->source(),
        );
    }

    public function testItDeduplicatesNodesAndEdges(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $builder = new BuildFlowGraph();

        $graph = $builder->build($analysis);

        $ids = array_map(static fn ($node): string => $node->id(), $graph->nodes());

        self::assertSame($ids, array_values(array_unique($ids)));
    }

    public function testItLinksMessagesToHandlers(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $edges = array_values(array_filter(
            $graph->edges(),
            static fn ($edge): bool =>
                $edge->type() === EdgeType::HANDLED_BY
                && $edge->source() === 'message:App\\Message\\CreateUser',
        ));

        self::assertCount(2, $edges);
    }


    public function testItLabelsAsyncDispatchesWithTheirTransport(): void
    {
        $analysis = new \PhpFlow\Domain\Analysis\ProjectAnalysis(
            new \PhpFlow\Domain\Analysis\ProjectStatistics(0, 0, 0, 0),
            [],
            [],
            [new \PhpFlow\Domain\Analysis\MessageDispatch('App\\Handler::run', 'App\\Event\\UserCreated')],
            [],
            [],
            [new \PhpFlow\Domain\Analysis\MessageRouting('App\\Event\\UserCreated', ['async'])],
        );

        $graph = (new BuildFlowGraph())->build($analysis);
        $edge = array_values(array_filter(
            $graph->edges(),
            static fn ($edge): bool => $edge->type() === EdgeType::DISPATCHES,
        ))[0];

        self::assertSame('async: async', $edge->label());
    }

    public function testDispatchFromHandlerUsesTheHandlerNode(): void
    {
        $analysis = new \PhpFlow\Domain\Analysis\ProjectAnalysis(
            new \PhpFlow\Domain\Analysis\ProjectStatistics(0, 0, 0, 0),
            [],
            [],
            [new \PhpFlow\Domain\Analysis\MessageDispatch(
                'App\\Handler\\FirstHandler::__invoke',
                'App\\Message\\SecondMessage',
            )],
            [],
            [new \PhpFlow\Domain\Analysis\MessageHandler(
                'App\\Message\\FirstMessage',
                'App\\Handler\\FirstHandler::__invoke',
            )],
        );

        $graph = (new BuildFlowGraph())->build($analysis);

        $edges = array_values(array_filter(
            $graph->edges(),
            static fn ($edge): bool =>
                $edge->type() === EdgeType::DISPATCHES
                && $edge->target() === 'message:App\\Message\\SecondMessage',
        ));

        self::assertCount(1, $edges);
        self::assertSame(
            'handler:App\\Handler\\FirstHandler::__invoke',
            $edges[0]->source(),
        );
    }

}
