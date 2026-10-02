<?php

declare(strict_types=1);

namespace App\ApiPlatform\Operation;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\HttpOperation;
use App\ApiPlatform\CatalogSummaryProvider;
use App\ApiPlatform\ImportCatalogProcessor;

// The provider is set by the class rather than at the call site.
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Export extends HttpOperation
{
    public function __construct(?string $uriTemplate = null, ?string $name = null, $provider = null)
    {
        parent::__construct(
            uriTemplate: $uriTemplate,
            name: $name,
            provider: $provider ?? ExportProvider::class,
        );
    }
}

// Intermediate class: sets the method and a processor fallback.
class ProcessingOperation extends HttpOperation
{
    public function __construct(?string $uriTemplate = null, $processor = null)
    {
        parent::__construct(
            method: 'POST',
            uriTemplate: $uriTemplate,
            processor: $processor ?? ImportCatalogProcessor::class,
        );
    }
}

// Walks the chain: the method comes from ProcessingOperation, the name from here.
final class ReplayOperation extends ProcessingOperation
{
    public function __construct(?string $uriTemplate = null)
    {
        parent::__construct(uriTemplate: $uriTemplate, processor: ImportCatalogProcessor::class);
    }
}

// A parameter default forwarded to the parent, and a literal method.
final class RebuildOperation extends HttpOperation
{
    public function __construct(?string $uriTemplate = null, string $processor = ImportCatalogProcessor::class)
    {
        parent::__construct(method: 'PUT', uriTemplate: $uriTemplate, processor: $processor);
    }
}

// Unresolved: positional arguments would need the vendor constructor signature.
final class PositionalOperation extends HttpOperation
{
    public function __construct(?string $uriTemplate = null)
    {
        parent::__construct('GET', $uriTemplate);
    }
}

// Unresolved: the method is computed, so inheritance rules cannot be applied.
final class ComputedMethodOperation extends HttpOperation
{
    public function __construct(?string $uriTemplate = null)
    {
        parent::__construct(method: strtoupper('get'), uriTemplate: $uriTemplate, provider: ExportProvider::class);
    }
}

// The operation is readable but its provider is computed: no target is proven.
final class ComputedProviderOperation extends HttpOperation
{
    public function __construct(?string $uriTemplate = null)
    {
        parent::__construct(uriTemplate: $uriTemplate, provider: self::pick());
    }

    private static function pick(): string
    {
        return ExportProvider::class;
    }
}

// A service id string is not a class reference, so no target is proven.
final class StringTargetOperation extends HttpOperation
{
    public function __construct(?string $uriTemplate = null)
    {
        parent::__construct(uriTemplate: $uriTemplate, provider: 'app.export_provider');
    }
}

// GraphQL stays out of scope.
final class GraphQlCatalogQuery extends Query
{
}

final readonly class ExportProvider
{
    public function provide(): array
    {
        return [];
    }
}

final readonly class LegacyExportProvider
{
    public function provide(): array
    {
        return [];
    }
}

#[ApiResource(
    operations: [
        new Export(uriTemplate: 'custom/catalogs/{id}/export'),
        new Export(uriTemplate: 'custom/catalogs/{id}/export-legacy', provider: LegacyExportProvider::class),
    ],
)]
final class CustomCatalogExport
{
}

#[Export(uriTemplate: 'custom/catalog-exports/{id}/download')]
final class CustomCatalogDownload
{
}

#[ApiResource(
    operations: [
        new ReplayOperation(uriTemplate: 'custom/catalogs/replay'),
        new RebuildOperation(uriTemplate: 'custom/catalogs/rebuild'),
    ],
)]
final class CustomCatalogReplay
{
}

// POST reads nothing by default, so the resource provider is not inherited.
#[ApiResource(
    provider: CatalogSummaryProvider::class,
    operations: [
        new ReplayOperation(uriTemplate: 'custom/catalogs/replay-default'),
    ],
)]
final class CustomCatalogDefaults
{
}

#[ApiResource(
    operations: [
        new PositionalOperation(uriTemplate: 'custom/catalogs/positional'),
        new ComputedMethodOperation(uriTemplate: 'custom/catalogs/computed-method'),
        new ComputedProviderOperation(uriTemplate: 'custom/catalogs/computed-provider'),
        new StringTargetOperation(uriTemplate: 'custom/catalogs/string-target'),
    ],
)]
final class CustomCatalogUnresolved
{
}

#[ApiResource(
    operations: [
        new GraphQlCatalogQuery(),
    ],
)]
final class CustomCatalogGraphQl
{
}
