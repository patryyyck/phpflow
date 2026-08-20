<?php
declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class RecursiveServiceCallTest extends TestCase
{
    public function testItResolvesInterfaceCallsAndAutowireServiceHttpCalls(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $calls = array_values(array_filter(
            $analysis->serviceCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Sync\\SyncServiceHandler::__invoke'
                && $call->method() === 'register',
        ));

        self::assertCount(1, $calls);
        self::assertSame('App\\Sync\\ExternalSyncClientInterface', $calls[0]->service());
        self::assertSame('App\\Sync\\ExternalSyncClient', $calls[0]->implementation());

        $http = array_values(array_filter(
            $analysis->httpCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\Sync\\ExternalSyncClient::register',
        ));

        self::assertCount(1, $http);
        self::assertSame('POST', $http[0]->method());
        self::assertSame('%sync.base_url%/v1/resources', $http[0]->url());
    }
}
