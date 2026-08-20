<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class ServiceCall
{
    public function __construct(
        private string $source,
        private string $service,
        private string $method,
        private ?string $implementation = null,
        private ?SourcePosition $position = null,
    ) {
    }

    public function source(): string { return $this->source; }
    public function service(): string { return $this->service; }
    public function method(): string { return $this->method; }
    public function implementation(): ?string { return $this->implementation; }
    public function position(): ?SourcePosition { return $this->position; }
}
