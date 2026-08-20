<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class SymfonyRoute
{
    /**
     * @param list<string> $methods
     */
    public function __construct(
        private string $controller,
        private ?string $path,
        private array $methods = [],
        private ?string $name = null,
    ) {
    }

    public function controller(): string { return $this->controller; }
    public function path(): ?string { return $this->path; }

    /** @return list<string> */
    public function methods(): array { return $this->methods; }

    public function name(): ?string { return $this->name; }
}
