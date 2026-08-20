<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class MethodReturn
{
    public function __construct(
        private string $source,
        private string $type,
        private ?string $branch = null,
        private ?SourcePosition $position = null,
    ) {
    }

    public function source(): string { return $this->source; }
    public function type(): string { return $this->type; }
    public function branch(): ?string { return $this->branch; }
    public function position(): ?SourcePosition { return $this->position; }
}
