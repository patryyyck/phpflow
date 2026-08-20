<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Console;

use PHPUnit\Framework\TestCase;

final class MakefileInspectTest extends TestCase
{
    public function testInspectTargetForwardsOptionalSummaryFlag(): void
    {
        $makefile = file_get_contents(__DIR__.'/../../Makefile');

        self::assertIsString($makefile);
        self::assertStringContainsString('SUMMARY ?=', $makefile);
        self::assertStringContainsString(
            '$(if $(SUMMARY),--summary,)',
            $makefile,
        );
    }
}
