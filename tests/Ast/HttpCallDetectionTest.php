<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class HttpCallDetectionTest extends TestCase
{
    public function testItDetectsStaticHttpClientCalls(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $calls = array_values(array_filter(
            $analysis->httpCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\MessageHandler\\ExternalApiHandler::__invoke',
        ));

        self::assertCount(1, $calls);
        self::assertSame('Symfony\\Contracts\\HttpClient\\HttpClientInterface', $calls[0]->client());
        self::assertSame('POST', $calls[0]->method());
        self::assertSame('https://api.example.test/users/delete', $calls[0]->url());
    }
}
