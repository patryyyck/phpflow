<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class LoopControl
{
    public function __construct(
        private string $source,
        private string $operation,
        private int $level = 1,
        private ?string $branch = null,
        private ?SourcePosition $position = null,
    ) {
    }

    public function source(): string { return $this->source; }
    public function operation(): string { return $this->operation; }
    public function level(): int { return $this->level; }
    public function branch(): ?string { return $this->branch; }
    public function position(): ?SourcePosition { return $this->position; }
}
