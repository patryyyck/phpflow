<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Console\FlowTreeRenderer;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class LoopFlowTest extends TestCase
{
    public function testForeachContainsNestedConditionalEffects(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $source = 'controller:App\\Loops\\LoopEffectsHandler::foreachLoop';
        $flow = (new TraverseFlowGraph())->from($graph, $source);

        self::assertNotNull($flow);

        $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

        self::assertStringContainsString(
            'FOREACH $items as $key => $item',
            $rendered,
        );
        self::assertStringContainsString(
            "IF \$item === 'send'",
            $rendered,
        );
        self::assertStringContainsString(
            'App\\Loops\\LoopClientInterface::send',
            $rendered,
        );
        self::assertStringContainsString(
            'App\\Loops\\LoopRepositoryInterface::record',
            $rendered,
        );
    }

    public function testEffectAfterForeachRemainsOutsideLoop(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $source = 'controller:App\\Loops\\LoopEffectsHandler::foreachLoop';

        $loopEdges = array_values(array_filter(
            $graph->outgoingEdges($source),
            static fn ($edge): bool =>
                $graph->node($edge->target())?->type() === NodeType::LOOP,
        ));

        self::assertCount(1, $loopEdges);

        $rootRepositoryCalls = array_values(array_filter(
            $graph->outgoingEdges($source),
            static fn ($edge): bool =>
                str_contains(
                    $graph->node($edge->target())?->label() ?? '',
                    'LoopRepositoryInterface::record',
                ),
        ));

        self::assertCount(1, $rootRepositoryCalls);
    }
}
