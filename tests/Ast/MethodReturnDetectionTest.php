<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class MethodReturnDetectionTest extends TestCase
{
    public function testItDetectsObjectReturnValuesWithoutAddingScalarNoise(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $returns = array_values(array_filter(
            $analysis->methodReturns(),
            static fn ($return): bool =>
                str_starts_with($return->source(), 'App\\ReturnFlow\\ReturnFlowHandler::'),
        ));

        self::assertCount(2, $returns);
        self::assertSame('App\\ReturnFlow\\ResultDto', $returns[0]->type());
        self::assertSame('App\\ReturnFlow\\ResultDto', $returns[1]->type());
        self::assertSame(
            [
                'App\\ReturnFlow\\ReturnFlowHandler::direct',
                'App\\ReturnFlow\\ReturnFlowHandler::variable',
            ],
            array_map(static fn ($return): string => $return->source(), $returns),
        );
    }
}
