<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class FirstClassCallableDetectionTest extends TestCase
{
    public function testItAnalyzesFirstClassCallableSyntaxWithoutFailing(): void
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );

        $routes = array_values(array_filter(
            $analysis->routes(),
            static fn ($route): bool => $route->path() === '/first-class-callable',
        ));

        self::assertCount(1, $routes);
    }

    public function testItKeepsLocalMethodCallsMadeThroughFirstClassCallables(): void
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );

        $calls = array_map(
            static fn ($call): string => $call->method(),
            array_filter(
                $analysis->serviceCalls(),
                static fn ($call): bool => $call->source()
                    === 'App\\FirstClassCallable\\ReportNormalizer::normalize',
            ),
        );

        self::assertContains('isNotEmpty', $calls);
        self::assertContains('decorate', $calls);
    }

    public function testItKeepsInjectedServiceCallsMadeThroughFirstClassCallables(): void
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );

        $calls = array_map(
            static fn ($call): string => $call->method(),
            array_filter(
                $analysis->serviceCalls(),
                static fn ($call): bool => $call->source()
                    === 'App\\FirstClassCallable\\ReportController::run'
                    && $call->service() === 'App\\FirstClassCallable\\ReportNormalizer',
            ),
        );

        self::assertContains('normalize', $calls);
        self::assertContains('fetch', $calls);
    }
}
