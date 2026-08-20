<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class UnionMessageHandlerGraphTest extends TestCase
{
    public function testBothUnionMessagesPointToTheSameHandler(): void
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );
        $graph = (new BuildFlowGraph())->build($analysis);

        foreach ([
            'App\\UnionHandler\\FirstCommand',
            'App\\UnionHandler\\SecondCommand',
        ] as $message) {
            $edges = $graph->outgoingEdges('message:'.$message);

            self::assertNotEmpty($edges);
            self::assertSame(
                'handler:App\\UnionHandler\\UnionCommandHandler::__invoke',
                $edges[0]->target(),
            );
        }
    }
}
