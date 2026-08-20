<?php

declare(strict_types=1);

namespace PhpFlow\Ast;

final readonly class MethodDefinition
{
    /**
     * @param list<string> $parameters
     */
    public function __construct(
        private string $className,
        private string $name,
        private array $parameters,
        private ?string $dispatchedParameter,
    ) {
    }

    public function className(): string { return $this->className; }
    public function name(): string { return $this->name; }

    /** @return list<string> */
    public function parameters(): array { return $this->parameters; }

    public function dispatchedParameter(): ?string { return $this->dispatchedParameter; }

    public function dispatchedParameterPosition(): ?int
    {
        if ($this->dispatchedParameter === null) {
            return null;
        }

        $position = array_search($this->dispatchedParameter, $this->parameters, true);

        return $position === false ? null : $position;
    }
}
