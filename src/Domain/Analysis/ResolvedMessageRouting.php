<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class ResolvedMessageRouting
{
    /** @param list<string> $transports */
    public function __construct(
        private string $message,
        private array $transports,
    ) {
    }

    public function message(): string { return $this->message; }

    /** @return list<string> */
    public function transports(): array { return $this->transports; }
}
