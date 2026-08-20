<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Domain\Graph\Graph;

final class DetectGraphCycles
{
    /**
     * @return list<string>
     */
    public function cycleNodeIds(Graph $graph): array
    {
        $cycles = [];
        $visited = [];
        $stack = [];
        $stackPositions = [];

        foreach ($graph->nodes() as $node) {
            if (!isset($visited[$node->id()])) {
                $this->walk(
                    $graph,
                    $node->id(),
                    $visited,
                    $stack,
                    $stackPositions,
                    $cycles,
                );
            }
        }

        return array_keys($cycles);
    }

    /**
     * @param array<string, true> $visited
     * @param list<string> $stack
     * @param array<string, int> $stackPositions
     * @param array<string, true> $cycles
     */
    private function walk(
        Graph $graph,
        string $nodeId,
        array &$visited,
        array &$stack,
        array &$stackPositions,
        array &$cycles,
    ): void {
        $visited[$nodeId] = true;
        $stackPositions[$nodeId] = count($stack);
        $stack[] = $nodeId;

        foreach ($graph->outgoingEdges($nodeId) as $edge) {
            $target = $edge->target();

            if (!isset($visited[$target])) {
                $this->walk(
                    $graph,
                    $target,
                    $visited,
                    $stack,
                    $stackPositions,
                    $cycles,
                );

                continue;
            }

            if (isset($stackPositions[$target])) {
                $start = $stackPositions[$target];

                for ($i = $start, $count = count($stack); $i < $count; ++$i) {
                    $cycles[$stack[$i]] = true;
                }
            }
        }

        array_pop($stack);
        unset($stackPositions[$nodeId]);
    }
}
