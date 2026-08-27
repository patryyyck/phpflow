<?php

declare(strict_types=1);

namespace PhpFlow\Exporter;

use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Impact\ImpactPath;

final readonly class ImpactJsonExporter
{
    public const string SCHEMA_VERSION = '1.0';

    public function __construct(
        private JsonNodeMetadata $metadata = new JsonNodeMetadata(),
    ) {
    }

    /**
     * @param list<ImpactPath> $paths
     */
    public function export(
        Graph $graph,
        string $type,
        string $query,
        array $paths,
        ?string $operation = null,
    ): string {
        $nodes = [];
        $entryPoints = [];
        $serializedPaths = [];

        foreach ($paths as $path) {
            $ids = [];

            foreach ($path->nodes() as $node) {
                $ids[] = $node->id();
                $nodes[$node->id()] ??= $this->node($graph, $node);
            }

            $root = $path->root();
            $entryPoints[$root->id()] ??= [
                'id' => $root->id(),
                'type' => $root->type()->value,
                'label' => $root->label(),
            ];

            $serializedPaths[] = [
                'entryPoint' => $root->id(),
                'effect' => $path->effect()->id(),
                'nodes' => $ids,
            ];
        }

        $target = [
            'type' => $type,
            'query' => $query,
        ];

        if ($operation !== null) {
            $target['operation'] = $operation;
        }

        return json_encode(
            [
                'schemaVersion' => self::SCHEMA_VERSION,
                'target' => $target,
                'entryPoints' => array_values($entryPoints),
                'nodes' => array_values($nodes),
                'paths' => $serializedPaths,
            ],
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR,
        ).PHP_EOL;
    }

    /** @return array<string, mixed> */
    private function node(Graph $graph, Node $node): array
    {
        return [
            'id' => $node->id(),
            'type' => $node->type()->value,
            'label' => $node->label(),
            'displayLabel' => $this->metadata->displayLabel($node),
            'metadata' => $this->metadata->for($graph, $node),
        ];
    }
}
