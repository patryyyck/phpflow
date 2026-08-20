<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ConditionalEffectBranchDetectionTest extends TestCase
{
    public function testItRecordsIfElseIfAndElseAsEffectOnlyControlBranches(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $branches = array_values(array_filter(
            $analysis->controlBranches(),
            static fn ($branch): bool =>
                $branch->source() === 'App\\ConditionalEffects\\ConditionalEffectsHandler::run',
        ));

        self::assertCount(3, $branches);
        self::assertSame(
            [
                "IF \$state === 'active'",
                "ELSEIF \$state === 'suspended'",
                'ELSE',
            ],
            array_map(static fn ($branch): string => $branch->label(), $branches),
        );

        foreach ($branches as $branch) {
            self::assertTrue($branch->effectOnly());
        }
    }
}
