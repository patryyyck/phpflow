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

final class DatabaseEffectGraphTest extends TestCase
{
    public function testRepositoryImplementationCanLeadToADatabaseEffect(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');

        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        $effects = array_values(array_filter(
            $analysis->databaseEffects(),
            static fn ($effect): bool =>
                $effect->source() === 'App\\Repository\\DoctrineCompanyRepository::save',
        ));

        self::assertNotEmpty($effects);
        self::assertSame('INSERT', $effects[0]->operation());
        self::assertSame('company', $effects[0]->target());

        $graph = (new BuildFlowGraph())->build($analysis);
        $source = 'repository_impl:App\\Repository\\DoctrineCompanyRepository::save';

        $edges = $graph->outgoingEdges($source);
        self::assertNotEmpty($edges);

        $databaseNode = $graph->node($edges[0]->target());

        self::assertNotNull($databaseNode);
        self::assertSame(NodeType::DATABASE, $databaseNode->type());
        self::assertSame('INSERT company', $databaseNode->label());
    }
    public function testDatabaseNodeLabelDoesNotExposeTheFullSql(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');

        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        $graph = (new BuildFlowGraph())->build($analysis);

        $databaseNodes = array_values(array_filter(
            $graph->nodes(),
            static fn ($node): bool => $node->type() === NodeType::DATABASE,
        ));

        self::assertNotEmpty($databaseNodes);

        foreach ($databaseNodes as $node) {
            self::assertStringNotContainsString(' VALUES ', $node->label());
            self::assertStringNotContainsString(' SET ', $node->label());
        }
    }


}
