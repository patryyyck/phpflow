<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Graph;

final readonly class Node
{
    public function __construct(
        private string $id,
        private NodeType $type,
        private string $label,
    ) {
    }

    public function id(): string { return $this->id; }
    public function type(): NodeType { return $this->type; }
    public function label(): string { return $this->label; }
}
