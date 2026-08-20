<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ExpressionBranchDetectionTest extends TestCase
{
    public function testItDetectsTernaryCoalesceAndShortCircuitBranches(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $branches = array_values(array_filter(
            $analysis->controlBranches(),
            static fn ($branch): bool =>
                str_starts_with($branch->source(), 'App\\ExpressionBranches\\ExpressionBranchHandler::'),
        ));

        $labels = array_map(
            static fn ($branch): string => $branch->label(),
            $branches,
        );

        self::assertContains('TERNARY $exists THEN', $labels);
        self::assertContains('TERNARY $exists ELSE', $labels);
        self::assertContains('COALESCE $result IS NULL', $labels);
        self::assertContains('IF $enabled', $labels);
        self::assertContains('IF NOT ($disabled)', $labels);

        foreach ($branches as $branch) {
            self::assertTrue($branch->effectOnly());
        }
    }
}
