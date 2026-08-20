<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Messenger\MessengerRoutingReader;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class SymfonyServiceAliasResolutionTest extends TestCase
{
    public function testItResolvesServiceCallsUsingSymfonyAliases(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');

        $analysis = (new AnalyzeProject(
            new ProjectAstAnalyzer(),
            new MessengerRoutingReader(),
        ))->analyze($project);

        $calls = array_values(array_filter(
            $analysis->serviceCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Sync\\SyncServiceHandler::__invoke'
                && $call->service() === 'App\\Sync\\ExternalSyncClientInterface'
                && $call->method() === 'register',
        ));

        self::assertCount(1, $calls);
        self::assertSame(
            'App\\Sync\\ExternalSyncClient',
            $calls[0]->implementation(),
        );
    }
}
