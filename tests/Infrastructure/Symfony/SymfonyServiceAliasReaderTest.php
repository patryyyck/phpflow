<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Infrastructure\Symfony;

use PhpFlow\Infrastructure\Symfony\SymfonyServiceAliasReader;
use PHPUnit\Framework\TestCase;

final class SymfonyServiceAliasReaderTest extends TestCase
{
    public function testItReadsAliasesFromPhpServiceConfiguration(): void
    {
        $aliases = (new SymfonyServiceAliasReader())->read(
            __DIR__.'/../../Fixtures/SimpleProject',
        );

        self::assertSame(
            'App\\Sync\\ExternalSyncClient',
            $aliases['App\\Sync\\ExternalSyncClientInterface'] ?? null,
        );

        self::assertSame(
            'App\\ServiceCycle\\CyclicServiceB',
            $aliases['App\\ServiceCycle\\CyclicServiceA'] ?? null,
        );
    }
}
