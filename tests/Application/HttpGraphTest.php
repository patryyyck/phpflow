<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Domain\Analysis\HttpCall;
use PhpFlow\Domain\Analysis\ProjectAnalysis;
use PhpFlow\Domain\Analysis\ProjectStatistics;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\NodeType;
use PHPUnit\Framework\TestCase;

final class HttpGraphTest extends TestCase
{
    public function testItLinksSourcesToHttpEndpoints(): void
    {
        $analysis = new ProjectAnalysis(
            new ProjectStatistics(0, 0, 0, 0),
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [new HttpCall(
                'App\\Controller\\ExportController::run',
                'Symfony\\Contracts\\HttpClient\\HttpClientInterface',
                'POST',
                'https://api.example.test/export',
            )],
        );

        $graph = (new BuildFlowGraph())->build($analysis);

        $httpNodes = array_values(array_filter(
            $graph->nodes(),
            static fn ($node): bool => $node->type() === NodeType::HTTP_ENDPOINT,
        ));

        self::assertCount(1, $httpNodes);
        self::assertSame('POST https://api.example.test/export', $httpNodes[0]->label());

        $edges = array_values(array_filter(
            $graph->edges(),
            static fn ($edge): bool => $edge->type() === EdgeType::CALLS,
        ));

        self::assertCount(1, $edges);
        self::assertSame('http', $edges[0]->label());
    }
}
