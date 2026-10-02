<?php

declare(strict_types=1);

namespace PhpFlow\Ast;

/**
 * Named arguments reaching an ancestor constructor. An argument that is passed
 * but whose value could not be read is known to be passed, and unresolved.
 */
final readonly class ResolvedArguments
{
    /**
     * @param array<string, ?array{0: mixed}> $arguments Value of each passed argument, null when unresolved
     */
    public function __construct(private array $arguments)
    {
    }

    public function isPassed(string $name): bool
    {
        return array_key_exists($name, $this->arguments);
    }

    public function isResolved(string $name): bool
    {
        return ($this->arguments[$name] ?? null) !== null;
    }

    /**
     * The literal value, or null when the argument is absent or unresolved:
     * check isResolved() to tell a literal null apart.
     */
    public function value(string $name): mixed
    {
        return ($this->arguments[$name] ?? [null])[0];
    }
}
