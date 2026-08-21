<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\ImpactEntryPoints;
use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PHPUnit\Framework\TestCase;

final class ImpactEntryPointsTest extends TestCase
{
    public function testItKeepsRoutesAndStandaloneMessagesButNotDispatchedMessages(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node('route', NodeType::ROUTE, 'POST /sync'));
        $graph->addNode(new Node('dispatched', NodeType::MESSAGE, 'App\\Message\\SyncRecord'));
        $graph->addNode(new Node('standalone', NodeType::MESSAGE, 'App\\Message\\ImportRecords'));

        $graph->addEdge(new Edge(
            'route',
            'dispatched',
            EdgeType::DISPATCHES,
        ));

        $labels = array_map(
            static fn (Node $node): string => $node->label(),
            (new ImpactEntryPoints())->find($graph),
        );

        sort($labels);

        self::assertSame(
            [
                'App\\Message\\ImportRecords',
                'POST /sync',
            ],
            $labels,
        );
    }
}
