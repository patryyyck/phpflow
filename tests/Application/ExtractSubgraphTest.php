<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\ExtractSubgraph;
use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PHPUnit\Framework\TestCase;

final class ExtractSubgraphTest extends TestCase
{
    public function testItExtractsOnlyReachableNodes(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node('route:r1', NodeType::ROUTE, 'GET /a'));
        $graph->addNode(new Node('controller:c1', NodeType::CONTROLLER, 'AController::index'));
        $graph->addNode(new Node('message:m1', NodeType::MESSAGE, 'MessageA'));

        $graph->addNode(new Node('route:r2', NodeType::ROUTE, 'GET /b'));
        $graph->addNode(new Node('controller:c2', NodeType::CONTROLLER, 'BController::index'));

        $graph->addEdge(new Edge('route:r1', 'controller:c1', EdgeType::INVOKES));
        $graph->addEdge(new Edge('controller:c1', 'message:m1', EdgeType::DISPATCHES));
        $graph->addEdge(new Edge('route:r2', 'controller:c2', EdgeType::INVOKES));

        $subgraph = (new ExtractSubgraph())->from($graph, 'route:r1', 10);

        self::assertNotNull($subgraph);

        $ids = array_map(static fn ($node): string => $node->id(), $subgraph->nodes());

        self::assertContains('route:r1', $ids);
        self::assertContains('controller:c1', $ids);
        self::assertContains('message:m1', $ids);
        self::assertNotContains('route:r2', $ids);
        self::assertNotContains('controller:c2', $ids);
    }

    public function testItRespectsMaximumDepth(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node('route:r', NodeType::ROUTE, 'POST /users'));
        $graph->addNode(new Node('controller:c', NodeType::CONTROLLER, 'UserController::create'));
        $graph->addNode(new Node('message:m', NodeType::MESSAGE, 'CreateUser'));
        $graph->addNode(new Node('handler:h', NodeType::HANDLER, 'CreateUserHandler::__invoke'));

        $graph->addEdge(new Edge('route:r', 'controller:c', EdgeType::INVOKES));
        $graph->addEdge(new Edge('controller:c', 'message:m', EdgeType::DISPATCHES));
        $graph->addEdge(new Edge('message:m', 'handler:h', EdgeType::HANDLED_BY));

        $subgraph = (new ExtractSubgraph())->from($graph, 'route:r', 1);

        self::assertNotNull($subgraph);

        $ids = array_map(static fn ($node): string => $node->id(), $subgraph->nodes());

        self::assertContains('route:r', $ids);
        self::assertContains('controller:c', $ids);
        self::assertNotContains('message:m', $ids);
        self::assertNotContains('handler:h', $ids);
    }

    public function testItStopsOnCycles(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node('message:a', NodeType::MESSAGE, 'A'));
        $graph->addNode(new Node('handler:b', NodeType::HANDLER, 'B'));
        $graph->addEdge(new Edge('message:a', 'handler:b', EdgeType::HANDLED_BY));
        $graph->addEdge(new Edge('handler:b', 'message:a', EdgeType::DISPATCHES));

        $subgraph = (new ExtractSubgraph())->from($graph, 'message:a', 10);

        self::assertNotNull($subgraph);
        self::assertCount(2, $subgraph->nodes());
        self::assertCount(2, $subgraph->edges());
    }
}
