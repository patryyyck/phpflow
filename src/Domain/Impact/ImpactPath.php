<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Impact;

use PhpFlow\Domain\Graph\Node;

final readonly class ImpactPath
{
    /** @param list<Node> $nodes */
    public function __construct(
        private array $nodes,
    ) {
    }

    /** @return list<Node> */
    public function nodes(): array
    {
        return $this->nodes;
    }

    public function root(): Node
    {
        return $this->nodes[0];
    }

    public function effect(): Node
    {
        return $this->nodes[array_key_last($this->nodes)];
    }
}
