<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class SourcePositionDetectionTest extends TestCase
{
    public function testDetectedCallsCarrySourcePositions(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $groups = [
            $analysis->repositoryCalls(),
            $analysis->serviceCalls(),
            $analysis->httpCalls(),
            $analysis->messageDispatches(),
        ];

        foreach ($groups as $calls) {
            self::assertNotEmpty($calls);

            foreach ($calls as $call) {
                self::assertNotNull($call->position());
                self::assertGreaterThanOrEqual(1, $call->position()->line());
                self::assertGreaterThanOrEqual(0, $call->position()->filePosition());
            }
        }
    }
}
