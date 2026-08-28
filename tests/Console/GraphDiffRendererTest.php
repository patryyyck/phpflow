<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Console;

use PhpFlow\Console\GraphDiffRenderer;
use PhpFlow\Domain\Diff\GraphDiff;
use PHPUnit\Framework\TestCase;

final class GraphDiffRendererTest extends TestCase
{
    public function testItRendersSummaryAndChanges(): void
    {
        $diff = new GraphDiff(
            [[
                'id' => 'http:new',
                'type' => 'http_endpoint',
                'label' => 'POST /v2/sync',
                'displayLabel' => 'POST /v2/sync',
            ]],
            [[
                'id' => 'route:old',
                'type' => 'route',
                'label' => 'GET /old',
                'displayLabel' => 'GET /old',
            ]],
            [[
                'source' => 'service:sync',
                'target' => 'http:new',
                'type' => 'calls',
            ]],
            [],
            [
                'routes' => ['added' => 0, 'removed' => 1],
                'externalHttp' => ['added' => 1, 'removed' => 0],
            ],
        );

        $output = implode("\n", (new GraphDiffRenderer())->render($diff));

        self::assertStringContainsString('Graph changes', $output);
        self::assertStringContainsString('Routes', $output);
        self::assertStringContainsString('External Http', $output);
        self::assertStringContainsString('+ [http_endpoint] POST /v2/sync', $output);
        self::assertStringContainsString('- [route] GET /old', $output);
        self::assertStringContainsString('+ service:sync --calls--> http:new', $output);
    }

    public function testItRendersNoChanges(): void
    {
        $diff = new GraphDiff([], [], [], [], []);

        self::assertSame(
            ['No graph changes detected.'],
            (new GraphDiffRenderer())->render($diff),
        );
    }
}
