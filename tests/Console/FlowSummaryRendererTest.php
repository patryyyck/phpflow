<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Console;

use PhpFlow\Console\FlowSummaryRenderer;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Domain\Graph\TraversalStep;
use PHPUnit\Framework\TestCase;

final class FlowSummaryRendererTest extends TestCase
{
    public function testItSummarizesOnlyArchitecturalEffectsAndResults(): void
    {
        $flow = new TraversalStep(
            new Node('route', NodeType::ROUTE, 'POST /companies'),
            [
                new TraversalStep(
                    new Node('handler', NodeType::HANDLER, 'App\\Handler::__invoke'),
                    [
                        new TraversalStep(
                            new Node('select', NodeType::DATABASE, 'SELECT companies'),
                        ),
                        new TraversalStep(
                            new Node('http', NodeType::HTTP_ENDPOINT, 'POST %api%/v1/resources'),
                        ),
                        new TraversalStep(
                            new Node('exception', NodeType::EXCEPTION, 'throws App\\DomainProblem'),
                        ),
                        new TraversalStep(
                            new Node('insert', NodeType::DATABASE, 'INSERT record_links'),
                        ),
                        new TraversalStep(
                            new Node('return', NodeType::RETURN_VALUE, 'returns App\\ResponseDto'),
                        ),
                    ],
                ),
            ],
        );

        self::assertSame(
            [
                'HTTP',
                '  POST %api%/v1/resources',
                '',
                'DATABASE',
                '  SELECT companies',
                '  INSERT record_links',
                '',
                'EXCEPTIONS',
                '  App\\DomainProblem',
                '',
                'RETURNS',
                '  App\\ResponseDto',
            ],
            (new FlowSummaryRenderer())->render($flow),
        );
    }

    public function testItDeduplicatesTheSameGraphNodeReachedBySeveralBranches(): void
    {
        $database = new TraversalStep(
            new Node('db', NodeType::DATABASE, 'SELECT companies'),
        );

        $flow = new TraversalStep(
            new Node('route', NodeType::ROUTE, 'GET /companies'),
            [$database, $database],
        );

        self::assertSame(
            ['DATABASE', '  SELECT companies'],
            (new FlowSummaryRenderer())->render($flow),
        );
    }

    public function testItIncludesMailFilesystemAndCacheEffects(): void
    {
        $flow = new TraversalStep(
            new Node('route', NodeType::ROUTE, 'POST /export'),
            [
                new TraversalStep(new Node('mail', NodeType::MAIL, 'SEND EMAIL')),
                new TraversalStep(new Node('file', NodeType::FILESYSTEM, 'WRITE /tmp/export.csv')),
                new TraversalStep(new Node('cache', NodeType::CACHE, 'CACHE DELETE company.42')),
            ],
        );

        self::assertSame(
            [
                'MAIL',
                '  SEND EMAIL',
                '',
                'FILESYSTEM',
                '  WRITE /tmp/export.csv',
                '',
                'CACHE',
                '  CACHE DELETE company.42',
            ],
            (new FlowSummaryRenderer())->render($flow),
        );
    }
}
