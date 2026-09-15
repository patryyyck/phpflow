<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class AttributeArgumentResolutionTest extends TestCase
{
    public function testItResolvesClassNamesUsedInsideAttributeArguments(): void
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );

        $bindings = array_values(array_filter(
            $analysis->attributes(),
            static fn ($attribute): bool => $attribute->name()
                === 'App\\AttributeArguments\\ResourceBinding',
        ));

        self::assertCount(1, $bindings);
        self::assertSame(
            [
                '\\App\\Repository\\CompanyRepository::class',
                '\\App\\Sync\\ExternalSyncClient::class',
            ],
            $bindings[0]->arguments(),
        );
    }
}
