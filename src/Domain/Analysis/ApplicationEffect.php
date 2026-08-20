<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class ApplicationEffect
{
    public function __construct(
        private string $source,
        private string $kind,
        private string $operation,
        private ?string $target = null,
        private ?SourcePosition $position = null,
    ) {
    }

    public function source(): string { return $this->source; }
    public function kind(): string { return $this->kind; }
    public function operation(): string { return $this->operation; }
    public function target(): ?string { return $this->target; }
    public function position(): ?SourcePosition { return $this->position; }
}
