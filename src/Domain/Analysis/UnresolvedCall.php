<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class UnresolvedCall
{
    public function __construct(
        private string $source,
        private string $method,
        private ?string $argumentType = null,
    ) {
    }

    public function source(): string { return $this->source; }
    public function method(): string { return $this->method; }
    public function argumentType(): ?string { return $this->argumentType; }
}
