<?php
declare(strict_types=1);
namespace PhpFlow\Domain\Analysis;

final readonly class MessengerTransport
{
    public function __construct(
        private string $name,
        private ?string $dsn,
        private string $source,
        private ?string $environment = null,
    ) {}
    public function name(): string { return $this->name; }
    public function dsn(): ?string { return $this->dsn; }
    public function source(): string { return $this->source; }
    public function environment(): ?string { return $this->environment; }
}
