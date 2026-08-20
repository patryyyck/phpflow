<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class ControlBranch
{
    public function __construct(
        private string $source,
        private string $label,
        private int $startFilePos,
        private int $endFilePos,
        private ?SourcePosition $position = null,
        private bool $effectOnly = false,
    ) {
    }

    public function source(): string { return $this->source; }
    public function label(): string { return $this->label; }
    public function startFilePos(): int { return $this->startFilePos; }
    public function endFilePos(): int { return $this->endFilePos; }
    public function position(): ?SourcePosition { return $this->position; }
    public function effectOnly(): bool { return $this->effectOnly; }

    public function contains(int $filePosition): bool
    {
        return $filePosition >= $this->startFilePos
            && $filePosition <= $this->endFilePos;
    }
}
