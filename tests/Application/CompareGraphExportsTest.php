<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use InvalidArgumentException;
use PhpFlow\Application\CompareGraphExports;
use PHPUnit\Framework\TestCase;

final class CompareGraphExportsTest extends TestCase
{
    public function testItComparesNodesEdgesAndBusinessSummary(): void
    {
        $before = $this->graph(
            [
                $this->node('route:GET:/companies', 'route', 'GET /companies'),
                $this->node('service:sync', 'service', 'App\\CompanyService::sync'),
                $this->node('database:companies', 'database', 'SELECT companies'),
            ],
            [
                $this->edge('route:GET:/companies', 'service:sync', 'calls'),
                $this->edge('service:sync', 'database:companies', 'calls'),
            ],
        );

        $after = $this->graph(
            [
                $this->node('route:POST:/companies/sync', 'route', 'POST /companies/sync'),
                $this->node('service:sync', 'service', 'App\\CompanyService::sync'),
                $this->node('database:companies', 'database', 'UPDATE companies'),
                $this->node('http:directory', 'http_endpoint', 'POST %directory.base_url%/v2/sync'),
                $this->node('exception:sync', 'exception', 'throws App\\Exception\\SyncFailed'),
            ],
            [
                $this->edge('route:POST:/companies/sync', 'service:sync', 'calls'),
                $this->edge('service:sync', 'database:companies', 'calls'),
                $this->edge('service:sync', 'http:directory', 'calls'),
                $this->edge('service:sync', 'exception:sync', 'throws'),
            ],
        );

        $diff = (new CompareGraphExports())->compare($before, $after);

        self::assertTrue($diff->hasChanges());
        self::assertSame(
            [
                'routes' => ['added' => 1, 'removed' => 1],
                'databaseEffects' => ['added' => 1, 'removed' => 1],
                'externalHttp' => ['added' => 1, 'removed' => 0],
                'exceptions' => ['added' => 1, 'removed' => 0],
            ],
            $diff->summary(),
        );

        self::assertSame(
            [
                'database:companies',
                'exception:sync',
                'http:directory',
                'route:POST:/companies/sync',
            ],
            array_column($diff->addedNodes(), 'id'),
        );
        self::assertSame(
            ['database:companies', 'route:GET:/companies'],
            array_column($diff->removedNodes(), 'id'),
        );
        self::assertCount(3, $diff->addedEdges());
        self::assertCount(1, $diff->removedEdges());
    }

    public function testItTreatsIdenticalGraphsAsUnchanged(): void
    {
        $json = $this->graph(
            [$this->node('message:sync', 'message', 'App\\Message\\Sync')],
            [],
        );

        $diff = (new CompareGraphExports())->compare($json, $json);

        self::assertFalse($diff->hasChanges());
        self::assertSame([], $diff->addedNodes());
        self::assertSame([], $diff->removedNodes());
        self::assertSame([], $diff->addedEdges());
        self::assertSame([], $diff->removedEdges());
        self::assertSame([], $diff->summary());
    }

    public function testItRejectsInvalidGraphJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid before PHPFlow graph JSON');

        (new CompareGraphExports())->compare('{', $this->graph([], []));
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<array<string, mixed>> $edges
     */
    private function graph(array $nodes, array $edges): string
    {
        return json_encode(
            [
                'schemaVersion' => '1.2',
                'nodes' => $nodes,
                'edges' => $edges,
            ],
            JSON_THROW_ON_ERROR,
        );
    }

    /** @return array<string, mixed> */
    private function node(string $id, string $type, string $label): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'label' => $label,
            'displayLabel' => $label,
            'metadata' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function edge(string $source, string $target, string $type): array
    {
        return [
            'source' => $source,
            'target' => $target,
            'type' => $type,
        ];
    }
}
