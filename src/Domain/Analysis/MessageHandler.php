<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class MessageHandler
{
    public function __construct(
        private string $message,
        private string $handler,
    ) {
    }

    public function message(): string { return $this->message; }
    public function handler(): string { return $this->handler; }
}
