<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Graph;

final readonly class TraversalStep
{
    /**
     * @param list<TraversalStep> $children
     */
    public function __construct(
        private Node $node,
        private array $children = [],
        private bool $cycle = false,
    ) {
    }

    public function node(): Node { return $this->node; }

    /** @return list<TraversalStep> */
    public function children(): array { return $this->children; }

    public function cycle(): bool { return $this->cycle; }
}
