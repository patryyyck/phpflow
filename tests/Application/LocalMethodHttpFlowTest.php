<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Console\FlowTreeRenderer;
use PhpFlow\Infrastructure\Messenger\MessengerRoutingReader;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class LocalMethodHttpFlowTest extends TestCase
{
    public function testItFollowsPublicToPrivateMethodAndKeepsHttpUrlInsideLoop(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        $graph = (new BuildFlowGraph())->build($analysis);

        $flow = (new TraverseFlowGraph())->from(
            $graph,
            'route:POST:/local-http-flow',
        );

        self::assertNotNull($flow);

        $rendered = implode("\n", (new FlowTreeRenderer())->render($flow));

        self::assertStringContainsString(
            'App\\LocalHttp\\DirectoryRegistrationClient::getRegistrationStatus',
            $rendered,
        );
        self::assertStringContainsString(
            'App\\LocalHttp\\DirectoryRegistrationClient::fetchAllResults',
            $rendered,
        );
        self::assertStringContainsString(
            'POST %directory.base_url%/v2/directory/search',
            $rendered,
        );
        self::assertStringNotContainsString('POST {param:url}', $rendered);
    }
}
