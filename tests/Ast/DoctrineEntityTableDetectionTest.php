<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class DoctrineEntityTableDetectionTest extends TestCase
{
    public function testItReadsALiteralTableName(): void
    {
        self::assertSame('library_book', $this->tables()['App\\Library\\Book']);
    }

    public function testItReadsDirectlyImportedMappingAttributes(): void
    {
        self::assertSame('library_author', $this->tables()['App\\Library\\Author']);
    }

    public function testSingleTableSubclassesResolveToTheRootTable(): void
    {
        $tables = $this->tables();

        self::assertSame('library_media', $tables['App\\Library\\Media']);
        self::assertSame('library_media', $tables['App\\Library\\Ebook']);
        self::assertSame('library_media', $tables['App\\Library\\Audiobook']);
        self::assertSame('library_media', $tables['App\\Library\\NarratedAudiobook']);
    }

    public function testJoinedSubclassesKeepTheirOwnTable(): void
    {
        $tables = $this->tables();

        self::assertSame('library_member', $tables['App\\Library\\Member']);
        self::assertSame('library_staff_member', $tables['App\\Library\\StaffMember']);
    }

    public function testAnEntityWithoutATableAttributeStaysUnresolved(): void
    {
        $tables = $this->tables();

        self::assertArrayHasKey('App\\Library\\ReadingList', $tables);
        self::assertNull($tables['App\\Library\\ReadingList']);
    }

    public function testATableAttributeWithoutANameStaysUnresolved(): void
    {
        $tables = $this->tables();

        self::assertArrayHasKey('App\\Library\\Loan', $tables);
        self::assertNull($tables['App\\Library\\Loan']);
    }

    public function testANonLiteralTableNameStaysUnresolved(): void
    {
        $tables = $this->tables();

        self::assertArrayHasKey('App\\Library\\Reservation', $tables);
        self::assertNull($tables['App\\Library\\Reservation']);
    }

    public function testATableAttributeAloneDoesNotMakeAnEntity(): void
    {
        self::assertArrayNotHasKey('App\\Library\\Shelf', $this->tables());
    }

    /**
     * @return array<string, ?string>
     */
    private function tables(): array
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );

        $tables = [];

        foreach ($analysis->doctrineEntities() as $entity) {
            $tables[$entity->class()] = $entity->table();
        }

        return $tables;
    }
}
