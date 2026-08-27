<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Exporter;

use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Exporter\JsonExporter;
use PHPUnit\Framework\TestCase;

final class JsonExporterTest extends TestCase
{
    public function testItExportsStableVersionedGraphContract(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node('route:GET:/companies', NodeType::ROUTE, 'GET /companies'));
        $graph->addNode(new Node('service:list', NodeType::SERVICE, 'App\\CompanyService::list'));
        $graph->addEdge(new Edge(
            'route:GET:/companies',
            'service:list',
            EdgeType::CALLS,
            'calls',
            12,
            ['url' => '/companies'],
        ));

        $data = $this->decode($graph);

        self::assertSame('1.2', $data['schemaVersion']);
        self::assertSame(
            [
                'id' => 'route:GET:/companies',
                'type' => 'route',
                'label' => 'GET /companies',
                'displayLabel' => 'GET /companies',
                'metadata' => [
                    'entryPoint' => true,
                    'route' => [
                        'method' => 'GET',
                        'path' => '/companies',
                    ],
                ],
            ],
            $data['nodes'][0],
        );
        self::assertSame(
            [
                'source' => 'route:GET:/companies',
                'target' => 'service:list',
                'type' => 'calls',
                'label' => 'calls',
                'order' => 12,
                'context' => ['url' => '/companies'],
            ],
            $data['edges'][0],
        );
    }

    public function testItExportsStructuredMetadataForUiConsumers(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node(
            'message:App\\Message\\SyncCompany',
            NodeType::MESSAGE,
            'App\\Message\\SyncCompany',
        ));
        $graph->addNode(new Node(
            'handler:sync',
            NodeType::HANDLER,
            'App\\Handler\\SyncCompanyHandler::__invoke',
        ));
        $graph->addNode(new Node(
            'database:sync',
            NodeType::DATABASE,
            'UPDATE public.companies',
        ));
        $graph->addNode(new Node(
            'http:sync',
            NodeType::HTTP_ENDPOINT,
            'POST %directory.base_url%/v2/sync',
        ));
        $graph->addNode(new Node(
            'exception:sync',
            NodeType::EXCEPTION,
            'throws App\\Exception\\SyncFailed',
        ));

        $graph->addEdge(new Edge(
            'message:App\\Message\\SyncCompany',
            'handler:sync',
            EdgeType::HANDLED_BY,
        ));

        $data = $this->decode($graph);

        self::assertSame(
            [
                'entryPoint' => true,
                'message' => [
                    'class' => 'App\\Message\\SyncCompany',
                    'shortName' => 'SyncCompany',
                    'namespace' => 'App\\Message',
                    'file' => null,
                ],
            ],
            $data['nodes'][0]['metadata'],
        );

        self::assertSame(
            [
                'entryPoint' => false,
                'callable' => [
                    'class' => 'App\\Handler\\SyncCompanyHandler',
                    'shortName' => 'SyncCompanyHandler',
                    'namespace' => 'App\\Handler',
                    'method' => '__invoke',
                    'file' => null,
                ],
            ],
            $data['nodes'][1]['metadata'],
        );

        self::assertSame(
            [
                'entryPoint' => false,
                'database' => [
                    'operation' => 'UPDATE',
                    'target' => 'public.companies',
                ],
            ],
            $data['nodes'][2]['metadata'],
        );

        self::assertSame(
            [
                'entryPoint' => false,
                'http' => [
                    'method' => 'POST',
                    'url' => '%directory.base_url%/v2/sync',
                ],
            ],
            $data['nodes'][3]['metadata'],
        );

        self::assertSame(
            [
                'entryPoint' => false,
                'exception' => [
                    'class' => 'App\\Exception\\SyncFailed',
                    'shortName' => 'SyncFailed',
                    'namespace' => 'App\\Exception',
                    'file' => null,
                ],
            ],
            $data['nodes'][4]['metadata'],
        );
    }

    public function testItOmitsOptionalNullEdgeFields(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node('route', NodeType::ROUTE, 'GET /'));
        $graph->addNode(new Node('service', NodeType::SERVICE, 'App\\Service::run'));
        $graph->addEdge(new Edge('route', 'service', EdgeType::CALLS));

        $data = $this->decode($graph);

        self::assertSame(
            [
                'source' => 'route',
                'target' => 'service',
                'type' => 'calls',
            ],
            $data['edges'][0],
        );
    }

    /** @return array<string, mixed> */
    private function decode(Graph $graph): array
    {
        return json_decode(
            (new JsonExporter())->export($graph),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
