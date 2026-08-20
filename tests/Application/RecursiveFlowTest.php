<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\DetectGraphCycles;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Messenger\MessengerRoutingReader;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class RecursiveFlowTest extends TestCase
{
    public function testItBuildsRecursiveMessageFlowAndDetectsCycle(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');

        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        $graph = (new BuildFlowGraph())->build($analysis);

        self::assertNotNull($graph->node('message:App\\Recursive\\FirstMessage'));
        self::assertNotNull($graph->node('handler:App\\Recursive\\FirstMessageHandler::__invoke'));
        self::assertNotNull($graph->node('message:App\\Recursive\\SecondMessage'));
        self::assertNotNull($graph->node('handler:App\\Recursive\\SecondMessageHandler::__invoke'));
        self::assertNotNull($graph->node('message:App\\Recursive\\ThirdMessage'));
        self::assertNotNull($graph->node('handler:App\\Recursive\\ThirdMessageHandler::__invoke'));

        $cycleIds = (new DetectGraphCycles())->cycleNodeIds($graph);

        self::assertContains('message:App\\Recursive\\FirstMessage', $cycleIds);
        self::assertContains('handler:App\\Recursive\\ThirdMessageHandler::__invoke', $cycleIds);
    }
}
