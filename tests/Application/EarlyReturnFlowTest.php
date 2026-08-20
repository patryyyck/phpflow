<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Console\FlowTreeRenderer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class EarlyReturnFlowTest extends TestCase
{
    public function testNominalPathIsGroupedUnderContinueAfterGuardClause(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $flow = (new TraverseFlowGraph())->from(
            $graph,
            'route:GET:/early-return',
        );

        self::assertNotNull($flow);

        $lines = (new FlowTreeRenderer())->render($flow);
        $rendered = implode("\n", $lines);

        self::assertStringContainsString('IF !$valid', $rendered);
        self::assertStringContainsString('HTTP 400 JsonResponse', $rendered);
        self::assertStringContainsString('CONTINUE', $rendered);
        self::assertStringContainsString('HTTP 200 JsonResponse', $rendered);

        $continueIndex = array_search(
            true,
            array_map(static fn (string $line): bool => str_contains($line, 'CONTINUE'), $lines),
            true,
        );
        $okIndex = array_search(
            true,
            array_map(static fn (string $line): bool => str_contains($line, 'HTTP 200 JsonResponse'), $lines),
            true,
        );

        self::assertIsInt($continueIndex);
        self::assertIsInt($okIndex);
        self::assertLessThan($okIndex, $continueIndex);
    }
}
