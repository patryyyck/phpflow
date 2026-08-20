<?php
declare(strict_types=1);
namespace PhpFlow\Domain\Graph;

final readonly class Edge
{
    public function __construct(
        private string $source,
        private string $target,
        private EdgeType $type,
        private ?string $label = null,
        private ?int $order = null,
    ) {}
    public function source(): string { return $this->source; }
    public function target(): string { return $this->target; }
    public function type(): EdgeType { return $this->type; }
    public function label(): ?string { return $this->label; }

    public function order(): ?int { return $this->order; }
}
