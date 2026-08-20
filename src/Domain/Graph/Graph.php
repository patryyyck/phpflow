<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Graph;

final class Graph
{
    /** @var array<string, Node> */
    private array $nodes = [];

    /** @var array<string, Edge> */
    private array $edges = [];

    public function addNode(Node $node): void
    {
        $this->nodes[$node->id()] = $node;
    }

    public function addEdge(Edge $edge): void
    {
        $key = implode('|', [$edge->source(), $edge->type()->value, $edge->target()]);
        $this->edges[$key] = $edge;
    }

    /** @return list<Node> */
    public function nodes(): array
    {
        return array_values($this->nodes);
    }

    /** @return list<Edge> */
    public function edges(): array
    {
        return array_values($this->edges);
    }

    public function node(string $id): ?Node
    {
        return $this->nodes[$id] ?? null;
    }

    /** @return list<Edge> */
    public function outgoingEdges(string $nodeId): array
    {
        $edges = array_values(array_filter(
            $this->edges(),
            static fn (Edge $edge): bool => $edge->source() === $nodeId,
        ));

        usort(
            $edges,
            static fn (Edge $left, Edge $right): int =>
                ($left->order() ?? PHP_INT_MAX) <=> ($right->order() ?? PHP_INT_MAX),
        );

        return $edges;
    }

    /** @return list<Edge> */
    public function incomingEdges(string $nodeId): array
    {
        return array_values(array_filter(
            $this->edges(),
            static fn (Edge $edge): bool => $edge->target() === $nodeId,
        ));
    }

    public function reparentOutgoingAfter(
        string $sourceId,
        int $afterOrder,
        string $newSourceId,
    ): void {
        $toMove = [];

        foreach ($this->edges as $key => $edge) {
            if (
                $edge->source() === $sourceId
                && $edge->order() !== null
                && $edge->order() > $afterOrder
            ) {
                $toMove[$key] = $edge;
            }
        }

        foreach ($toMove as $key => $edge) {
            unset($this->edges[$key]);

            $this->addEdge(new Edge(
                $newSourceId,
                $edge->target(),
                $edge->type(),
                $edge->label(),
                $edge->order(),
                $edge->context(),
            ));
        }
    }


    /**
     * @param list<NodeType> $excludedTargetTypes
     */
    public function reparentOutgoingBetween(
        string $sourceId,
        int $startOrder,
        int $endOrder,
        string $newSourceId,
        array $excludedTargetTypes = [],
    ): int {
        $toMove = [];

        foreach ($this->edges as $key => $edge) {
            $targetType = $this->node($edge->target())?->type();

            if (
                $edge->source() === $sourceId
                && $edge->order() !== null
                && $edge->order() >= $startOrder
                && $edge->order() <= $endOrder
                && (
                    $targetType === null
                    || !in_array($targetType, $excludedTargetTypes, true)
                )
            ) {
                $toMove[$key] = $edge;
            }
        }

        foreach ($toMove as $key => $edge) {
            unset($this->edges[$key]);

            $this->addEdge(new Edge(
                $newSourceId,
                $edge->target(),
                $edge->type(),
                $edge->label(),
                $edge->order(),
                $edge->context(),
            ));
        }

        return count($toMove);
    }

}
