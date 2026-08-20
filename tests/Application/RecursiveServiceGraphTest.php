<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Infrastructure\Messenger\MessengerRoutingReader;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class RecursiveServiceGraphTest extends TestCase
{
    public function testItConnectsResolvedServiceImplementationsToTheirHttpEffects(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');

        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        $graph = (new BuildFlowGraph())->build($analysis);

        $interfaceId = 'service:App\\Sync\\ExternalSyncClientInterface::register';
        $implementationId = 'service_impl:App\\Sync\\ExternalSyncClient::register';

        self::assertNotNull($graph->node($interfaceId));
        self::assertNotNull($graph->node($implementationId));
        self::assertSame(NodeType::SERVICE, $graph->node($implementationId)?->type());

        $implementationEdges = $graph->outgoingEdges($implementationId);

        self::assertNotEmpty($implementationEdges);

        $httpTargets = array_filter(
            $implementationEdges,
            static fn ($edge): bool =>
                str_starts_with($edge->target(), 'http:'),
        );

        self::assertNotEmpty($httpTargets);
    }
    public function testConcreteServiceCallsCanFormACycleWithoutInfiniteTraversal(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');

        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        $graph = (new BuildFlowGraph())->build($analysis);

        $a = 'service:App\\ServiceCycle\\CyclicServiceA::run';
        $b = 'service:App\\ServiceCycle\\CyclicServiceB::run';

        self::assertNotNull($graph->node($a));
        self::assertNotNull($graph->node($b));

        $flow = (new TraverseFlowGraph())->from($graph, $a);

        self::assertNotNull($flow);
        self::assertNotEmpty($flow->children());
        self::assertSame(
            'App\\ServiceCycle\\CyclicServiceB::run',
            $flow->children()[0]->node()->label(),
        );
        self::assertTrue(
            $flow->children()[0]->children()[0]->cycle(),
        );
    }


}
