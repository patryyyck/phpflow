<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Console;

use InvalidArgumentException;
use PhpFlow\Console\ExportRouteOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExportRouteOptionsTest extends TestCase
{
    public function testItNormalizesRouteAndMethod(): void
    {
        $options = ExportRouteOptions::from(' /companies ', ' post ', '12');

        self::assertSame('/companies', $options->route());
        self::assertSame('POST', $options->method());
        self::assertSame(12, $options->maxDepth());
        self::assertSame('route:POST:/companies', $options->startNodeId());
    }

    public function testEmptyRouteMeansFullGraphExport(): void
    {
        $options = ExportRouteOptions::from('  ', 'GET', '10');

        self::assertNull($options->route());
        self::assertNull($options->startNodeId());
    }

    #[DataProvider('invalidDepthProvider')]
    public function testItRejectsInvalidMaxDepth(mixed $depth): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--max-depth must be a positive integer.');

        ExportRouteOptions::from('/companies', 'GET', $depth);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidDepthProvider(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'decimal' => ['1.5'];
        yield 'text' => ['abc'];
        yield 'empty' => [''];
        yield 'null' => [null];
    }

    public function testItRejectsEmptyHttpMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HTTP method must be a non-empty string.');

        ExportRouteOptions::from('/companies', ' ', '10');
    }
}
