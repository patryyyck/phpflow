<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Domain\Analysis\MessageHandler;
use PhpFlow\Domain\Analysis\ProjectAnalysis;
use PhpFlow\Domain\Analysis\ProjectStatistics;
use PhpFlow\Domain\Analysis\RepositoryCall;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\NodeType;
use PHPUnit\Framework\TestCase;

final class RepositoryGraphTest extends TestCase
{
    public function testItLinksHandlersToRepositories(): void
    {
        $analysis = new ProjectAnalysis(
            new ProjectStatistics(0, 0, 0, 0),
            [],
            [],
            [],
            [],
            [new MessageHandler(
                'App\\Message\\PersistCompany',
                'App\\MessageHandler\\PersistCompanyHandler::__invoke',
            )],
            [],
            [],
            [],
            [new RepositoryCall(
                'App\\MessageHandler\\PersistCompanyHandler::__invoke',
                'App\\Repository\\CompanyRepository',
                'save',
            )],
        );

        $graph = (new BuildFlowGraph())->build($analysis);

        $repository = $graph->node('repository:App\\Repository\\CompanyRepository::save');
        self::assertNotNull($repository);
        self::assertSame(NodeType::REPOSITORY, $repository->type());

        $edges = array_values(array_filter(
            $graph->edges(),
            static fn ($edge): bool => $edge->type() === EdgeType::CALLS,
        ));

        self::assertCount(1, $edges);
        self::assertSame('repository', $edges[0]->label());
    }
}
