<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Domain\Analysis\HttpCall;
use PhpFlow\Domain\Analysis\ProjectAnalysis;
use PhpFlow\Domain\Analysis\ProjectStatistics;
use PhpFlow\Domain\Analysis\RepositoryCall;
use PhpFlow\Domain\Analysis\ServiceCall;
use PhpFlow\Domain\Analysis\SourcePosition;
use PHPUnit\Framework\TestCase;

final class SourceOrderFlowTest extends TestCase
{
    public function testOutgoingEdgesFollowSourceOrderAcrossEffectTypes(): void
    {
        $source = 'App\\Handler\\ExampleHandler::__invoke';

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
            [
                new RepositoryCall(
                    $source,
                    'App\\Repository\\CompanyRepositoryInterface',
                    'findRequired',
                    new SourcePosition(10, 100),
                ),
                new RepositoryCall(
                    $source,
                    'App\\Repository\\CompanyRoutingRepositoryInterface',
                    'insert',
                    new SourcePosition(30, 300),
                ),
            ],
            [
                new HttpCall(
                    $source,
                    'Symfony\\Contracts\\HttpClient\\HttpClientInterface',
                    'POST',
                    'https://example.test',
                    new SourcePosition(20, 200),
                ),
            ],
            [
                new ServiceCall(
                    $source,
                    'App\\Service\\ExternalSyncClientInterface',
                    'register',
                    null,
                    new SourcePosition(15, 150),
                ),
            ],
        );

        $graph = (new BuildFlowGraph())->build($analysis);
        $edges = $graph->outgoingEdges('controller:'.$source);

        self::assertSame(
            [100, 150, 200, 300],
            array_map(
                static fn ($edge): ?int => $edge->order(),
                $edges,
            ),
        );
    }
}
