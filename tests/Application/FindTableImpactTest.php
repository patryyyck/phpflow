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
}
