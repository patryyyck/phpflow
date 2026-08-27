<?php

declare(strict_types=1);

namespace PhpFlow\Exporter;

use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;

final readonly class JsonExporter
{
    public const string SCHEMA_VERSION = '1.1';

    public function __construct(
        private JsonNodeMetadata $metadata = new JsonNodeMetadata(),
    ) {
    }

    public function export(Graph $graph): string
    {
        $payload = [
            'schemaVersion' => self::SCHEMA_VERSION,
            'nodes' => array_map(
                fn (Node $node): array => [
                    'id' => $node->id(),
                    'type' => $node->type()->value,
                    'label' => $node->label(),
                    'metadata' => $this->metadata->for($graph, $node),
                ],
                $graph->nodes(),
            ),
            'edges' => array_map(
                static fn (Edge $edge): array => array_filter(
                    [
                        'source' => $edge->source(),
                        'target' => $edge->target(),
                        'type' => $edge->type()->value,
                        'label' => $edge->label(),
                        'order' => $edge->order(),
                        'context' => $edge->context() === [] ? null : $edge->context(),
                    ],
                    static fn (mixed $value): bool => $value !== null,
                ),
                $graph->edges(),
            ),
        ];

        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ).PHP_EOL;
    }
}
