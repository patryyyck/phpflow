<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Messenger\MessengerRoutingReader;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class AnalyzeProjectTest extends TestCase
{
    public function testItResolvesRoutingDeclaredOnAnInterfaceForConcreteDispatchedMessages(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');

        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        $resolved = [];
        foreach ($analysis->resolvedMessageRoutings() as $routing) {
            $resolved[$routing->message()] = $routing->transports();
        }

        self::assertSame(
            ['async', 'webhook_event'],
            $resolved['App\\Event\\UserCreated'] ?? null,
        );
    }
}
