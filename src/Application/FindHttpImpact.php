<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Domain\Impact\ImpactPath;

final readonly class FindHttpImpact
{
    /** @return list<ImpactPath> */
    public function find(Graph $graph, string $query): array
    {
        $query = strtolower(trim($query));

        if ($query === '') {
            return [];
        }

        $paths = [];

        foreach ($graph->nodes() as $node) {
            if ($node->type() !== NodeType::ROUTE) {
                continue;
            }

            foreach ($this->walk(
                $graph,
                $node,
                [],
                [],
                [$node],
            ) as $path) {
                if (
                    $path->effect()->type() === NodeType::HTTP_ENDPOINT
                    && str_contains(
                        strtolower($path->effect()->label()),
                        $query,
                    )
                ) {
                    $paths[] = $path;
                }
            }
        }

        usort(
            $paths,
            static fn (ImpactPath $left, ImpactPath $right): int =>
                [$left->root()->label(), $left->effect()->label()]
                <=> [$right->root()->label(), $right->effect()->label()],
        );

        return $paths;
    }

    /**
     * @param array<string, true> $visited
     * @param array<string, string> $context
     * @param list<Node> $nodes
     * @return list<ImpactPath>
     */
    private function walk(
        Graph $graph,
        Node $node,
        array $visited,
        array $context,
        array $nodes,
    ): array {
        if (isset($visited[$node->id()])) {
            return [];
        }

        $visited[$node->id()] = true;

        if ($node->type() === NodeType::HTTP_ENDPOINT) {
            return [new ImpactPath($nodes)];
        }

        $paths = [];

        foreach ($graph->outgoingEdges($node->id()) as $edge) {
            $child = $graph->node($edge->target());

            if ($child === null) {
                continue;
            }

            $childContext = $this->mergeContext(
                $context,
                $edge->context(),
            );

            $resolvedChild = $this->withContext(
                $child,
                $childContext,
            );

            $childNodes = $nodes;
            $childNodes[] = $resolvedChild;

            foreach ($this->walk(
                $graph,
                $child,
                $visited,
                $childContext,
                $childNodes,
            ) as $path) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @param array<string, string> $current
     * @param array<string, string> $incoming
     * @return array<string, string>
     */
    private function mergeContext(array $current, array $incoming): array
    {
        $merged = $current;

        foreach ($incoming as $name => $value) {
            $merged[$name] = $this->resolveContextValue(
                $value,
                $merged,
            );
        }

        return $merged;
    }

    /**
     * @param array<string, string> $context
     */
    private function withContext(Node $node, array $context): Node
    {
        if ($context === [] || !str_contains($node->label(), '{param:')) {
            return $node;
        }

        return new Node(
            $node->id(),
            $node->type(),
            $this->resolveContextValue($node->label(), $context),
        );
    }

    /**
     * @param array<string, string> $context
     */
    private function resolveContextValue(
        string $value,
        array $context,
    ): string {
        $resolved = preg_replace_callback(
            '/\{param:([A-Za-z_][A-Za-z0-9_]*)\}/',
            static fn (array $match): string =>
                $context[$match[1]] ?? $match[0],
            $value,
        );

        return $resolved ?? $value;
    }
}
