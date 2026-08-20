<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ThrownExceptionDetectionTest extends TestCase
{
    public function testItDetectsExplicitNewExceptionsInSourceOrder(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $exceptions = array_values(array_filter(
            $analysis->thrownExceptions(),
            static fn ($exception): bool =>
                $exception->source() === 'App\\ExceptionFlow\\ExceptionFlowHandler::run',
        ));

        self::assertCount(2, $exceptions);
        self::assertSame('App\\ExceptionFlow\\DomainProblem', $exceptions[0]->exception());
        self::assertSame('IF !$valid', $exceptions[0]->condition());
        self::assertSame('App\\ExceptionFlow\\InvalidResult', $exceptions[1]->exception());
        self::assertSame('IF $items === []', $exceptions[1]->condition());
        self::assertLessThan(
            $exceptions[1]->position()?->filePosition(),
            $exceptions[0]->position()?->filePosition(),
        );
    }
    public function testItCombinesNestedIfConditionsWithoutInterpretingThem(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $exceptions = array_values(array_filter(
            $analysis->thrownExceptions(),
            static fn ($exception): bool =>
                $exception->source() === 'App\\ExceptionFlow\\ExceptionFlowHandler::nested',
        ));

        self::assertCount(1, $exceptions);
        self::assertSame('IF $valid / IF $enabled', $exceptions[0]->condition());
    }

}
