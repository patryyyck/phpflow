<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ApplicationEffectDetectionTest extends TestCase
{
    public function testItDetectsMailFilesystemAndCacheEffectsWithoutGenericDuplicates(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $effects = array_values(array_filter(
            $analysis->applicationEffects(),
            static fn ($effect): bool =>
                $effect->source() === 'App\\Effects\\ApplicationEffectsHandler::run',
        ));

        self::assertCount(3, $effects);

        self::assertSame('mail', $effects[0]->kind());
        self::assertSame('SEND EMAIL', $effects[0]->operation());

        self::assertSame('filesystem', $effects[1]->kind());
        self::assertSame('WRITE', $effects[1]->operation());
        self::assertSame('/tmp/export.csv', $effects[1]->target());

        self::assertSame('cache', $effects[2]->kind());
        self::assertSame('CACHE DELETE', $effects[2]->operation());
        self::assertSame('company.42', $effects[2]->target());

        $duplicates = array_values(array_filter(
            $analysis->serviceCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Effects\\ApplicationEffectsHandler::run',
        ));

        self::assertCount(0, $duplicates);
    }
}
