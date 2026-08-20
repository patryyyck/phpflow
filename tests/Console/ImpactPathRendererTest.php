<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Console;

use PhpFlow\Console\ImpactPathRenderer;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Domain\Impact\ImpactPath;
use PHPUnit\Framework\TestCase;

final class ImpactPathRendererTest extends TestCase
{
    public function testItRendersAPathFromRouteToEffect(): void
    {
        $path = new ImpactPath([
            new Node('route', NodeType::ROUTE, 'POST /records'),
            new Node('handler', NodeType::HANDLER, 'RecordHandler::__invoke'),
            new Node('db', NodeType::DATABASE, 'INSERT records'),
        ]);

        self::assertSame(
            [
                'POST /records',
                '└── RecordHandler::__invoke',
                '    └── INSERT records',
            ],
            (new ImpactPathRenderer())->render($path),
        );
    }
}
