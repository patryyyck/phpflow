<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ServiceCallFilteringTest extends TestCase
{
    public function testMessengerDispatchIsNotDuplicatedAsAGenericServiceCall(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $duplicates = array_values(array_filter(
            $analysis->serviceCalls(),
            static fn ($call): bool =>
                str_ends_with($call->service(), 'MessageBusInterface')
                && $call->method() === 'dispatch',
        ));

        self::assertCount(0, $duplicates);
    }
}
