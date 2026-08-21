<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\FindHttpImpact;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Messenger\MessengerRoutingReader;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class FindHttpImpactTest extends TestCase
{
    public function testItFindsRoutesByHttpEndpointFragment(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $paths = (new FindHttpImpact())->find($graph, '/v1/resources');

        self::assertNotEmpty($paths);

        $consolidated = array_values(array_filter(
            $paths,
            static fn ($path): bool =>
                $path->root()->label() === 'POST /consolidated'
                && $path->effect()->label() === 'POST https://example.test/v1/resources',
        ));

        self::assertCount(1, $consolidated);
    }

    public function testItMatchesTheHttpMethodOrUrlCaseInsensitively(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        $paths = (new FindHttpImpact())->find(
            (new BuildFlowGraph())->build($analysis),
            'EXAMPLE.TEST/V1/RESOURCES',
        );

        self::assertNotEmpty($paths);
    }

    public function testItReturnsNoImpactForAnUnknownEndpoint(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        self::assertSame(
            [],
            (new FindHttpImpact())->find(
                (new BuildFlowGraph())->build($analysis),
                '/does-not-exist',
            ),
        );
    }
    public function testItFindsPartiallyResolvedHttpUrlsByKnownFragment(): void
    {
        $graph = new \PhpFlow\Domain\Graph\Graph();

        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'route:test',
            \PhpFlow\Domain\Graph\NodeType::ROUTE,
            'POST /directory/search',
        ));
        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'service:test',
            \PhpFlow\Domain\Graph\NodeType::SERVICE,
            'DirectoryClient::search',
        ));
        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'http:test',
            \PhpFlow\Domain\Graph\NodeType::HTTP_ENDPOINT,
            'POST {dynamic}/v2/directory/search',
        ));

        $graph->addEdge(new \PhpFlow\Domain\Graph\Edge(
            'route:test',
            'service:test',
            \PhpFlow\Domain\Graph\EdgeType::CALLS,
        ));
        $graph->addEdge(new \PhpFlow\Domain\Graph\Edge(
            'service:test',
            'http:test',
            \PhpFlow\Domain\Graph\EdgeType::CALLS,
        ));

        $paths = (new FindHttpImpact())->find(
            $graph,
            '/v2/directory/search',
        );

        self::assertCount(1, $paths);
        self::assertSame(
            'POST {dynamic}/v2/directory/search',
            $paths[0]->effect()->label(),
        );
    }


    public function testItMatchesHttpEndpointAfterResolvingPathContext(): void
    {
        $graph = new \PhpFlow\Domain\Graph\Graph();

        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'route:context',
            \PhpFlow\Domain\Graph\NodeType::ROUTE,
            'POST /directory',
        ));
        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'caller:context',
            \PhpFlow\Domain\Graph\NodeType::SERVICE,
            'DirectoryClient::getDirectory',
        ));
        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'wrapper:context',
            \PhpFlow\Domain\Graph\NodeType::SERVICE,
            'ClientInterface::request',
        ));
        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'http:context',
            \PhpFlow\Domain\Graph\NodeType::HTTP_ENDPOINT,
            '{param:method} {param:url}',
        ));

        $graph->addEdge(new \PhpFlow\Domain\Graph\Edge(
            'route:context',
            'caller:context',
            \PhpFlow\Domain\Graph\EdgeType::CALLS,
        ));
        $graph->addEdge(new \PhpFlow\Domain\Graph\Edge(
            'caller:context',
            'wrapper:context',
            \PhpFlow\Domain\Graph\EdgeType::CALLS,
            'calls',
            10,
            [
                'method' => 'POST',
                'url' => '%directory.base_url%/v2/directory/search',
            ],
        ));
        $graph->addEdge(new \PhpFlow\Domain\Graph\Edge(
            'wrapper:context',
            'http:context',
            \PhpFlow\Domain\Graph\EdgeType::CALLS,
        ));

        $paths = (new FindHttpImpact())->find(
            $graph,
            '/v2/directory/search',
        );

        self::assertCount(1, $paths);
        self::assertSame(
            'POST %directory.base_url%/v2/directory/search',
            $paths[0]->effect()->label(),
        );
    }


    public function testItFindsStandaloneMessengerProcessesThatCallHttp(): void
    {
        $graph = new \PhpFlow\Domain\Graph\Graph();

        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'message:http-import',
            \PhpFlow\Domain\Graph\NodeType::MESSAGE,
            'App\\Message\\PushDirectory',
        ));
        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'handler:http-import',
            \PhpFlow\Domain\Graph\NodeType::HANDLER,
            'App\\MessageHandler\\PushDirectoryHandler::__invoke',
        ));
        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'http:push',
            \PhpFlow\Domain\Graph\NodeType::HTTP_ENDPOINT,
            'POST %directory.base_url%/v2/push',
        ));

        $graph->addEdge(new \PhpFlow\Domain\Graph\Edge(
            'message:http-import',
            'handler:http-import',
            \PhpFlow\Domain\Graph\EdgeType::HANDLED_BY,
        ));
        $graph->addEdge(new \PhpFlow\Domain\Graph\Edge(
            'handler:http-import',
            'http:push',
            \PhpFlow\Domain\Graph\EdgeType::CALLS,
        ));

        $paths = (new FindHttpImpact())->find(
            $graph,
            '/v2/push',
        );

        self::assertCount(1, $paths);
        self::assertSame(
            'App\\Message\\PushDirectory',
            $paths[0]->root()->label(),
        );
        self::assertSame(
            'POST %directory.base_url%/v2/push',
            $paths[0]->effect()->label(),
        );
    }


}
