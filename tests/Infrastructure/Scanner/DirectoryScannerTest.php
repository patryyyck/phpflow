<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Infrastructure\Scanner;

use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirectoryScanner::class)]
final class DirectoryScannerTest extends TestCase
{
    public function testItScansPhpFilesFromAProjectDirectory(): void
    {
        $scanner = new DirectoryScanner();

        $project = $scanner->scan(__DIR__.'/../../Fixtures/SimpleProject');

        self::assertSame(58, $project->sourceFileCount());
        self::assertCount(58, $project->sourceFiles());
        self::assertDirectoryExists($project->path());
    }

    public function testItRejectsAnUnknownDirectory(): void
    {
        $scanner = new DirectoryScanner();

        $this->expectException(\InvalidArgumentException::class);
        $scanner->scan(__DIR__.'/does-not-exist');
    }
}
