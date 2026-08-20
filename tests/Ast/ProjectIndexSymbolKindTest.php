<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectIndexer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ProjectIndexSymbolKindTest extends TestCase
{
    public function testItDistinguishesConcreteClassesFromInterfaces(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $index = (new ProjectIndexer())->index($project);

        self::assertTrue(
            $index->isInterface('App\\Sync\\ExternalSyncClientInterface'),
        );
        self::assertFalse(
            $index->isInterface('App\\ServiceCycle\\CyclicServiceA'),
        );
    }
    public function testItFindsTheConcreteImplementationOfAnInterface(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $index = (new ProjectIndexer())->index($project);

        self::assertSame(
            [
                'App\\Sync\\ExternalSyncClient',
                'App\\Tests\\MockExternalSyncClient',
            ],
            $index->implementationsOf('App\\Sync\\ExternalSyncClientInterface'),
        );

        self::assertTrue($index->isTestSymbol('App\\Tests\\MockExternalSyncClient'));
        self::assertFalse($index->isTestSymbol('App\\Sync\\ExternalSyncClient'));

        self::assertSame(
            'App\\Sync\\ExternalSyncClient',
            $index->uniqueImplementationOf('App\\Sync\\ExternalSyncClientInterface'),
        );
    }


    public function testItPrefersANonTestRepositoryImplementation(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $index = (new ProjectIndexer())->index($project);

        self::assertSame(
            [
                'App\\Repository\\DoctrineCompanyRepository',
                'App\\Tests\\MockCompanyRepository',
            ],
            $index->implementationsOf('App\\Repository\\CompanyRepository'),
        );

        self::assertSame(
            'App\\Repository\\DoctrineCompanyRepository',
            $index->uniqueImplementationOf('App\\Repository\\CompanyRepository'),
        );
    }


}
