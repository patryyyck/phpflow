<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class UnreachableCodeDetectionTest extends TestCase
{
    public function testEffectsAfterTerminatingStatementsAreFilteredOut(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $historicalMethods = [
            'App\\Unreachable\\UnreachableEffectsHandler::afterReturn',
            'App\\Unreachable\\UnreachableEffectsHandler::afterThrow',
            'App\\Unreachable\\UnreachableEffectsHandler::afterContinue',
            'App\\Unreachable\\UnreachableEffectsHandler::afterBreak',
        ];

        $calls = array_values(array_filter(
            $analysis->repositoryCalls(),
            static fn ($call): bool =>
                in_array($call->source(), $historicalMethods, true),
        ));

        $sources = array_map(
            static fn ($call): string => $call->source(),
            $calls,
        );

        self::assertSame(
            [
                'App\\Unreachable\\UnreachableEffectsHandler::afterThrow',
                'App\\Unreachable\\UnreachableEffectsHandler::afterBreak',
            ],
            $sources,
        );
    }

    public function testReachableReturnsAndThrowsRemainDetected(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $returns = array_values(array_filter(
            $analysis->methodReturns(),
            static fn ($return): bool =>
                $return->source() === 'App\\Unreachable\\UnreachableEffectsHandler::afterReturn',
        ));

        self::assertCount(1, $returns);

        $throws = array_values(array_filter(
            $analysis->thrownExceptions(),
            static fn ($throw): bool =>
                $throw->source() === 'App\\Unreachable\\UnreachableEffectsHandler::afterThrow',
        ));

        self::assertCount(1, $throws);
    }
    public function testCompleteIfElseMakesFollowingEffectsUnreachable(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $sources = array_map(
            static fn ($call): string => $call->source(),
            array_values(array_filter(
                $analysis->repositoryCalls(),
                static fn ($call): bool =>
                    str_starts_with(
                        $call->source(),
                        'App\\Unreachable\\UnreachableEffectsHandler::',
                    ),
            )),
        );

        self::assertNotContains(
            'App\\Unreachable\\UnreachableEffectsHandler::afterCompleteIfElse',
            $sources,
        );
        self::assertNotContains(
            'App\\Unreachable\\UnreachableEffectsHandler::afterCompleteElseIf',
            $sources,
        );
    }

    public function testPartialIfElseKeepsFollowingEffectsReachable(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $calls = array_values(array_filter(
            $analysis->repositoryCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Unreachable\\UnreachableEffectsHandler::afterPartialIfElse',
        ));

        self::assertCount(2, $calls);
    }


    public function testCompleteTryCatchMakesFollowingEffectsUnreachable(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $sources = array_map(
            static fn ($call): string => $call->source(),
            array_values(array_filter(
                $analysis->repositoryCalls(),
                static fn ($call): bool =>
                    str_starts_with(
                        $call->source(),
                        'App\\Unreachable\\UnreachableEffectsHandler::',
                    ),
            )),
        );

        self::assertNotContains(
            'App\\Unreachable\\UnreachableEffectsHandler::afterCompleteTryCatch',
            $sources,
        );
    }

    public function testPartialTryCatchKeepsFollowingEffectsReachable(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $calls = array_values(array_filter(
            $analysis->repositoryCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Unreachable\\UnreachableEffectsHandler::afterPartialTryCatch',
        ));

        self::assertCount(2, $calls);
    }

    public function testTerminatingFinallyMakesFollowingEffectsUnreachable(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $calls = array_values(array_filter(
            $analysis->repositoryCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Unreachable\\UnreachableEffectsHandler::afterTerminatingFinally',
        ));

        self::assertCount(1, $calls);
    }


    public function testExhaustiveTerminatingMatchMakesFollowingEffectsUnreachable(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $calls = array_values(array_filter(
            $analysis->repositoryCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Unreachable\\UnreachableEffectsHandler::afterTerminatingMatch',
        ));

        self::assertCount(0, $calls);
    }

    public function testPartialMatchKeepsFollowingEffectsReachable(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $calls = array_values(array_filter(
            $analysis->repositoryCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Unreachable\\UnreachableEffectsHandler::afterPartialMatch',
        ));

        self::assertCount(2, $calls);
    }

    public function testNonExhaustiveTerminatingMatchStaysConservative(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $calls = array_values(array_filter(
            $analysis->repositoryCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Unreachable\\UnreachableEffectsHandler::afterNonExhaustiveTerminatingMatch',
        ));

        self::assertCount(1, $calls);
    }


}
