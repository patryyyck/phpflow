<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Console\FlowSummaryRenderer;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class HttpResponseGraphTest extends TestCase
{
    public function testHttpResponseIsVisibleInFlowAndSummary(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $flow = (new TraverseFlowGraph())->from(
            $graph,
            'route:POST:/responses/created',
        );

        self::assertNotNull($flow);

        $summary = (new FlowSummaryRenderer())->render($flow);

        self::assertContains('RESPONSES', $summary);
        self::assertContains('  HTTP 201 JsonResponse', $summary);

        $controller = 'controller:App\\Controller\\HttpResponseController::created';
        $responseEdges = array_values(array_filter(
            $graph->outgoingEdges($controller),
            static fn ($edge): bool =>
                $graph->node($edge->target())?->type() === NodeType::HTTP_RESPONSE,
        ));

        self::assertCount(1, $responseEdges);
    }
}
