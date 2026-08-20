<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class RepositoryCall
{
    public function __construct(
        private string $source,
        private string $repository,
        private string $method,
        private ?SourcePosition $position = null,
        private ?string $implementation = null,
    ) {
    }

    public function source(): string { return $this->source; }
    public function repository(): string { return $this->repository; }
    public function method(): string { return $this->method; }
    public function implementation(): ?string { return $this->implementation; }

    public function position(): ?SourcePosition { return $this->position; }
}
