<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\FindTableImpact;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Messenger\MessengerRoutingReader;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class FindTableImpactTest extends TestCase
{
    public function testItFindsRoutesThatCanReachATable(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $paths = (new FindTableImpact())->find($graph, 'record_links');

        self::assertNotEmpty($paths);
        self::assertSame('POST /consolidated', $paths[0]->root()->label());
        self::assertSame('INSERT record_links', $paths[0]->effect()->label());

        $labels = array_map(
            static fn ($node): string => $node->label(),
            $paths[0]->nodes(),
        );

        self::assertContains('App\\Consolidated\\ConsolidatedHandler::__invoke', $labels);
        self::assertContains(
            'App\\Consolidated\\ConsolidatedRepository::insert',
            $labels,
        );
    }

    public function testItReturnsNoImpactForAnUnknownTable(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        $paths = (new FindTableImpact())->find(
            (new BuildFlowGraph())->build($analysis),
            'does_not_exist',
        );

        self::assertSame([], $paths);
    }
    public function testItMatchesSchemaQualifiedTablesByBaseName(): void
    {
        $graph = new \PhpFlow\Domain\Graph\Graph();

        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'route:companies',
            \PhpFlow\Domain\Graph\NodeType::ROUTE,
            'GET /companies',
        ));
        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'database:companies',
            \PhpFlow\Domain\Graph\NodeType::DATABASE,
            'SELECT public.companies',
        ));
        $graph->addEdge(new \PhpFlow\Domain\Graph\Edge(
            'route:companies',
            'database:companies',
            \PhpFlow\Domain\Graph\EdgeType::CALLS,
        ));

        $paths = (new FindTableImpact())->find($graph, 'companies');

        self::assertCount(1, $paths);
        self::assertSame('SELECT public.companies', $paths[0]->effect()->label());

        self::assertCount(
            1,
            (new FindTableImpact())->find($graph, '"public"."companies"'),
        );
    }

    public function testItCanFilterTableImpactBySqlOperation(): void
    {
        $graph = new \PhpFlow\Domain\Graph\Graph();

        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'route:read',
            \PhpFlow\Domain\Graph\NodeType::ROUTE,
            'GET /companies',
        ));
        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'route:write',
            \PhpFlow\Domain\Graph\NodeType::ROUTE,
            'PUT /companies/{id}',
        ));
        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'database:select',
            \PhpFlow\Domain\Graph\NodeType::DATABASE,
            'SELECT companies',
        ));
        $graph->addNode(new \PhpFlow\Domain\Graph\Node(
            'database:update',
            \PhpFlow\Domain\Graph\NodeType::DATABASE,
            'UPDATE companies',
        ));

        $graph->addEdge(new \PhpFlow\Domain\Graph\Edge(
            'route:read',
            'database:select',
            \PhpFlow\Domain\Graph\EdgeType::CALLS,
        ));
        $graph->addEdge(new \PhpFlow\Domain\Graph\Edge(
            'route:write',
            'database:update',
            \PhpFlow\Domain\Graph\EdgeType::CALLS,
        ));

        $selectPaths = (new FindTableImpact())->find(
            $graph,
            'companies',
            'select',
        );

        self::assertCount(1, $selectPaths);
        self::assertSame('GET /companies', $selectPaths[0]->root()->label());
        self::assertSame('SELECT companies', $selectPaths[0]->effect()->label());

        $updatePaths = (new FindTableImpact())->find(
            $graph,
            'companies',
            'UPDATE',
        );

        self::assertCount(1, $updatePaths);
        self::assertSame('PUT /companies/{id}', $updatePaths[0]->root()->label());
        self::assertSame('UPDATE companies', $updatePaths[0]->effect()->label());
    }


}
