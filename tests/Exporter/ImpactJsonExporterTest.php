<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Exporter;

use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Domain\Impact\ImpactPath;
use PhpFlow\Exporter\ImpactJsonExporter;
use PHPUnit\Framework\TestCase;

final class ImpactJsonExporterTest extends TestCase
{
    public function testItExportsVersionedImpactContract(): void
    {
        $graph = new Graph();
        $route = new Node('route:POST:/companies', NodeType::ROUTE, 'POST /companies');
        $service = new Node('service:create', NodeType::SERVICE, 'App\\CompanyService::create');
        $database = new Node('database:companies', NodeType::DATABASE, 'INSERT companies');

        foreach ([$route, $service, $database] as $node) {
            $graph->addNode($node);
        }

        $graph->addEdge(new Edge($route->id(), $service->id(), EdgeType::CALLS));
        $graph->addEdge(new Edge($service->id(), $database->id(), EdgeType::CALLS));

        $data = json_decode(
            (new ImpactJsonExporter())->export(
                $graph,
                'table',
                'companies',
                [new ImpactPath([$route, $service, $database])],
                'INSERT',
            ),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame('1.0', $data['schemaVersion']);
        self::assertSame(
            [
                'type' => 'table',
                'query' => 'companies',
                'operation' => 'INSERT',
            ],
            $data['target'],
        );
        self::assertSame(
            [[
                'id' => 'route:POST:/companies',
                'type' => 'route',
                'label' => 'POST /companies',
            ]],
            $data['entryPoints'],
        );
        self::assertCount(3, $data['nodes']);
        self::assertSame(
            [
                'entryPoint' => 'route:POST:/companies',
                'effect' => 'database:companies',
                'nodes' => [
                    'route:POST:/companies',
                    'service:create',
                    'database:companies',
                ],
            ],
            $data['paths'][0],
        );
    }

    public function testItExportsEmptyBlastRadiusAsValidJson(): void
    {
        $data = json_decode(
            (new ImpactJsonExporter())->export(
                new Graph(),
                'service',
                'UnknownService',
                [],
            ),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame([], $data['entryPoints']);
        self::assertSame([], $data['nodes']);
        self::assertSame([], $data['paths']);
    }
}
