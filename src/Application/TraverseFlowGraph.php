<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\TraversalStep;

final class TraverseFlowGraph
{
    public function from(Graph $graph, string $startNodeId): ?TraversalStep
    {
        if ($graph->node($startNodeId) === null) {
            return null;
        }

        return $this->walk($graph, $startNodeId, [], []);
    }

    /**
     * @param array<string, true> $path
     * @param array<string, string> $context
     */
    private function walk(
        Graph $graph,
        string $nodeId,
        array $path,
        array $context,
    ): TraversalStep {
        $node = $graph->node($nodeId);

        if ($node === null) {
            throw new \LogicException(sprintf('Unknown graph node "%s".', $nodeId));
        }

        $displayNode = $this->withContext($node, $context);

        if (isset($path[$nodeId])) {
            return new TraversalStep($displayNode, [], true);
        }

        $path[$nodeId] = true;
        $children = [];

        foreach ($graph->outgoingEdges($nodeId) as $edge) {
            $childContext = $this->mergeContext(
                $context,
                $edge->context(),
            );

            $children[] = $this->walk(
                $graph,
                $edge->target(),
                $path,
                $childContext,
            );
        }

        return new TraversalStep($displayNode, $children);
    }

    /**
     * @param array<string, string> $current
     * @param array<string, string> $incoming
     * @return array<string, string>
     */
    private function mergeContext(array $current, array $incoming): array
    {
        if ($incoming === []) {
            return $current;
        }

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
}
