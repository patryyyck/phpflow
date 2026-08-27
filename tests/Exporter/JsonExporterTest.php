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

        $data = json_decode(
            (new JsonExporter())->export($graph),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame('1.0', $data['schemaVersion']);
        self::assertSame(
            [
                'id' => 'route:GET:/companies',
                'type' => 'route',
                'label' => 'GET /companies',
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

    public function testItOmitsOptionalNullEdgeFields(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node('route', NodeType::ROUTE, 'GET /'));
        $graph->addNode(new Node('service', NodeType::SERVICE, 'App\\Service::run'));
        $graph->addEdge(new Edge('route', 'service', EdgeType::CALLS));

        $data = json_decode(
            (new JsonExporter())->export($graph),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame(
            [
                'source' => 'route',
                'target' => 'service',
                'type' => 'calls',
            ],
            $data['edges'][0],
        );
    }
}
