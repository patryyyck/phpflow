<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class DatabaseEffect
{
    public function __construct(
        private string $source,
        private string $operation,
        private ?string $target,
        private ?string $sql = null,
        private ?SourcePosition $position = null,
    ) {
    }

    public function source(): string { return $this->source; }
    public function operation(): string { return $this->operation; }
    public function target(): ?string { return $this->target; }
    public function sql(): ?string { return $this->sql; }
    public function position(): ?SourcePosition { return $this->position; }
}
