<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class HttpCall
{
    public function __construct(
        private string $source,
        private string $client,
        private ?string $method,
        private ?string $url,
        private ?SourcePosition $position = null,
    ) {
    }

    public function source(): string { return $this->source; }
    public function client(): string { return $this->client; }
    public function method(): ?string { return $this->method; }
    public function url(): ?string { return $this->url; }

    public function position(): ?SourcePosition { return $this->position; }
}
