<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class PhpAttribute
{
    /**
     * @param list<string> $arguments
     */
    public function __construct(
        private string $name,
        private string $target,
        private array $arguments = [],
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function shortName(): string
    {
        $parts = explode('\\', $this->name);

        return end($parts) ?: $this->name;
    }

    public function target(): string
    {
        return $this->target;
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }
}
