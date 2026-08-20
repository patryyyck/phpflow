<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Console\FlowTreeRenderer;
use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PHPUnit\Framework\TestCase;

final class ContextualHttpLoopTraversalTest extends TestCase
{
    public function testInvocationContextSurvivesADoWhileBranch(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node('route', NodeType::ROUTE, 'POST /search'));
        $graph->addNode(new Node('caller', NodeType::SERVICE, 'DirectoryGateway::search'));
        $graph->addNode(new Node('interface', NodeType::SERVICE, 'ClientInterface::request'));
        $graph->addNode(new Node('implementation', NodeType::SERVICE, 'HttpClient::request'));
        $graph->addNode(new Node('loop', NodeType::LOOP, 'DO WHILE $hasNextPage'));
        $graph->addNode(new Node(
            'http',
            NodeType::HTTP_ENDPOINT,
            '{param:method} {param:url}',
        ));

        $graph->addEdge(new Edge('route', 'caller', EdgeType::CALLS));
        $graph->addEdge(new Edge(
            'caller',
            'interface',
            EdgeType::CALLS,
            'calls',
            10,
            [
                'method' => 'POST',
                'url' => '%directory.base_url%/v2/directory/search',
            ],
        ));
        $graph->addEdge(new Edge('interface', 'implementation', EdgeType::CALLS));
        $graph->addEdge(new Edge('implementation', 'loop', EdgeType::CALLS));
        $graph->addEdge(new Edge('loop', 'http', EdgeType::CALLS));

        $flow = (new TraverseFlowGraph())->from($graph, 'route');

        self::assertNotNull($flow);

        $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

        self::assertStringContainsString(
            'POST %directory.base_url%/v2/directory/search',
            $rendered,
        );
        self::assertStringNotContainsString('POST {param:url}', $rendered);
    }
}
