<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Exporter;

use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Exporter\JsonNodeMetadata;
use PHPUnit\Framework\TestCase;

final class JsonNodeMetadataTest extends TestCase
{
    public function testDispatchedMessageIsNotMarkedAsEntryPoint(): void
    {
        $graph = new Graph();
        $route = new Node('route:POST:/sync', NodeType::ROUTE, 'POST /sync');
        $message = new Node(
            'message:App\\Message\\SyncCompany',
            NodeType::MESSAGE,
            'App\\Message\\SyncCompany',
        );

        $graph->addNode($route);
        $graph->addNode($message);
        $graph->addEdge(new Edge(
            $route->id(),
            $message->id(),
            EdgeType::DISPATCHES,
        ));

        $metadata = new JsonNodeMetadata();

        self::assertTrue($metadata->for($graph, $route)['entryPoint']);
        self::assertFalse($metadata->for($graph, $message)['entryPoint']);
    }
}
