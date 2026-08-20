<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class MethodReturnGraphTest extends TestCase
{
    public function testObjectReturnValueBecomesAGraphNode(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $source = 'controller:App\\ReturnFlow\\ReturnFlowHandler::direct';
        $edges = array_values(array_filter(
            $graph->outgoingEdges($source),
            static fn ($edge): bool =>
                $graph->node($edge->target())?->type() === NodeType::RETURN_VALUE,
        ));

        self::assertCount(1, $edges);
        self::assertSame(
            'returns App\\ReturnFlow\\ResultDto',
            $graph->node($edges[0]->target())?->label(),
        );
    }
}
