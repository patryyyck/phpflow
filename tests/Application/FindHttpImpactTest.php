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
}
