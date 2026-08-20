<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class HttpResponse
{
    public function __construct(
        private string $source,
        private string $responseType,
        private ?int $statusCode,
        private ?string $branch = null,
        private ?SourcePosition $position = null,
    ) {
    }

    public function source(): string { return $this->source; }
    public function responseType(): string { return $this->responseType; }
    public function statusCode(): ?int { return $this->statusCode; }
    public function branch(): ?string { return $this->branch; }
    public function position(): ?SourcePosition { return $this->position; }
}
