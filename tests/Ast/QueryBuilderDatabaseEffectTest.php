<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class QueryBuilderDatabaseEffectTest extends TestCase
{
    public function testItDetectsUpdateAndSelectQueryBuilderEffects(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $effects = array_values(array_filter(
            $analysis->databaseEffects(),
            static fn ($effect): bool =>
                str_starts_with(
                    $effect->source(),
                    'App\\Repository\\DoctrineCompanyRepository::',
                ),
        ));

        $update = array_values(array_filter(
            $effects,
            static fn ($effect): bool =>
                $effect->source() === 'App\\Repository\\DoctrineCompanyRepository::markDuplicateWithQueryBuilder',
        ));

        self::assertCount(1, $update);
        self::assertSame('UPDATE', $update[0]->operation());
        self::assertSame('companies', $update[0]->target());

        $select = array_values(array_filter(
            $effects,
            static fn ($effect): bool =>
                $effect->source() === 'App\\Repository\\DoctrineCompanyRepository::listWithQueryBuilder',
        ));

        self::assertCount(1, $select);
        self::assertSame('SELECT', $select[0]->operation());
        self::assertSame('companies', $select[0]->target());

        $delete = array_values(array_filter(
            $effects,
            static fn ($effect): bool =>
                $effect->source() === 'App\\Repository\\DoctrineCompanyRepository::deleteWithInlineQueryBuilder',
        ));

        self::assertCount(1, $delete);
        self::assertSame('DELETE', $delete[0]->operation());
        self::assertSame('companies', $delete[0]->target());
    }
}
