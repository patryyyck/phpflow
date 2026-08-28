<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Console;

use PhpFlow\Application\CompareGraphExports;
use PhpFlow\Console\Command\DiffCommand;
use PhpFlow\Console\GraphDiffRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DiffCommandTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function testItComparesTwoGraphFiles(): void
    {
        $before = $this->tempGraph([
            $this->node('route:GET:/companies', 'route', 'GET /companies'),
        ]);
        $after = $this->tempGraph([
            $this->node('route:POST:/companies', 'route', 'POST /companies'),
            $this->node('http:sync', 'http_endpoint', 'POST /v2/sync'),
        ]);

        $tester = new CommandTester($this->command());

        self::assertSame(
            Command::SUCCESS,
            $tester->execute([
                'before' => $before,
                'after' => $after,
            ]),
        );

        $display = $tester->getDisplay();

        self::assertStringContainsString('Graph changes', $display);
        self::assertStringContainsString('Routes', $display);
        self::assertStringContainsString('External Http', $display);
        self::assertStringContainsString('+ [route] POST /companies', $display);
        self::assertStringContainsString('- [route] GET /companies', $display);
    }

    public function testItReportsIdenticalGraphs(): void
    {
        $graph = $this->tempGraph([
            $this->node('message:sync', 'message', 'App\\Message\\Sync'),
        ]);

        $tester = new CommandTester($this->command());

        self::assertSame(
            Command::SUCCESS,
            $tester->execute([
                'before' => $graph,
                'after' => $graph,
            ]),
        );

        self::assertStringContainsString('No graph changes detected.', $tester->getDisplay());
    }

    public function testItFailsWhenAFileCannotBeRead(): void
    {
        $graph = $this->tempGraph([]);

        $tester = new CommandTester($this->command());

        self::assertSame(
            Command::FAILURE,
            $tester->execute([
                'before' => '/definitely/missing/phpflow.json',
                'after' => $graph,
            ]),
        );

        self::assertStringContainsString('Unable to read graph JSON file', $tester->getDisplay());
    }

    public function testItFailsForInvalidGraphJson(): void
    {
        $before = $this->tempFile('{');
        $after = $this->tempGraph([]);

        $tester = new CommandTester($this->command());

        self::assertSame(
            Command::FAILURE,
            $tester->execute([
                'before' => $before,
                'after' => $after,
            ]),
        );

        self::assertStringContainsString('Invalid before PHPFlow graph JSON', $tester->getDisplay());
    }

    private function command(): DiffCommand
    {
        return new DiffCommand(
            new CompareGraphExports(),
            new GraphDiffRenderer(),
        );
    }

    /** @param list<array<string, mixed>> $nodes */
    private function tempGraph(array $nodes): string
    {
        return $this->tempFile(json_encode(
            [
                'schemaVersion' => '1.2',
                'nodes' => $nodes,
                'edges' => [],
            ],
            JSON_THROW_ON_ERROR,
        ));
    }

    private function tempFile(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'phpflow-diff-');

        if ($file === false) {
            self::fail('Unable to create temporary graph file.');
        }

        file_put_contents($file, $contents);
        $this->files[] = $file;

        return $file;
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
}
