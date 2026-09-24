<?php

declare(strict_types=1);

namespace App\ApiPlatform;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: 'catalogs/import',
            processor: ImportCatalogProcessor::class,
        ),
        new Get(
            uriTemplate: 'catalogs/{id}/summary',
            provider: CatalogSummaryProvider::class,
        ),
        new Delete(
            uriTemplate: 'catalogs/{id}',
            provider: CatalogSummaryProvider::class,
            processor: ImportCatalogProcessor::class,
        ),

        // Defaults: the path is derived and the state classes live in vendor/,
        // so neither can be proven from application source.
        new Get(),
        new GetCollection(),
    ],
)]
final class Catalog
{
}

#[Get(
    uriTemplate: 'catalog-feeds/{id}',
    provider: CatalogSummaryProvider::class,
)]
final class CatalogFeed
{
}

#[ApiResource(
    routePrefix: '/internal',
    operations: [
        new Get(
            uriTemplate: 'catalog-exports/{id}',
            provider: CatalogSummaryProvider::class,
        ),
    ],
)]
final class CatalogExport
{
}

#[ApiResource(
    operations: [
        // No uriTemplate: API Platform derives the path, but the target is named
        // in source, so the operation is still a provable entry point.
        new Post(
            processor: ImportCatalogProcessor::class,
        ),
        new Post(
            processor: PublishCatalogProcessor::class,
        ),

        // An explicit operation name is an identity PHPFlow can read as is.
        new Get(
            name: 'catalog_draft_preview',
            provider: CatalogSummaryProvider::class,
        ),
    ],
)]
final class CatalogDraft
{
}

// A uriTemplate declared on the resource is the path of every operation of
// that resource declaring none, as for subresources.
#[ApiResource(
    uriTemplate: 'catalogs/{catalogId}/entries',
    operations: [
        new GetCollection(
            provider: CatalogSummaryProvider::class,
        ),
        new Post(
            uriTemplate: 'catalogs/{catalogId}/entries/import',
            processor: ImportCatalogProcessor::class,
        ),
    ],
)]
#[ApiResource(
    routePrefix: '/internal',
    uriTemplate: 'catalog-entries/{id}',
    operations: [
        new Get(
            provider: CatalogSummaryProvider::class,
        ),
    ],
)]
final class CatalogEntry
{
}

final readonly class ImportCatalogProcessor
{
    public function process(): void
    {
    }
}

#[ApiResource(
    processor: ArchiveCatalogProcessor::class,
    provider: CatalogSummaryProvider::class,
    operations: [
        // A resource-level target is the default for every operation: the
        // processor runs on unsafe methods, the provider on anything but a POST.
        new Get(),
        new Post(),
        new Delete(),

        // What the operation declares itself wins over the resource default.
        new Put(
            processor: ImportCatalogProcessor::class,
        ),

        // A literal read: puts the resource provider back on a POST.
        new Post(
            uriTemplate: 'catalog-archives/replay',
            read: true,
        ),
    ],
)]
final class CatalogArchive
{
}

// No operations: the set API Platform would default to is not declared here,
// so only the class-level operation below is read, and it inherits the
// resource-level processor.
#[ApiResource(
    processor: ArchiveCatalogProcessor::class,
)]
#[Post(
    uriTemplate: 'catalog-imports/replay',
)]
final class CatalogImport
{
}

final readonly class ArchiveCatalogProcessor
{
    public function process(): void
    {
    }
}

final readonly class PublishCatalogProcessor
{
    public function process(): void
    {
    }
}

final readonly class CatalogSummaryProvider
{
    public function provide(): array
    {
        return [];
    }
}
