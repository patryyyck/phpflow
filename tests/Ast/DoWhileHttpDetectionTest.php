<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class DoWhileHttpDetectionTest extends TestCase
{
    public function testDirectHttpCallInsideDoWhileKeepsItsResolvedUrl(): void
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );

        $calls = array_values(array_filter(
            $analysis->httpCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Http\\DoWhileHttpClient::search',
        ));

        self::assertCount(1, $calls);
        self::assertSame('POST', $calls[0]->method());
        self::assertSame(
            '%directory.base_url%/v2/directory/search',
            $calls[0]->url(),
        );
    }
}
