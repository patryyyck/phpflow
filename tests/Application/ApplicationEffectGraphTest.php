<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ApplicationEffectGraphTest extends TestCase
{
    public function testItAddsApplicationEffectsToTheGraphInSourceOrder(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $source = 'controller:App\\Effects\\ApplicationEffectsHandler::run';
        $edges = $graph->outgoingEdges($source);

        self::assertCount(3, $edges);

        $types = array_map(
            static fn ($edge) => $graph->node($edge->target())?->type(),
            $edges,
        );

        self::assertSame(
            [NodeType::MAIL, NodeType::FILESYSTEM, NodeType::CACHE],
            $types,
        );
    }
}
