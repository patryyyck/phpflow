<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Console;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\FindExceptionImpact;
use PhpFlow\Application\FindHttpImpact;
use PhpFlow\Application\FindMessageImpact;
use PhpFlow\Application\FindServiceImpact;
use PhpFlow\Application\FindTableImpact;
use PhpFlow\Application\ScanProject;
use PhpFlow\Console\Command\ImpactCommand;
use PhpFlow\Console\ImpactPathRenderer;
use PhpFlow\Infrastructure\Messenger\MessengerRoutingReader;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ImpactCommandTest extends TestCase
{
    public function testItRequiresExactlyOneImpactTarget(): void
    {
        $tester = new CommandTester($this->command());

        self::assertSame(
            Command::INVALID,
            $tester->execute([
                'path' => __DIR__.'/../Fixtures/SimpleProject',
            ]),
        );

        self::assertSame(
            Command::INVALID,
            $tester->execute([
                'path' => __DIR__.'/../Fixtures/SimpleProject',
                '--table' => 'companies',
                '--service' => 'CompanyService',
            ]),
        );
    }

    public function testOperationCanOnlyBeUsedWithTable(): void
    {
        $tester = new CommandTester($this->command());

        self::assertSame(
            Command::INVALID,
            $tester->execute([
                'path' => __DIR__.'/../Fixtures/SimpleProject',
                '--http' => '/v1/resources',
                '--operation' => 'SELECT',
            ]),
        );
    }

    public function testSummaryListsUniqueEntryPoints(): void
    {
        $tester = new CommandTester($this->command());

        self::assertSame(
            Command::SUCCESS,
            $tester->execute([
                'path' => __DIR__.'/../Fixtures/SimpleProject',
                '--table' => 'companies',
                '--summary' => true,
            ]),
        );

        $display = $tester->getDisplay();

        self::assertStringContainsString('Impact analysis: table companies', $display);
        self::assertStringNotContainsString('└──', $display);
    }

    private function command(): ImpactCommand
    {
        return new ImpactCommand(
            new ScanProject(new DirectoryScanner()),
            new AnalyzeProject(
                new \PhpFlow\Ast\ProjectAstAnalyzer(),
                new MessengerRoutingReader(),
            ),
            new BuildFlowGraph(),
            new FindTableImpact(),
            new FindHttpImpact(),
            new FindMessageImpact(),
            new FindServiceImpact(),
            new FindExceptionImpact(),
            new ImpactPathRenderer(),
        );
    }
}
