<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class SourcePosition
{
    public function __construct(
        private int $line,
        private int $filePosition,
    ) {
    }

    public function line(): int { return $this->line; }
    public function filePosition(): int { return $this->filePosition; }
}
