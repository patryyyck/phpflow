<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class GuardClauseDetectionTest extends TestCase
{
    public function testItDetectsTopLevelEarlyReturnGuardClause(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $guards = array_values(array_filter(
            $analysis->guardClauses(),
            static fn ($guard): bool =>
                $guard->source() === 'App\\Controller\\EarlyReturnController::run',
        ));

        self::assertCount(1, $guards);
        self::assertSame('!$valid', $guards[0]->condition());
        self::assertGreaterThan(0, $guards[0]->continuesAfter());
    }
}
