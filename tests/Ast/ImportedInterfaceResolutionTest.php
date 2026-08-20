<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectIndexer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ImportedInterfaceResolutionTest extends TestCase
{
    public function testItResolvesImportedImplementedInterfacesAcrossNamespaces(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $index = (new ProjectIndexer())->index($project);

        self::assertSame(
            ['App\\ImportedResolution\\Infra\\ConcreteExternalClient'],
            $index->implementationsOf(
                'App\\ImportedResolution\\Domain\\ExternalClientInterface',
            ),
        );

        self::assertSame(
            'App\\ImportedResolution\\Infra\\ConcreteExternalClient',
            $index->uniqueImplementationOf(
                'App\\ImportedResolution\\Domain\\ExternalClientInterface',
            ),
        );
    }
}
