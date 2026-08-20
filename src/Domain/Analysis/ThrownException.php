<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class ThrownException
{
    public function __construct(
        private string $source,
        private string $exception,
        private ?string $condition = null,
        private ?SourcePosition $position = null,
    ) {
    }

    public function source(): string { return $this->source; }
    public function exception(): string { return $this->exception; }
    public function condition(): ?string { return $this->condition; }
    public function position(): ?SourcePosition { return $this->position; }
}
