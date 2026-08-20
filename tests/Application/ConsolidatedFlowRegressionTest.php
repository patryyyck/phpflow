<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Console\FlowSummaryRenderer;
use PhpFlow\Infrastructure\Messenger\MessengerRoutingReader;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ConsolidatedFlowRegressionTest extends TestCase
{
    public function testCompleteRouteFlowKeepsTheStableArchitecturalEffects(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');

        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        $graph = (new BuildFlowGraph())->build($analysis);
        $flow = (new TraverseFlowGraph())->from(
            $graph,
            'route:POST:/consolidated',
        );

        self::assertNotNull($flow);

        $summary = (new FlowSummaryRenderer())->render($flow);

        self::assertContains('  POST https://example.test/v1/resources', $summary);
        self::assertContains('  SELECT companies', $summary);
        self::assertContains('  INSERT record_links', $summary);
        self::assertContains('  App\\Consolidated\\ConsolidatedProblem', $summary);
        self::assertContains('  App\\Consolidated\\ConsolidatedResponse', $summary);
    }
}
