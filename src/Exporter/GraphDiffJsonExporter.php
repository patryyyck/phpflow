<?php

declare(strict_types=1);

namespace PhpFlow\Exporter;

use PhpFlow\Domain\Diff\GraphDiff;

final readonly class GraphDiffJsonExporter
{
    public const string SCHEMA_VERSION = '1.0';

    public function export(GraphDiff $diff): string
    {
        return json_encode(
            [
                'schemaVersion' => self::SCHEMA_VERSION,
                'hasChanges' => $diff->hasChanges(),
                'summary' => $diff->summary(),
                'nodes' => [
                    'added' => $diff->addedNodes(),
                    'removed' => $diff->removedNodes(),
                ],
                'edges' => [
                    'added' => $diff->addedEdges(),
                    'removed' => $diff->removedEdges(),
                ],
            ],
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR,
        ).PHP_EOL;
    }
}
