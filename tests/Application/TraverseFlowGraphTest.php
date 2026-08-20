<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PHPUnit\Framework\TestCase;

final class TraverseFlowGraphTest extends TestCase
{
    public function testItTraversesRecursively(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node('route:r', NodeType::ROUTE, 'POST /users'));
        $graph->addNode(new Node('controller:c', NodeType::CONTROLLER, 'UserController::create'));
        $graph->addNode(new Node('message:m', NodeType::MESSAGE, 'CreateUser'));
        $graph->addNode(new Node('handler:h', NodeType::HANDLER, 'CreateUserHandler::__invoke'));

        $graph->addEdge(new Edge('route:r', 'controller:c', EdgeType::INVOKES));
        $graph->addEdge(new Edge('controller:c', 'message:m', EdgeType::DISPATCHES));
        $graph->addEdge(new Edge('message:m', 'handler:h', EdgeType::HANDLED_BY));

        $flow = (new TraverseFlowGraph())->from($graph, 'route:r');

        self::assertNotNull($flow);
        self::assertSame('POST /users', $flow->node()->label());
        self::assertSame('CreateUserHandler::__invoke', $flow->children()[0]->children()[0]->children()[0]->node()->label());
    }

    public function testItStopsOnCyclesWithoutRemovingThem(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node('message:a', NodeType::MESSAGE, 'A'));
        $graph->addNode(new Node('handler:b', NodeType::HANDLER, 'B'));
        $graph->addEdge(new Edge('message:a', 'handler:b', EdgeType::HANDLED_BY));
        $graph->addEdge(new Edge('handler:b', 'message:a', EdgeType::DISPATCHES));

        $flow = (new TraverseFlowGraph())->from($graph, 'message:a');

        self::assertNotNull($flow);
        $cycle = $flow->children()[0]->children()[0];

        self::assertTrue($cycle->cycle());
        self::assertSame('A', $cycle->node()->label());
    }
}
