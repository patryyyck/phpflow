<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Console\FlowTreeRenderer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class UnreachableFlowTest extends TestCase
{
    public function testUnreachableEffectsDoNotAppearInRenderedFlow(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        foreach ([
            'afterReturn',
            'afterThrow',
            'afterContinue',
            'afterBreak',
        ] as $method) {
            $flow = (new TraverseFlowGraph())->from(
                $graph,
                'controller:App\\Unreachable\\UnreachableEffectsHandler::'.$method,
            );

            self::assertNotNull($flow);

            $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

            self::assertStringNotContainsString('::unreachable', $rendered);
        }
    }
    public function testEffectsAfterFullyTerminatingIfElseAreAbsent(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        foreach (['afterCompleteIfElse', 'afterCompleteElseIf'] as $method) {
            $flow = (new TraverseFlowGraph())->from(
                $graph,
                'controller:App\\Unreachable\\UnreachableEffectsHandler::'.$method,
            );

            self::assertNotNull($flow);

            $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

            self::assertStringNotContainsString('::unreachable', $rendered);
        }
    }


    public function testEffectsAfterFullyTerminatingTryCatchAreAbsent(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        foreach (['afterCompleteTryCatch', 'afterTerminatingFinally'] as $method) {
            $flow = (new TraverseFlowGraph())->from(
                $graph,
                'controller:App\\Unreachable\\UnreachableEffectsHandler::'.$method,
            );

            self::assertNotNull($flow);

            $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

            self::assertStringNotContainsString('::unreachable', $rendered);
        }
    }


    public function testEffectsAfterExhaustiveTerminatingMatchAreAbsent(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $flow = (new TraverseFlowGraph())->from(
            $graph,
            'controller:App\\Unreachable\\UnreachableEffectsHandler::afterTerminatingMatch',
        );

        self::assertNotNull($flow);

        $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

        self::assertStringNotContainsString('::unreachable', $rendered);
    }


}
