<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Domain\Impact\ImpactPath;

final readonly class FindTableImpact
{
    /** @return list<ImpactPath> */
    public function find(Graph $graph, string $table): array
    {
        $targets = array_values(array_filter(
            $graph->nodes(),
            static fn (Node $node): bool =>
                $node->type() === NodeType::DATABASE
                && self::databaseTarget($node->label()) === $table,
        ));

        $paths = [];

        foreach ($targets as $target) {
            foreach ($this->pathsToRoutes($graph, $target) as $path) {
                $paths[] = new ImpactPath($path);
            }
        }

        usort(
            $paths,
            static fn (ImpactPath $left, ImpactPath $right): int =>
                $left->root()->label() <=> $right->root()->label(),
        );

        return $paths;
    }

    private static function databaseTarget(string $label): string
    {
        $parts = preg_split('/\s+/', trim($label), 2);

        return $parts[1] ?? '';
    }

    /**
     * @return list<list<Node>>
     */
    private function pathsToRoutes(Graph $graph, Node $target): array
    {
        return $this->walkBackwards(
            $graph,
            $target,
            [$target->id() => true],
        );
    }

    /**
     * @param array<string, true> $visited
     * @return list<list<Node>>
     */
    private function walkBackwards(
        Graph $graph,
        Node $node,
        array $visited,
    ): array {
        if ($node->type() === NodeType::ROUTE) {
            return [[$node]];
        }

        $paths = [];

        foreach ($graph->incomingEdges($node->id()) as $edge) {
            if (isset($visited[$edge->source()])) {
                continue;
            }

            $parent = $graph->node($edge->source());

            if ($parent === null) {
                continue;
            }

            $nextVisited = $visited;
            $nextVisited[$parent->id()] = true;

            foreach ($this->walkBackwards($graph, $parent, $nextVisited) as $path) {
                $path[] = $node;
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
