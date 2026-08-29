<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Exporter;

use PhpFlow\Domain\Diff\GraphDiff;
use PhpFlow\Exporter\GraphDiffJsonExporter;
use PHPUnit\Framework\TestCase;

final class GraphDiffJsonExporterTest extends TestCase
{
    public function testItExportsVersionedMachineReadableDiff(): void
    {
        $diff = new GraphDiff(
            [['id' => 'http:new', 'type' => 'http_endpoint', 'label' => 'POST /v2/sync']],
            [['id' => 'route:old', 'type' => 'route', 'label' => 'GET /old']],
            [['source' => 'service:sync', 'target' => 'http:new', 'type' => 'calls']],
            [],
            [
                'routes' => ['added' => 0, 'removed' => 1],
                'externalHttp' => ['added' => 1, 'removed' => 0],
            ],
        );

        $data = json_decode(
            (new GraphDiffJsonExporter())->export($diff),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame('1.0', $data['schemaVersion']);
        self::assertTrue($data['hasChanges']);
        self::assertSame($diff->summary(), $data['summary']);
        self::assertSame($diff->addedNodes(), $data['nodes']['added']);
        self::assertSame($diff->removedNodes(), $data['nodes']['removed']);
        self::assertSame($diff->addedEdges(), $data['edges']['added']);
        self::assertSame([], $data['edges']['removed']);
    }

    public function testItExportsNoChangesExplicitly(): void
    {
        $data = json_decode(
            (new GraphDiffJsonExporter())->export(new GraphDiff([], [], [], [], [])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertFalse($data['hasChanges']);
        self::assertSame([], $data['summary']);
        self::assertSame([], $data['nodes']['added']);
        self::assertSame([], $data['nodes']['removed']);
    }
}
