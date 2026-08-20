<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class PartialHttpUrlDetectionTest extends TestCase
{
    public function testItPreservesStaticSuffixWhenBaseUrlIsDynamic(): void
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );

        $calls = array_values(array_filter(
            $analysis->httpCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Http\\PartialHttpUrlClient::unresolvedBase',
        ));

        self::assertCount(1, $calls);
        self::assertSame('POST', $calls[0]->method());
        self::assertSame(
            '{dynamic}/v2/directory/search',
            $calls[0]->url(),
        );
    }

    public function testItKeepsConfiguredBaseAndMarksOnlyUnknownSegments(): void
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );

        $calls = array_values(array_filter(
            $analysis->httpCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Http\\PartialHttpUrlClient::configuredBase',
        ));

        self::assertCount(1, $calls);
        self::assertSame(
            '%api.base_url%/v1/resources/{dynamic}',
            $calls[0]->url(),
        );
    }

    public function testItPreservesStaticFragmentsInInterpolatedStrings(): void
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );

        $calls = array_values(array_filter(
            $analysis->httpCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Http\\PartialHttpUrlClient::interpolated',
        ));

        self::assertCount(1, $calls);
        self::assertSame(
            '/v1/resources/{dynamic}/status',
            $calls[0]->url(),
        );
    }

    public function testItResolvesPartialUrlStoredInALocalVariable(): void
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );

        $calls = array_values(array_filter(
            $analysis->httpCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Http\\PartialHttpUrlClient::variableUrl',
        ));

        self::assertCount(1, $calls);
        self::assertSame(
            '/v1/resources/{dynamic}',
            $calls[0]->url(),
        );
    }
    public function testItResolvesClassStringConstantsInHttpUrls(): void
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );

        $calls = array_values(array_filter(
            $analysis->httpCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Http\\PartialHttpUrlClient::classConstantEndpoint',
        ));

        self::assertCount(1, $calls);
        self::assertSame(
            '%api.base_url%/oauth/token',
            $calls[0]->url(),
        );
    }


    public function testItResolvesSprintfUrlsStoredInLocalVariables(): void
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );

        $calls = array_values(array_filter(
            $analysis->httpCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Http\\PartialHttpUrlClient::sprintfUrl',
        ));

        self::assertCount(1, $calls);
        self::assertSame('POST', $calls[0]->method());
        self::assertSame(
            '%api.base_url%/v2/directory/search',
            $calls[0]->url(),
        );
    }


}
