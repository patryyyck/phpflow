<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Domain\Graph\Graph;

final class ExtractSubgraph
{
    public function from(Graph $graph, string $startNodeId, int $maxDepth = 10): ?Graph
    {
        if ($maxDepth < 0) {
            throw new \InvalidArgumentException('Maximum depth must be zero or greater.');
        }

        if ($graph->node($startNodeId) === null) {
            return null;
        }

        $subgraph = new Graph();
        $subgraph->setSymbolFiles($graph->symbolFiles());
        $this->walk($graph, $subgraph, $startNodeId, $maxDepth, 0, []);

        return $subgraph;
    }

    /**
     * @param array<string, true> $path
     */
    private function walk(
        Graph $graph,
        Graph $subgraph,
        string $nodeId,
        int $maxDepth,
        int $depth,
        array $path,
    ): void {
        $node = $graph->node($nodeId);

        if ($node === null) {
            return;
        }

        $subgraph->addNode($node);

        if ($depth >= $maxDepth || isset($path[$nodeId])) {
            return;
        }

        $path[$nodeId] = true;

        foreach ($graph->outgoingEdges($nodeId) as $edge) {
            $target = $graph->node($edge->target());

            if ($target === null) {
                continue;
            }

            $subgraph->addNode($target);
            $subgraph->addEdge($edge);

            $this->walk(
                $graph,
                $subgraph,
                $edge->target(),
                $maxDepth,
                $depth + 1,
                $path,
            );
        }
    }
}
