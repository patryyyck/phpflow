<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Console;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Application\ScanProject;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Console\Command\ScanCommand;
use PhpFlow\Infrastructure\Messenger\MessengerRoutingReader;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ScanCommandTest extends TestCase
{
    public function testItReportsApiPlatformCoverage(): void
    {
        $tester = new CommandTester(new ScanCommand(
            new ScanProject(new DirectoryScanner()),
            new AnalyzeProject(new ProjectAstAnalyzer(), new MessengerRoutingReader()),
        ));

        self::assertSame(
            Command::SUCCESS,
            $tester->execute(['path' => __DIR__.'/../Fixtures/SimpleProject']),
        );

        $output = preg_replace('/\s+/', ' ', $tester->getDisplay());

        self::assertStringContainsString('Resources 10', $output);
        self::assertStringContainsString('Operations 20', $output);
        self::assertStringContainsString('With a provable target 15', $output);
        self::assertStringContainsString(
            'Without a provable target 3 (not represented: framework defaults or unresolved targets)',
            $output,
        );
        self::assertStringContainsString('Unrecognized operation class 2 (not represented)', $output);
        self::assertStringContainsString(
            'Resources without operations argument 1 (not represented: default operation set)',
            $output,
        );
    }
}
