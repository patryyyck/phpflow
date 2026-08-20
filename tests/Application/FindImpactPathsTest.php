<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\FindImpactPaths;
use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PHPUnit\Framework\TestCase;

final class FindImpactPathsTest extends TestCase
{
    public function testItFindsReversePathsAndStopsOnCycles(): void
    {
        $graph = new Graph();
        $route = new Node('route', NodeType::ROUTE, 'GET /records');
        $handler = new Node('handler', NodeType::HANDLER, 'Handler::__invoke');
        $service = new Node('service', NodeType::SERVICE, 'Service::run');
        $database = new Node('db', NodeType::DATABASE, 'SELECT records');

        foreach ([$route, $handler, $service, $database] as $node) {
            $graph->addNode($node);
        }

        $graph->addEdge(new Edge('route', 'handler', EdgeType::INVOKES));
        $graph->addEdge(new Edge('handler', 'service', EdgeType::CALLS));
        $graph->addEdge(new Edge('service', 'handler', EdgeType::CALLS));
        $graph->addEdge(new Edge('service', 'db', EdgeType::CALLS));

        $paths = (new FindImpactPaths())->fromTargets($graph, [$database]);

        self::assertCount(1, $paths);
        self::assertSame(
            ['GET /records', 'Handler::__invoke', 'Service::run', 'SELECT records'],
            array_map(
                static fn ($node): string => $node->label(),
                $paths[0]->nodes(),
            ),
        );
    }
}
