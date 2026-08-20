<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class LoopControlDetectionTest extends TestCase
{
    public function testItDetectsConditionalContinueBreakAndLevels(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $controls = array_values(array_filter(
            $analysis->loopControls(),
            static fn ($control): bool =>
                str_starts_with($control->source(), 'App\\Loops\\LoopEffectsHandler::'),
        ));

        self::assertCount(3, $controls);

        self::assertSame('CONTINUE LOOP', $controls[0]->operation());
        self::assertSame("IF \$item === 'skip'", $controls[0]->branch());
        self::assertSame(1, $controls[0]->level());

        self::assertSame('BREAK', $controls[1]->operation());
        self::assertSame("IF \$item === 'stop'", $controls[1]->branch());
        self::assertSame(1, $controls[1]->level());

        self::assertSame('BREAK', $controls[2]->operation());
        self::assertSame(2, $controls[2]->level());
        self::assertSame("IF \$item === 'stop-all'", $controls[2]->branch());
    }
}
