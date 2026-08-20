<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\TraversalStep;

final class TraverseFlowGraph
{
    public function from(Graph $graph, string $startNodeId): ?TraversalStep
    {
        if ($graph->node($startNodeId) === null) {
            return null;
        }

        return $this->walk($graph, $startNodeId, []);
    }

    /**
     * @param array<string, true> $path
     */
    private function walk(Graph $graph, string $nodeId, array $path): TraversalStep
    {
        $node = $graph->node($nodeId);

        if ($node === null) {
            throw new \LogicException(sprintf('Unknown graph node "%s".', $nodeId));
        }

        if (isset($path[$nodeId])) {
            return new TraversalStep($node, [], true);
        }

        $path[$nodeId] = true;
        $children = [];

        foreach ($graph->outgoingEdges($nodeId) as $edge) {
            $children[] = $this->walk($graph, $edge->target(), $path);
        }

        return new TraversalStep($node, $children);
    }
}
