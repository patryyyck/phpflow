<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Infrastructure\Messenger\MessengerRoutingReader;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class PartialHttpUrlGraphTest extends TestCase
{
    public function testGraphDoesNotCollapsePartiallyKnownUrlToDynamicUrl(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        $graph = (new BuildFlowGraph())->build($analysis);

        $nodes = array_values(array_filter(
            $graph->nodes(),
            static fn ($node): bool =>
                $node->type() === NodeType::HTTP_ENDPOINT
                && $node->label() === 'POST {dynamic}/v2/directory/search',
        ));

        self::assertCount(1, $nodes);
        self::assertNotSame('HTTP <dynamic URL>', $nodes[0]->label());
    }
    public function testGraphKeepsClassConstantEndpointResolved(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        $graph = (new BuildFlowGraph())->build($analysis);

        $nodes = array_values(array_filter(
            $graph->nodes(),
            static fn ($node): bool =>
                $node->type() === NodeType::HTTP_ENDPOINT
                && $node->label() === 'POST %api.base_url%/oauth/token',
        ));

        self::assertCount(1, $nodes);
    }


}
