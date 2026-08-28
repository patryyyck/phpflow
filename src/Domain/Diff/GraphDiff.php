<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Diff;

final readonly class GraphDiff
{
    /**
     * @param list<array<string, mixed>> $addedNodes
     * @param list<array<string, mixed>> $removedNodes
     * @param list<array<string, mixed>> $addedEdges
     * @param list<array<string, mixed>> $removedEdges
     * @param array<string, array{added: int, removed: int}> $summary
     */
    public function __construct(
        private array $addedNodes,
        private array $removedNodes,
        private array $addedEdges,
        private array $removedEdges,
        private array $summary,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function addedNodes(): array
    {
        return $this->addedNodes;
    }

    /** @return list<array<string, mixed>> */
    public function removedNodes(): array
    {
        return $this->removedNodes;
    }

    /** @return list<array<string, mixed>> */
    public function addedEdges(): array
    {
        return $this->addedEdges;
    }

    /** @return list<array<string, mixed>> */
    public function removedEdges(): array
    {
        return $this->removedEdges;
    }

    /** @return array<string, array{added: int, removed: int}> */
    public function summary(): array
    {
        return $this->summary;
    }

    public function hasChanges(): bool
    {
        return $this->addedNodes !== []
            || $this->removedNodes !== []
            || $this->addedEdges !== []
            || $this->removedEdges !== [];
    }
}
