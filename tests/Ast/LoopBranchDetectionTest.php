<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class LoopBranchDetectionTest extends TestCase
{
    public function testItDetectsForeachForWhileAndDoWhileBranches(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $expectedSources = [
            'App\\Loops\\LoopEffectsHandler::foreachLoop',
            'App\\Loops\\LoopEffectsHandler::forLoop',
            'App\\Loops\\LoopEffectsHandler::whileLoop',
            'App\\Loops\\LoopEffectsHandler::doWhileLoop',
        ];

        $branches = array_values(array_filter(
            $analysis->controlBranches(),
            static fn ($branch): bool =>
                in_array($branch->source(), $expectedSources, true)
                && (
                    str_starts_with($branch->label(), 'FOREACH ')
                    || str_starts_with($branch->label(), 'FOR ')
                    || str_starts_with($branch->label(), 'WHILE ')
                    || str_starts_with($branch->label(), 'DO WHILE ')
                ),
        ));

        self::assertCount(4, $branches);

        $labels = array_map(
            static fn ($branch): string => $branch->label(),
            $branches,
        );

        self::assertContains('FOREACH $items as $key => $item', $labels);
        self::assertContains('FOR $i = 0; $i < 3; ++$i', $labels);
        self::assertContains('WHILE $running', $labels);
        self::assertContains('DO WHILE $running', $labels);

        foreach ($branches as $branch) {
            self::assertFalse($branch->effectOnly());
        }
    }
}
