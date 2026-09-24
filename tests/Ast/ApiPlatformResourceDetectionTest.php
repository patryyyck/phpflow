<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Domain\Analysis\SymfonyRoute;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ApiPlatformResourceDetectionTest extends TestCase
{
    public function testItTurnsResourceOperationsIntoEntryPoints(): void
    {
        self::assertSame(
            ['POST' => ['App\\ApiPlatform\\ImportCatalogProcessor::process']],
            $this->entryPointsFor('/catalogs/import'),
        );

        self::assertSame(
            ['GET' => ['App\\ApiPlatform\\CatalogSummaryProvider::provide']],
            $this->entryPointsFor('/catalogs/{id}/summary'),
        );
    }

    public function testAnOperationNamingBothAProviderAndAProcessorYieldsBoth(): void
    {
        self::assertSame(
            [
                'DELETE' => [
                    'App\\ApiPlatform\\CatalogSummaryProvider::provide',
                    'App\\ApiPlatform\\ImportCatalogProcessor::process',
                ],
            ],
            $this->entryPointsFor('/catalogs/{id}'),
        );
    }

    public function testItReadsClassLevelOperationAttributes(): void
    {
        self::assertSame(
            ['GET' => ['App\\ApiPlatform\\CatalogSummaryProvider::provide']],
            $this->entryPointsFor('/catalog-feeds/{id}'),
        );
    }

    public function testItPrefixesOperationPathsWithTheResourceRoutePrefix(): void
    {
        self::assertSame(
            ['GET' => ['App\\ApiPlatform\\CatalogSummaryProvider::provide']],
            $this->entryPointsFor('/internal/catalog-exports/{id}'),
        );
    }

    public function testAnOperationWithoutAUriTemplateIsStillAnEntryPoint(): void
    {
        self::assertSame(
            ['POST' => ['App\\ApiPlatform\\ImportCatalogProcessor::process']],
            $this->entryPointsForName('api_platform.App\\ApiPlatform\\CatalogDraft.post'),
        );
    }

    public function testRepeatedOperationsOnOneResourceGetDistinctIdentities(): void
    {
        self::assertSame(
            ['POST' => ['App\\ApiPlatform\\PublishCatalogProcessor::process']],
            $this->entryPointsForName('api_platform.App\\ApiPlatform\\CatalogDraft.post#2'),
        );
    }

    public function testAnExplicitOperationNameIsUsedAsTheIdentity(): void
    {
        self::assertSame(
            ['GET' => ['App\\ApiPlatform\\CatalogSummaryProvider::provide']],
            $this->entryPointsForName('catalog_draft_preview'),
        );
    }

    public function testAnOperationWithoutAUriTemplateExposesNoPath(): void
    {
        $paths = [];

        foreach ($this->analyze() as $route) {
            if (str_starts_with((string) $route->name(), 'api_platform.App\\ApiPlatform\\CatalogDraft.')) {
                $paths[] = $route->path();
            }
        }

        // The path API Platform derives from the resource short name is not
        // reconstructed, so the operation carries its identity and no path.
        self::assertSame([null, null], $paths);
    }

    public function testOperationsInheritTheResourceLevelTargets(): void
    {
        self::assertSame(
            ['GET' => ['App\\ApiPlatform\\CatalogSummaryProvider::provide']],
            $this->entryPointsForName('api_platform.App\\ApiPlatform\\CatalogArchive.get'),
        );

        self::assertSame(
            [
                'DELETE' => [
                    'App\\ApiPlatform\\ArchiveCatalogProcessor::process',
                    'App\\ApiPlatform\\CatalogSummaryProvider::provide',
                ],
            ],
            $this->entryPointsForName('api_platform.App\\ApiPlatform\\CatalogArchive.delete'),
        );
    }

    public function testAnInheritedTargetIsSkippedWhereApiPlatformWouldNotRunIt(): void
    {
        // A POST reads nothing by default, so the resource provider is not
        // inherited, while the resource processor is.
        self::assertSame(
            ['POST' => ['App\\ApiPlatform\\ArchiveCatalogProcessor::process']],
            $this->entryPointsForName('api_platform.App\\ApiPlatform\\CatalogArchive.post'),
        );
    }

    public function testALiteralReadFlagRestoresAnInheritedProvider(): void
    {
        self::assertSame(
            [
                'POST' => [
                    'App\\ApiPlatform\\ArchiveCatalogProcessor::process',
                    'App\\ApiPlatform\\CatalogSummaryProvider::provide',
                ],
            ],
            $this->entryPointsFor('/catalog-archives/replay'),
        );
    }

    public function testAnOperationTargetWinsOverTheResourceDefault(): void
    {
        self::assertSame(
            [
                'PUT' => [
                    'App\\ApiPlatform\\CatalogSummaryProvider::provide',
                    'App\\ApiPlatform\\ImportCatalogProcessor::process',
                ],
            ],
            $this->entryPointsForName('api_platform.App\\ApiPlatform\\CatalogArchive.put'),
        );
    }

    public function testAClassLevelOperationInheritsTheResourceTarget(): void
    {
        self::assertSame(
            ['POST' => ['App\\ApiPlatform\\ArchiveCatalogProcessor::process']],
            $this->entryPointsFor('/catalog-imports/replay'),
        );
    }

    public function testAnOperationWithoutAUriTemplateUsesTheResourceOne(): void
    {
        self::assertSame(
            ['GET' => ['App\\ApiPlatform\\CatalogSummaryProvider::provide']],
            $this->entryPointsFor('/catalogs/{catalogId}/entries'),
        );
    }

    public function testAnOperationUriTemplateWinsOverTheResourceOne(): void
    {
        self::assertSame(
            ['POST' => ['App\\ApiPlatform\\ImportCatalogProcessor::process']],
            $this->entryPointsFor('/catalogs/{catalogId}/entries/import'),
        );
    }

    public function testEachResourceOfAClassAppliesItsOwnUriTemplateAndPrefix(): void
    {
        self::assertSame(
            ['GET' => ['App\\ApiPlatform\\CatalogSummaryProvider::provide']],
            $this->entryPointsFor('/internal/catalog-entries/{id}'),
        );
    }

    public function testItSkipsOperationsWithoutAProvableTarget(): void
    {
        $resourceRoutes = array_filter(
            $this->analyze(),
            static fn (SymfonyRoute $route): bool => str_contains(
                $route->controller(),
                'App\\ApiPlatform\\',
            ),
        );

        // The fixture declares nineteen operations. The two relying on API Platform
        // defaults for their target contribute nothing, and four name or inherit
        // two targets at once, so twenty-one entry points remain.
        self::assertCount(21, $resourceRoutes);
    }

    /**
     * @return array<string, list<string>>
     */
    private function entryPointsFor(string $path): array
    {
        $entryPoints = [];

        foreach ($this->analyze() as $route) {
            if ($route->path() !== $path) {
                continue;
            }

            $entryPoints[implode('|', $route->methods())][] = $route->controller();
        }

        foreach ($entryPoints as &$controllers) {
            sort($controllers);
        }

        return $entryPoints;
    }

    /**
     * @return array<string, list<string>>
     */
    private function entryPointsForName(string $name): array
    {
        $entryPoints = [];

        foreach ($this->analyze() as $route) {
            if ($route->name() !== $name) {
                continue;
            }

            $entryPoints[implode('|', $route->methods())][] = $route->controller();
        }

        foreach ($entryPoints as &$controllers) {
            sort($controllers);
        }

        return $entryPoints;
    }

    /**
     * @return list<SymfonyRoute>
     */
    private function analyze(): array
    {
        return (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        )->routes();
    }
}
