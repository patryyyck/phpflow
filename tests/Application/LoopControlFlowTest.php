<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Console\FlowTreeRenderer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class LoopControlFlowTest extends TestCase
{
    public function testContinueAndBreakAppearUnderTheirConditionsInsideLoop(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $flow = (new TraverseFlowGraph())->from(
            $graph,
            'controller:App\\Loops\\LoopEffectsHandler::controlFlow',
        );

        self::assertNotNull($flow);

        $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

        self::assertStringContainsString('FOREACH $items as $item', $rendered);
        self::assertStringContainsString("IF \$item === 'skip'", $rendered);
        self::assertStringContainsString('CONTINUE LOOP', $rendered);
        self::assertStringContainsString("IF \$item === 'stop'", $rendered);
        self::assertStringContainsString('BREAK', $rendered);
        self::assertStringContainsString(
            'App\\Loops\\LoopClientInterface::send',
            $rendered,
        );
    }

    public function testBreakLevelIsVisible(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $flow = (new TraverseFlowGraph())->from(
            $graph,
            'controller:App\\Loops\\LoopEffectsHandler::nestedBreak',
        );

        self::assertNotNull($flow);

        $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

        self::assertStringContainsString('BREAK 2', $rendered);
    }
}
