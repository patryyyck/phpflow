<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Console;

use PhpFlow\Application\CompareGraphExports;
use PhpFlow\Console\Command\DiffCommand;
use PhpFlow\Console\GraphDiffRenderer;
use PhpFlow\Exporter\GraphDiffJsonExporter;
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


    public function testInvalidFormatFailsBeforeReadingGraphFiles(): void
    {
        $tester = new CommandTester($this->command());

        self::assertSame(
            Command::INVALID,
            $tester->execute([
                'before' => '/missing/before.json',
                'after' => '/missing/after.json',
                '--format' => 'xml',
            ]),
        );

        self::assertStringContainsString('Format must be text or json.', $tester->getDisplay());
        self::assertStringNotContainsString('Unable to read graph JSON file', $tester->getDisplay());
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


    public function testItCanRenderDiffAsJson(): void
    {
        $before = $this->tempGraph([
            $this->node('route:GET:/old', 'route', 'GET /old'),
        ]);
        $after = $this->tempGraph([
            $this->node('route:GET:/new', 'route', 'GET /new'),
        ]);

        $tester = new CommandTester($this->command());

        self::assertSame(
            Command::SUCCESS,
            $tester->execute([
                'before' => $before,
                'after' => $after,
                '--format' => 'json',
            ]),
        );

        $data = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('1.0', $data['schemaVersion']);
        self::assertTrue($data['hasChanges']);
        self::assertSame('route:GET:/new', $data['nodes']['added'][0]['id']);
        self::assertSame('route:GET:/old', $data['nodes']['removed'][0]['id']);
    }

    public function testOutputRequiresJsonFormat(): void
    {
        $graph = $this->tempGraph([]);
        $tester = new CommandTester($this->command());

        self::assertSame(
            Command::INVALID,
            $tester->execute([
                'before' => $graph,
                'after' => $graph,
                '--output' => sys_get_temp_dir().'/phpflow-diff.json',
            ]),
        );

        self::assertStringContainsString('--output can only be used with --format=json.', $tester->getDisplay());
    }

    public function testItCanWriteJsonDiffToAFile(): void
    {
        $before = $this->tempGraph([]);
        $after = $this->tempGraph([
            $this->node('message:new', 'message', 'App\\Message\\NewMessage'),
        ]);
        $output = tempnam(sys_get_temp_dir(), 'phpflow-diff-output-');

        if ($output === false) {
            self::fail('Unable to create temporary output file.');
        }

        $this->files[] = $output;
        $tester = new CommandTester($this->command());

        self::assertSame(
            Command::SUCCESS,
            $tester->execute([
                'before' => $before,
                'after' => $after,
                '--format' => 'json',
                '--output' => $output,
            ]),
        );

        $data = json_decode((string) file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('1.0', $data['schemaVersion']);
        self::assertSame('message:new', $data['nodes']['added'][0]['id']);
    }

    private function command(): DiffCommand
    {
        return new DiffCommand(
            new CompareGraphExports(),
            new GraphDiffRenderer(),
            new GraphDiffJsonExporter(),
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
