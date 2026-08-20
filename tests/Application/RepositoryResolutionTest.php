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

final class RepositoryResolutionTest extends TestCase
{
    public function testItPrefersTheProductionRepositoryImplementation(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');

        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        $calls = array_values(array_filter(
            $analysis->repositoryCalls(),
            static fn ($call): bool =>
                $call->repository() === 'App\\Repository\\CompanyRepository'
                && $call->method() === 'save',
        ));

        self::assertCount(1, $calls);
        self::assertSame(
            'App\\Repository\\DoctrineCompanyRepository',
            $calls[0]->implementation(),
        );

        $graph = (new BuildFlowGraph())->build($analysis);
        $interfaceNode = 'repository:App\\Repository\\CompanyRepository::save';
        $implementationNode = 'repository_impl:App\\Repository\\DoctrineCompanyRepository::save';

        self::assertNotNull($graph->node($interfaceNode));
        self::assertNotNull($graph->node($implementationNode));
        self::assertSame(NodeType::REPOSITORY, $graph->node($implementationNode)?->type());

        $targets = array_map(
            static fn ($edge): string => $edge->target(),
            $graph->outgoingEdges($interfaceNode),
        );

        self::assertContains($implementationNode, $targets);
    }
}
