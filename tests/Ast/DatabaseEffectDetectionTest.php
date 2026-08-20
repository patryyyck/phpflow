<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class DatabaseEffectDetectionTest extends TestCase
{
    public function testItDetectsDbalTableOperationsAndLiteralSql(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $effects = array_values(array_filter(
            $analysis->databaseEffects(),
            static fn ($effect): bool =>
                str_starts_with($effect->source(), 'App\\Repository\\DoctrineCompanyRepository::'),
        ));

        $selects = array_values(array_filter(
            $effects,
            static fn ($effect): bool => $effect->operation() === 'SELECT',
        ));
        $inserts = array_values(array_filter(
            $effects,
            static fn ($effect): bool => $effect->operation() === 'INSERT',
        ));
        $updates = array_values(array_filter(
            $effects,
            static fn ($effect): bool => $effect->operation() === 'UPDATE',
        ));

        self::assertGreaterThanOrEqual(4, count($selects));

        $selectTargets = array_map(
            static fn ($effect): ?string => $effect->target(),
            $selects,
        );

        self::assertContains('company', $selectTargets);
        self::assertContains('companies', $selectTargets);

        self::assertCount(1, $inserts);
        self::assertSame('company', $inserts[0]->target());
        self::assertNull($inserts[0]->sql());

        $literalUpdates = array_values(array_filter(
            $updates,
            static fn ($effect): bool =>
                $effect->sql() === 'UPDATE company SET status = :status WHERE id = :id',
        ));

        self::assertCount(1, $literalUpdates);
        self::assertSame('company', $literalUpdates[0]->target());
    }
    public function testRepositoryMethodNamedInsertIsNotMistakenForADatabaseEffect(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $falsePositives = array_values(array_filter(
            $analysis->databaseEffects(),
            static fn ($effect): bool =>
                str_contains($effect->source(), 'PersistCompanyHandler::__invoke')
                && $effect->operation() === 'INSERT',
        ));

        self::assertCount(0, $falsePositives);
    }


    public function testItResolvesSelectBuiltFromAHelperMethodAndConcatAssignment(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $effects = array_values(array_filter(
            $analysis->databaseEffects(),
            static fn ($effect): bool =>
                $effect->source() === 'App\\Repository\\DoctrineCompanyRepository::findRequiredFromBaseSelect',
        ));

        self::assertCount(1, $effects);
        self::assertSame('SELECT', $effects[0]->operation());
        self::assertSame('companies', $effects[0]->target());
    }


}
