<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class UnreachableRange
{
    public function __construct(
        private string $source,
        private int $startFilePos,
        private int $endFilePos,
    ) {
    }

    public function source(): string { return $this->source; }
    public function startFilePos(): int { return $this->startFilePos; }
    public function endFilePos(): int { return $this->endFilePos; }

    public function contains(int $filePosition): bool
    {
        return $filePosition >= $this->startFilePos
            && $filePosition <= $this->endFilePos;
    }
}
