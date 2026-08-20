<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class BranchPathDetectionTest extends TestCase
{
    public function testItDistinguishesIfElseIfAndElseResponses(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $responses = array_values(array_filter(
            $analysis->httpResponses(),
            static fn ($response): bool =>
                $response->source() === 'App\\Controller\\BranchingController::branches',
        ));

        self::assertCount(3, $responses);
        self::assertSame("IF \$state === 'created'", $responses[0]->branch());
        self::assertSame("ELSEIF \$state === 'accepted'", $responses[1]->branch());
        self::assertSame('ELSE', $responses[2]->branch());
    }

    public function testItDetectsMatchArmBranchesForObjectReturns(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $returns = array_values(array_filter(
            $analysis->methodReturns(),
            static fn ($return): bool =>
                $return->source() === 'App\\Controller\\BranchingController::result',
        ));

        self::assertCount(3, $returns);
        self::assertSame("MATCH 'a'", $returns[0]->branch());
        self::assertSame("MATCH 'b', 'c'", $returns[1]->branch());
        self::assertSame('MATCH default', $returns[2]->branch());
    }
    public function testItCombinesOuterIfWithMatchArm(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $returns = array_values(array_filter(
            $analysis->methodReturns(),
            static fn ($return): bool =>
                $return->source() === 'App\\Controller\\BranchingController::nestedMatch'
                && $return->branch() !== null
                && str_contains($return->branch(), 'MATCH '),
        ));

        self::assertCount(2, $returns);
        self::assertSame("IF \$enabled / MATCH 'a'", $returns[0]->branch());
        self::assertSame('IF $enabled / MATCH default', $returns[1]->branch());
    }


}
