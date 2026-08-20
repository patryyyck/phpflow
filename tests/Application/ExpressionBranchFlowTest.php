<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Console\FlowTreeRenderer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ExpressionBranchFlowTest extends TestCase
{
    public function testTernaryRepositoryCallsAreGroupedByBranch(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $flow = (new TraverseFlowGraph())->from(
            $graph,
            'controller:App\\ExpressionBranches\\ExpressionBranchHandler::ternary',
        );

        self::assertNotNull($flow);

        $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

        self::assertStringContainsString('TERNARY $exists THEN', $rendered);
        self::assertStringContainsString(
            'ExpressionRepositoryInterface::update',
            $rendered,
        );
        self::assertStringContainsString('TERNARY $exists ELSE', $rendered);
        self::assertStringContainsString(
            'ExpressionRepositoryInterface::insert',
            $rendered,
        );
    }

    public function testCoalesceAndShortCircuitEffectsAreGrouped(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $coalesce = (new TraverseFlowGraph())->from(
            $graph,
            'controller:App\\ExpressionBranches\\ExpressionBranchHandler::coalesce',
        );
        self::assertNotNull($coalesce);
        $coalesceText = implode("\n", (new FlowTreeRenderer())->render($coalesce));
        self::assertStringContainsString('COALESCE $result IS NULL', $coalesceText);
        self::assertStringContainsString(
            'ExpressionRepositoryInterface::fallback',
            $coalesceText,
        );

        $short = (new TraverseFlowGraph())->from(
            $graph,
            'controller:App\\ExpressionBranches\\ExpressionBranchHandler::shortCircuit',
        );
        self::assertNotNull($short);
        $shortText = implode("\n", (new FlowTreeRenderer())->render($short));
        self::assertStringContainsString('IF $enabled', $shortText);
        self::assertStringContainsString(
            'ExpressionClientInterface::register',
            $shortText,
        );
        self::assertStringContainsString('IF NOT ($disabled)', $shortText);
        self::assertStringContainsString(
            'ExpressionClientInterface::notify',
            $shortText,
        );
    }
}
