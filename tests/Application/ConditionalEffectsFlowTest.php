<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Console\FlowTreeRenderer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ConditionalEffectsFlowTest extends TestCase
{
    public function testNonTerminalCallsAreGroupedUnderTheirBusinessBranch(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $flow = (new TraverseFlowGraph())->from(
            $graph,
            'controller:App\\ConditionalEffects\\ConditionalEffectsHandler::run',
        );

        self::assertNotNull($flow);

        $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

        self::assertStringContainsString("IF \$state === 'active'", $rendered);
        self::assertStringContainsString(
            'App\\ConditionalEffects\\PartnerClientInterface::activate',
            $rendered,
        );
        self::assertStringContainsString(
            'App\\ConditionalEffects\\AuditRepositoryInterface::record',
            $rendered,
        );
        self::assertStringContainsString("ELSEIF \$state === 'suspended'", $rendered);
        self::assertStringContainsString(
            'App\\ConditionalEffects\\PartnerClientInterface::suspend',
            $rendered,
        );
        self::assertStringContainsString('ELSE', $rendered);
    }

    public function testCallAfterConditionalRemainsOnNominalMethodPath(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);
        $graph = (new BuildFlowGraph())->build($analysis);

        $source = 'controller:App\\ConditionalEffects\\ConditionalEffectsHandler::run';

        $rootRepositoryCalls = array_values(array_filter(
            $graph->outgoingEdges($source),
            static fn ($edge): bool =>
                str_contains($edge->target(), 'AuditRepositoryInterface::record'),
        ));

        self::assertCount(1, $rootRepositoryCalls);
    }
}
