<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\FindMessageImpact;
use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PHPUnit\Framework\TestCase;

final class FindMessageImpactTest extends TestCase
{
    public function testItFindsRouteThatDispatchesMessageRecursively(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node('route', NodeType::ROUTE, 'POST /sync'));
        $graph->addNode(new Node('controller', NodeType::CONTROLLER, 'SyncController::run'));
        $graph->addNode(new Node('first', NodeType::MESSAGE, 'App\\Message\\StartSync'));
        $graph->addNode(new Node('handler', NodeType::HANDLER, 'StartSyncHandler::__invoke'));
        $graph->addNode(new Node('target', NodeType::MESSAGE, 'App\\Message\\SyncCompany'));

        $graph->addEdge(new Edge('route', 'controller', EdgeType::INVOKES));
        $graph->addEdge(new Edge('controller', 'first', EdgeType::DISPATCHES));
        $graph->addEdge(new Edge('first', 'handler', EdgeType::HANDLED_BY));
        $graph->addEdge(new Edge('handler', 'target', EdgeType::DISPATCHES));

        $paths = (new FindMessageImpact())->find(
            $graph,
            'App\\Message\\SyncCompany',
        );

        self::assertCount(1, $paths);
        self::assertSame('POST /sync', $paths[0]->root()->label());
        self::assertSame('App\\Message\\SyncCompany', $paths[0]->effect()->label());
    }

    public function testItAcceptsShortMessageClassName(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node('route', NodeType::ROUTE, 'POST /sync'));
        $graph->addNode(new Node('target', NodeType::MESSAGE, 'App\\Message\\SyncCompany'));
        $graph->addEdge(new Edge('route', 'target', EdgeType::DISPATCHES));

        $paths = (new FindMessageImpact())->find($graph, 'SyncCompany');

        self::assertCount(1, $paths);
        self::assertSame('App\\Message\\SyncCompany', $paths[0]->effect()->label());
    }

    public function testStandaloneTargetMessageIsReportedAsItsOwnEntryPoint(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node(
            'target',
            NodeType::MESSAGE,
            'App\\Message\\ScheduledImport',
        ));

        $paths = (new FindMessageImpact())->find(
            $graph,
            'ScheduledImport',
        );

        self::assertCount(1, $paths);
        self::assertSame('App\\Message\\ScheduledImport', $paths[0]->root()->label());
        self::assertSame('App\\Message\\ScheduledImport', $paths[0]->effect()->label());
    }

    public function testItReturnsNothingForUnknownMessage(): void
    {
        $graph = new Graph();

        self::assertSame(
            [],
            (new FindMessageImpact())->find($graph, 'UnknownMessage'),
        );
    }
}
