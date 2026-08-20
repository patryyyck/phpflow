<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class RepositoryCallDetectionTest extends TestCase
{
    public function testItDetectsCallsToInjectedRepositories(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $calls = array_values(array_filter(
            $analysis->repositoryCalls(),
            static fn ($call): bool =>
                $call->source() === 'App\\MessageHandler\\PersistCompanyHandler::__invoke',
        ));

        self::assertCount(1, $calls);
        self::assertSame('App\\Repository\\CompanyRepository', $calls[0]->repository());
        self::assertSame('save', $calls[0]->method());
    }
}
