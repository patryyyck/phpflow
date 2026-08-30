<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Examples;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class SymfonyDemoTest extends TestCase
{
    public function testBundledSymfonyDemoExercisesMajorV01AnalysisFamilies(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../../examples/symfony-demo');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        self::assertNotEmpty($analysis->routes());
        self::assertNotEmpty($analysis->messageDispatches());
        self::assertNotEmpty($analysis->messageHandlers());
        self::assertNotEmpty($analysis->serviceCalls());
        self::assertNotEmpty($analysis->httpCalls());
        self::assertNotEmpty($analysis->thrownExceptions());

        $databaseOperations = array_values(array_unique(array_map(
            static fn ($effect): string => $effect->operation(),
            $analysis->databaseEffects(),
        )));

        self::assertContains('SELECT', $databaseOperations);
        self::assertContains('INSERT', $databaseOperations);
        self::assertContains('UPDATE', $databaseOperations);
        self::assertContains('DELETE', $databaseOperations);

        $applicationEffectKinds = array_values(array_unique(array_map(
            static fn ($effect): string => $effect->kind(),
            $analysis->applicationEffects(),
        )));

        self::assertContains('mail', $applicationEffectKinds);
        self::assertContains('filesystem', $applicationEffectKinds);
        self::assertContains('cache', $applicationEffectKinds);
    }
}
