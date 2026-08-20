<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Console\FlowTreeRenderer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class TryCatchFlowTest extends TestCase
{
    public function testTryCatchResponsesAreGroupedByControlBranch(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $flow = (new TraverseFlowGraph())->from(
            $graph,
            'route:GET:/try-catch',
        );

        self::assertNotNull($flow);

        $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

        self::assertStringContainsString('TRY', $rendered);
        self::assertStringContainsString('HTTP 200 JsonResponse', $rendered);
        self::assertStringContainsString('CATCH App\\Controller\\TryCatchProblem', $rendered);
        self::assertStringContainsString('HTTP 409 JsonResponse', $rendered);
        self::assertStringContainsString('CATCH Throwable', $rendered);
        self::assertStringContainsString('HTTP 500 JsonResponse', $rendered);
        self::assertStringContainsString('FINALLY', $rendered);
        self::assertStringContainsString(
            'App\\Controller\\CleanupServiceInterface::cleanup',
            $rendered,
        );
    }
}
