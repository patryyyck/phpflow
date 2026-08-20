<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class TryCatchBranchDetectionTest extends TestCase
{
    public function testItDetectsTryCatchAndFinallySourceRanges(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $branches = array_values(array_filter(
            $analysis->controlBranches(),
            static fn ($branch): bool =>
                $branch->source() === 'App\\Controller\\TryCatchController::run'
                && !$branch->effectOnly(),
        ));

        self::assertCount(4, $branches);
        self::assertSame(
            [
                'TRY',
                'CATCH App\\Controller\\TryCatchProblem',
                'CATCH Throwable',
                'FINALLY',
            ],
            array_map(static fn ($branch): string => $branch->label(), $branches),
        );

        foreach ($branches as $branch) {
            self::assertLessThan($branch->endFilePos(), $branch->startFilePos());
        }
    }
}
