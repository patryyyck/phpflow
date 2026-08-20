<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class BranchingGraphTest extends TestCase
{
    public function testHttpResponsesAreNestedUnderTheirSyntacticBranches(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $controller = 'controller:App\\Controller\\BranchingController::branches';
        $branchEdges = array_values(array_filter(
            $graph->outgoingEdges($controller),
            static fn ($edge): bool =>
                $graph->node($edge->target())?->type() === NodeType::CONDITION,
        ));

        self::assertCount(3, $branchEdges);

        self::assertSame(
            [
                "IF \$state === 'created'",
                "ELSEIF \$state === 'accepted'",
                'ELSE',
            ],
            array_map(
                static fn ($edge): string => $graph->node($edge->target())?->label() ?? '',
                $branchEdges,
            ),
        );

        foreach ($branchEdges as $edge) {
            $children = $graph->outgoingEdges($edge->target());
            self::assertCount(1, $children);
            self::assertSame(
                NodeType::HTTP_RESPONSE,
                $graph->node($children[0]->target())?->type(),
            );
        }
    }
}
