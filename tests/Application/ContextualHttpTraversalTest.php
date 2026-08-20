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

final class ContextualHttpTraversalTest extends TestCase
{
    public function testServiceInvocationArgumentsResolveWrapperHttpParameters(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node('route', NodeType::ROUTE, 'POST /sync'));
        $graph->addNode(new Node('caller', NodeType::SERVICE, 'Gateway::create'));
        $graph->addNode(new Node('interface', NodeType::SERVICE, 'ClientInterface::request'));
        $graph->addNode(new Node('implementation', NodeType::SERVICE, 'HttpClient::request'));
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
            100,
            [
                'method' => 'POST',
                'url' => '{dynamic}/v2/directory/search',
            ],
        ));
        $graph->addEdge(new Edge('interface', 'implementation', EdgeType::CALLS));
        $graph->addEdge(new Edge('implementation', 'http', EdgeType::CALLS));

        $flow = (new TraverseFlowGraph())->from($graph, 'route');

        self::assertNotNull($flow);

        $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

        self::assertStringContainsString(
            'POST {dynamic}/v2/directory/search',
            $rendered,
        );
        self::assertStringNotContainsString('<dynamic URL>', $rendered);
    }
    public function testForwardedParameterContextDoesNotOverwriteConcreteCallerValues(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node('route2', NodeType::ROUTE, 'POST /directory/search'));
        $graph->addNode(new Node('caller2', NodeType::SERVICE, 'InvoicesClient::search'));
        $graph->addNode(new Node('wrapper1', NodeType::SERVICE, 'ClientInterface::request'));
        $graph->addNode(new Node('wrapper2', NodeType::SERVICE, 'HttpWrapper::request'));
        $graph->addNode(new Node('loop2', NodeType::LOOP, 'DO WHILE $hasNext'));
        $graph->addNode(new Node(
            'http2',
            NodeType::HTTP_ENDPOINT,
            '{param:method} {param:url}',
        ));

        $graph->addEdge(new Edge('route2', 'caller2', EdgeType::CALLS));

        $graph->addEdge(new Edge(
            'caller2',
            'wrapper1',
            EdgeType::CALLS,
            'calls',
            10,
            [
                'method' => 'POST',
                'url' => '%directory.base_url%/v2/directory/search',
            ],
        ));

        // The wrapper forwards its own parameters. These placeholders must be
        // resolved against the concrete values already carried by the path.
        $graph->addEdge(new Edge(
            'wrapper1',
            'wrapper2',
            EdgeType::CALLS,
            'calls',
            20,
            [
                'method' => '{param:method}',
                'url' => '{param:url}',
            ],
        ));

        $graph->addEdge(new Edge('wrapper2', 'loop2', EdgeType::CALLS));
        $graph->addEdge(new Edge('loop2', 'http2', EdgeType::CALLS));

        $flow = (new TraverseFlowGraph())->from($graph, 'route2');

        self::assertNotNull($flow);

        $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

        self::assertStringContainsString(
            'POST %directory.base_url%/v2/directory/search',
            $rendered,
        );
        self::assertStringNotContainsString('POST {param:url}', $rendered);
    }


    public function testConcreteSprintfUrlContextCanReachHttpWrapper(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node('route3', NodeType::ROUTE, 'POST /directory'));
        $graph->addNode(new Node('caller3', NodeType::SERVICE, 'DirectoryClient::getBySiren'));
        $graph->addNode(new Node('interface3', NodeType::SERVICE, 'ClientInterface::request'));
        $graph->addNode(new Node('implementation3', NodeType::SERVICE, 'HttpClient::request'));
        $graph->addNode(new Node(
            'http3',
            NodeType::HTTP_ENDPOINT,
            '{param:method} {param:url}',
        ));

        $graph->addEdge(new Edge('route3', 'caller3', EdgeType::CALLS));
        $graph->addEdge(new Edge(
            'caller3',
            'interface3',
            EdgeType::CALLS,
            'calls',
            10,
            [
                'method' => 'POST',
                'url' => '%directory.base_url%/v2/directory/search',
            ],
        ));
        $graph->addEdge(new Edge(
            'interface3',
            'implementation3',
            EdgeType::CALLS,
            'resolves_to',
            null,
            [
                'method' => '{param:method}',
                'url' => '{param:url}',
            ],
        ));
        $graph->addEdge(new Edge('implementation3', 'http3', EdgeType::CALLS));

        $flow = (new TraverseFlowGraph())->from($graph, 'route3');

        self::assertNotNull($flow);

        $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

        self::assertStringContainsString(
            'POST %directory.base_url%/v2/directory/search',
            $rendered,
        );
        self::assertStringNotContainsString('POST {param:url}', $rendered);
    }


}
