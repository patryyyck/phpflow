<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class LocalMethodCallDetectionTest extends TestCase
{
    public function testItDetectsCallsToAnotherMethodOfTheSameClass(): void
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );

        $calls = array_values(array_filter(
            $analysis->serviceCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\LocalHttp\\DirectoryRegistrationClient::getRegistrationStatus'
                && $call->service() === 'App\\LocalHttp\\DirectoryRegistrationClient'
                && $call->method() === 'fetchAllResults',
        ));

        self::assertCount(1, $calls);
        self::assertSame(
            'App\\LocalHttp\\DirectoryRegistrationClient',
            $calls[0]->implementation(),
        );
    }
}
