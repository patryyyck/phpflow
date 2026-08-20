<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class MessageDispatch
{
    public function __construct(
        private string $source,
        private string $message,
        private ?SourcePosition $position = null,
    ) {
    }

    public function source(): string
    {
        return $this->source;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function position(): ?SourcePosition { return $this->position; }
}
