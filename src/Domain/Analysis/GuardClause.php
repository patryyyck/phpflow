<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class GuardClause
{
    public function __construct(
        private string $source,
        private string $condition,
        private int $continuesAfter,
        private ?SourcePosition $position = null,
    ) {
    }

    public function source(): string { return $this->source; }
    public function condition(): string { return $this->condition; }
    public function continuesAfter(): int { return $this->continuesAfter; }
    public function position(): ?SourcePosition { return $this->position; }
}
